<?php

declare(strict_types=1);

namespace Tests\E2E;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Config\Cookie as CookieConfig;
use Config\Security as SecurityConfig;
use Config\Services;
use Tests\Support\Database\Seeds\TestAuthSeeder;

/**
 * Opaque-box base test harness for Creditrack HU-002 E2E test tiers.
 *
 * Uses CI4's testing SQLite :memory: group (ENVIRONMENT === 'testing')
 * without accessing external environment configuration files.
 */
abstract class E2ETestCase extends CIUnitTestCase
{
    use DatabaseTestTrait {
        regressDatabase as protected traitRegressDatabase;
    }
    use FeatureTestTrait;

    /**
     * Mandatory 5-color corporate palette defined in R7 / CA-12.
     */
    protected const PALETTE = [
        '#BF0426',
        '#0511F2',
        '#023373',
        '#A61717',
        '#8D253C',
    ];

    /**
     * All 10 tables required by HU-002 Database Schema Contract.
     */
    protected const REQUIRED_HU002_TABLES = [
        'Pais',
        'Marca',
        'Departamento',
        'Municipio',
        'Distrito',
        'Agencias',
        'Agencia_Marca',
        'Auditoria_Agencias',
        'Visitas',
        'Usuario',
    ];

    protected $DBGroup     = 'tests';
    protected $namespace   = 'App';
    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $seed        = '';
    protected $seedOnce    = false;

    /**
     * Tracks which seeder populated Usuario/Pais ('app' vs 'test_auth_seeder')
     * so login helpers use the matching password automatically.
     */
    protected string $authSeedSource = 'test_auth_seeder';

    /**
     * Current authenticated session carried across FeatureTestTrait calls.
     *
     * @var array<string, mixed>
     */
    protected array $activeSession = [];

    private static ?string $cachedHnSupervisorHash = null;
    private static ?string $cachedAppDefaultHash   = null;

    /**
     * Wrap migration rollback with foreign key checks disabled so SQLite
     * PRAGMA foreign_keys = ON never blocks down() table drops.
     *
     * @return void
     */
    protected function regressDatabase()
    {
        if ($this->migrate === false) {
            return;
        }

        if ($this->db === null) {
            $this->traitRegressDatabase();

            return;
        }

        $this->db->disableForeignKeyChecks();
        try {
            $this->traitRegressDatabase();

            // Clean up un-migrated tables left by non-migration suites (e.g., AuthTestCase/TestAuthSeeder)
            $cleanupOrder = [
                'Visitas',
                'Auditoria_Agencias',
                'Agencia_Marca',
                'Agencias',
                'Distrito',
                'Municipio',
                'Departamento',
                'Marca',
                'Usuario',
                'Pais',
            ];
            $this->db->disableForeignKeyChecks();
            $this->db->resetDataCache();
            $forge = \Config\Database::forge($this->DBGroup);
            foreach ($cleanupOrder as $table) {
                $resolved = $this->resolveTableName($table);
                if ($resolved !== null) {
                    $forge->dropTable($resolved, true);
                }
            }
            $this->db->resetDataCache();
        } finally {
            $this->db->enableForeignKeyChecks();
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetHttpTestState();
        $this->seedE2EDatabase();
    }

    protected function tearDown(): void
    {
        $this->resetHttpTestState();
        $_SESSION = [];

        parent::tearDown();

        $this->resetServices();
    }

    protected function resetResponseServices(): void
    {
        Services::resetSingle('response');
        Services::resetSingle('redirectresponse');
    }

    protected function resetHttpTestState(): void
    {
        $this->activeSession = [];
        $this->withSession([]);
        $this->withHeaders([]);
        $this->withBodyFormat('');
        $this->withBody('');

        $superglobals = service('superglobals');
        $superglobals
            ->setCookieArray([])
            ->setPostArray([])
            ->setGetArray([])
            ->setRequestArray([]);

        $_COOKIE  = [];
        $_POST    = [];
        $_GET     = [];
        $_REQUEST = [];

        Services::resetSingle('security');
        $this->resetResponseServices();
    }

    /**
     * Pre-M1 and Post-M1 safe database seeding.
     * Never fabricates missing HU-002 tables.
     */
    protected function seedE2EDatabase(): void
    {
        $this->db->resetDataCache();

        if (
            $this->hasAppMigrations()
            && $this->tableExistsNormalized('Pais')
            && $this->tableExistsNormalized('Usuario')
        ) {
            $this->authSeedSource = 'app';

            $hasAllCatalogTables = $this->tableExistsNormalized('Marca')
                && $this->tableExistsNormalized('Departamento')
                && $this->tableExistsNormalized('Municipio')
                && $this->tableExistsNormalized('Distrito');

            if ($hasAllCatalogTables && class_exists(\App\Database\Seeds\AuthSeeder::class)) {
                $this->seed(\App\Database\Seeds\AuthSeeder::class);
            } else {
                if (class_exists(\App\Database\Seeds\PaisSeeder::class)) {
                    $this->seed(\App\Database\Seeds\PaisSeeder::class);
                }
                if (class_exists(\App\Database\Seeds\UsuarioSeeder::class)) {
                    $this->seed(\App\Database\Seeds\UsuarioSeeder::class);
                }
                if (
                    $this->tableExistsNormalized('Marca')
                    && class_exists(\App\Database\Seeds\MarcaSeeder::class)
                ) {
                    $this->seed(\App\Database\Seeds\MarcaSeeder::class);
                }
                if (
                    $this->tableExistsNormalized('Departamento')
                    && $this->tableExistsNormalized('Municipio')
                    && $this->tableExistsNormalized('Distrito')
                    && class_exists(\App\Database\Seeds\GeoCatalogSeeder::class)
                ) {
                    $this->seed(\App\Database\Seeds\GeoCatalogSeeder::class);
                }
            }

            $this->ensureBaselineCatalogsOnExistingTables();
        } else {
            $this->authSeedSource = 'test_auth_seeder';
            $this->seed(TestAuthSeeder::class);
            $this->db->resetDataCache();
        }

        $this->ensureHondurasSupervisorExists();
    }

    protected function hasAppMigrations(): bool
    {
        $files = glob(APPPATH . 'Database/Migrations/*.php');

        return is_array($files) && $files !== [];
    }

    /**
     * Ensures deterministic baseline data ONLY on tables that already exist from migrations.
     * Never creates any missing HU-002 tables.
     */
    protected function ensureBaselineCatalogsOnExistingTables(): void
    {
        $paisTable = $this->resolveTableName('Pais');
        if ($paisTable !== null) {
            $paisCols = $this->getTableColumnsNormalized($paisTable);
            $hasReqDist = in_array('requiere_distrito', $paisCols, true);

            $defaults = [
                ['name' => 'El Salvador', 'req' => 1],
                ['name' => 'Guatemala', 'req' => 0],
                ['name' => 'Honduras', 'req' => 1],
            ];

            foreach ($defaults as $country) {
                $row = $this->db->table($paisTable)
                    ->where('LOWER(Pais)', strtolower($country['name']))
                    ->get()
                    ->getRowArray();

                if ($row === null) {
                    $insert = ['Pais' => $country['name']];
                    if ($hasReqDist) {
                        $insert['requiere_distrito'] = $country['req'];
                    }
                    $this->db->table($paisTable)->insert($insert);
                } elseif ($hasReqDist) {
                    $norm = $this->normalizeRow($row);
                    if (! array_key_exists('requiere_distrito', $norm) || $norm['requiere_distrito'] === null) {
                        $this->db->table($paisTable)
                            ->where('Id_pais', (int) $norm['id_pais'])
                            ->update(['requiere_distrito' => $country['req']]);
                    }
                }
            }
        }

        $marcaTable = $this->resolveTableName('Marca');
        if ($marcaTable !== null && $this->db->table($marcaTable)->countAllResults() === 0) {
            foreach (['Honda', 'Yamaha', 'Suzuki'] as $brand) {
                $this->db->table($marcaTable)->insert(['Marca' => $brand]);
            }
        }

        $deptoTable = $this->resolveTableName('Departamento');
        $muniTable  = $this->resolveTableName('Municipio');
        $distTable  = $this->resolveTableName('Distrito');

        if ($paisTable !== null && $deptoTable !== null && $muniTable !== null) {
            $this->ensureCountryGeoExists('El Salvador', 'San Salvador', 'San Salvador Centro', 'San Salvador', $distTable);
            $this->ensureCountryGeoExists('Guatemala', 'Guatemala', 'Guatemala', null, $distTable);
            $this->ensureCountryGeoExists('Honduras', 'Francisco Morazán', 'Distrito Central', 'Tegucigalpa', $distTable);
        }
    }

    private function ensureCountryGeoExists(
        string $paisName,
        string $deptoName,
        string $muniName,
        ?string $distName,
        ?string $distTable
    ): void {
        $paisTable  = $this->resolveTableName('Pais');
        $deptoTable = $this->resolveTableName('Departamento');
        $muniTable  = $this->resolveTableName('Municipio');

        if ($paisTable === null || $deptoTable === null || $muniTable === null) {
            return;
        }

        $paisRow = $this->db->table($paisTable)
            ->where('LOWER(Pais)', strtolower($paisName))
            ->get()
            ->getRowArray();

        if ($paisRow === null) {
            return;
        }

        $idPais = (int) $this->normalizeRow($paisRow)['id_pais'];

        $deptoRow = $this->db->table($deptoTable)
            ->where('Id_pais', $idPais)
            ->get()
            ->getRowArray();

        if ($deptoRow === null) {
            $this->db->table($deptoTable)->insert([
                'Departamento' => $deptoName,
                'Id_pais'      => $idPais,
            ]);
            $idDepto = (int) $this->db->insertID();
        } else {
            $idDepto = (int) $this->normalizeRow($deptoRow)['id_departamento'];
        }

        $muniRow = $this->db->table($muniTable)
            ->where('Id_departamento', $idDepto)
            ->get()
            ->getRowArray();

        if ($muniRow === null) {
            $this->db->table($muniTable)->insert([
                'Municipio'       => $muniName,
                'Id_departamento' => $idDepto,
            ]);
            $idMuni = (int) $this->db->insertID();
        } else {
            $idMuni = (int) $this->normalizeRow($muniRow)['id_municipio'];
        }

        if ($distName !== null && $distTable !== null) {
            $distRow = $this->db->table($distTable)
                ->where('Id_municipio', $idMuni)
                ->get()
                ->getRowArray();

            if ($distRow === null) {
                $this->db->table($distTable)->insert([
                    'Distrito'     => $distName,
                    'Id_municipio' => $idMuni,
                ]);
            }
        }
    }

    // =========================================================================
    // Cross-Driver Case-Insensitive Schema & Query Helpers
    // =========================================================================

    protected function resolveTableName(string $preferredName): ?string
    {
        $tables = $this->db->listTables() ?: [];

        if (in_array($preferredName, $tables, true)) {
            return $preferredName;
        }

        $lower = strtolower($preferredName);
        if (in_array($lower, $tables, true)) {
            return $lower;
        }

        foreach ($tables as $table) {
            if (strcasecmp($table, $preferredName) === 0) {
                return $table;
            }
        }

        return null;
    }

    protected function tableExistsNormalized(string $tableName): bool
    {
        return $this->resolveTableName($tableName) !== null;
    }

    /**
     * @return list<string>
     */
    protected function getTableColumnsNormalized(string $tableName): array
    {
        $resolved = $this->resolveTableName($tableName);
        $this->assertNotNull($resolved, "Table '{$tableName}' does not exist in test database.");

        $fields = $this->db->getFieldNames($resolved) ?: [];

        return array_values(array_map('strtolower', $fields));
    }

    /**
     * @return list<array{table: string, from: string, to: string, on_delete: string, on_update: string}>
     */
    protected function getForeignKeysNormalized(string $tableName): array
    {
        $resolved = $this->resolveTableName($tableName);
        $this->assertNotNull($resolved, "Table '{$tableName}' does not exist in test database.");

        $normalized = [];

        if (strcasecmp($this->db->DBDriver, 'SQLite3') === 0) {
            $escaped = str_replace('"', '""', $resolved);
            $rows    = $this->db->query("PRAGMA foreign_key_list(\"{$escaped}\")")->getResultArray();

            foreach ($rows as $row) {
                $normalized[] = [
                    'table'     => strtolower((string) ($row['table'] ?? '')),
                    'from'      => strtolower((string) ($row['from'] ?? '')),
                    'to'        => strtolower((string) ($row['to'] ?? '')),
                    'on_delete' => strtoupper((string) ($row['on_delete'] ?? '')),
                    'on_update' => strtoupper((string) ($row['on_update'] ?? '')),
                ];
            }

            return $normalized;
        }

        foreach ($this->db->getForeignKeyData($resolved) as $fk) {
            $fromCols = is_array($fk->column_name ?? null) ? $fk->column_name : [];
            $toCols   = is_array($fk->foreign_column_name ?? null) ? $fk->foreign_column_name : [];
            $refTable = strtolower((string) ($fk->foreign_table_name ?? ''));

            foreach ($fromCols as $idx => $fromCol) {
                $normalized[] = [
                    'table'     => $refTable,
                    'from'      => strtolower((string) $fromCol),
                    'to'        => strtolower((string) ($toCols[$idx] ?? '')),
                    'on_delete' => strtoupper((string) ($fk->on_delete ?? '')),
                    'on_update' => strtoupper((string) ($fk->on_update ?? '')),
                ];
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected function normalizeRow(array $row): array
    {
        return array_change_key_case($row, CASE_LOWER);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    protected function normalizeRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->normalizeRow($row), $rows);
    }

    protected function isHU002SchemaReady(): bool
    {
        foreach (self::REQUIRED_HU002_TABLES as $table) {
            if (! $this->tableExistsNormalized($table)) {
                return false;
            }
        }

        return true;
    }

    protected function requireHU002Schema(): void
    {
        $missing = [];

        foreach (self::REQUIRED_HU002_TABLES as $table) {
            if (! $this->tableExistsNormalized($table)) {
                $missing[] = $table;
            }
        }

        if ($missing !== []) {
            $this->fail('HU-002 schema is incomplete; missing tables: ' . implode(', ', $missing));
        }
    }

    protected function assertSqliteForeignKeysEnabled(): void
    {
        $row = $this->db->query('PRAGMA foreign_keys')->getRowArray();
        $this->assertNotNull($row, 'Could not query SQLite PRAGMA foreign_keys.');
        $norm  = $this->normalizeRow($row);
        $value = (int) ($norm['foreign_keys'] ?? reset($norm));
        $this->assertSame(1, $value, 'SQLite PRAGMA foreign_keys must be enabled (1).');
    }

    // =========================================================================
    // Auth, Session, CSRF, & HTTP Request Helpers
    // =========================================================================

    protected function ensureHondurasSupervisorExists(): void
    {
        $paisTable    = $this->resolveTableName('Pais');
        $usuarioTable = $this->resolveTableName('Usuario');

        if ($paisTable === null || $usuarioTable === null) {
            return;
        }

        $paisCols = $this->getTableColumnsNormalized($paisTable);
        $hnRow    = $this->db->table($paisTable)
            ->where('LOWER(Pais)', 'honduras')
            ->get()
            ->getRowArray();

        if ($hnRow === null) {
            $paisInsert = ['Pais' => 'Honduras'];
            if (in_array('requiere_distrito', $paisCols, true)) {
                $paisInsert['requiere_distrito'] = 1;
            }
            $this->db->table($paisTable)->insert($paisInsert);
            $idPaisHn = (int) $this->db->insertID();
            $this->assertGreaterThan(0, $idPaisHn, 'Failed to obtain inserted Id_pais for Honduras.');
        } else {
            $normHn   = $this->normalizeRow($hnRow);
            $idPaisHn = (int) $normHn['id_pais'];
            if (in_array('requiere_distrito', $paisCols, true) && ($normHn['requiere_distrito'] ?? null) === null) {
                $this->db->table($paisTable)
                    ->where('Id_pais', $idPaisHn)
                    ->update(['requiere_distrito' => 1]);
            }
        }

        $existing = $this->db->table($usuarioTable)
            ->where('Usuario', 'supervisor_hn')
            ->get()
            ->getRowArray();

        if ($existing !== null) {
            return;
        }

        if ($this->authSeedSource === 'app') {
            if (self::$cachedAppDefaultHash === null) {
                self::$cachedAppDefaultHash = password_hash('Password123!', PASSWORD_BCRYPT);
            }
            $passwordHash = self::$cachedAppDefaultHash;
        } else {
            if (self::$cachedHnSupervisorHash === null) {
                self::$cachedHnSupervisorHash = password_hash('SuperHn123!', PASSWORD_BCRYPT);
            }
            $passwordHash = self::$cachedHnSupervisorHash;
        }

        $code      = 'EMP-SUP-03';
        $codeTaken = $this->db->table($usuarioTable)
            ->where('Codigo_Empleado', $code)
            ->countAllResults() > 0;
        if ($codeTaken) {
            $code = 'EMP-SUP-HN';
        }

        $this->db->table($usuarioTable)->insert([
            'Codigo_Empleado'   => $code,
            'Nombre'            => 'Sofia Supervisor HN',
            'Usuario'           => 'supervisor_hn',
            'Contrasena'        => $passwordHash,
            'Id_Pais'           => $idPaisHn,
            'telefono'          => '555-0203',
            'rol'               => 'Supervisor',
            'intentos_fallidos' => 0,
            'bloqueado'         => 0,
            'ultimo_fallo'      => null,
            'ultimo_login'      => null,
            'creado_en'         => '2026-11-10 10:00:00',
            'actualizado_en'    => '2026-11-10 10:00:00',
        ]);
    }

    protected function loginViaHttp(string $usuario, string $contrasena): TestResponse
    {
        $this->resetHttpTestState();
        $_SESSION = [];

        $response = $this->postForm('login', [
            'usuario'    => $usuario,
            'contrasena' => $contrasena,
        ]);

        $response->assertStatus(302);
        $this->assertMatchesRegularExpression('/home\.php/i', (string) $response->getRedirectUrl());
        $response->assertSessionHas('isLoggedIn', true);

        $captured            = ! empty($_SESSION) ? $_SESSION : (session()->get() ?? []);
        $this->activeSession = $captured;
        $this->withSession($this->activeSession);

        return $response;
    }

    protected function loginAsSupervisorSv(): TestResponse
    {
        $password = $this->authSeedSource === 'app' ? 'Password123!' : 'SuperSv123!';

        return $this->loginViaHttp('supervisor_sv', $password);
    }

    protected function loginAsSupervisorGt(): TestResponse
    {
        $password = $this->authSeedSource === 'app' ? 'Password123!' : 'SuperGt123!';

        return $this->loginViaHttp('supervisor_gt', $password);
    }

    protected function loginAsSupervisorHn(): TestResponse
    {
        $password = $this->authSeedSource === 'app' ? 'Password123!' : 'SuperHn123!';

        return $this->loginViaHttp('supervisor_hn', $password);
    }

    protected function loginAsAdmin(): TestResponse
    {
        $password = $this->authSeedSource === 'app' ? 'Password123!' : 'AdminPass123!';

        return $this->loginViaHttp('admin_sv', $password);
    }

    protected function loginAsAsesor(int $idPais = 1): TestResponse
    {
        if ($this->authSeedSource === 'app') {
            $username = match ($idPais) {
                2       => 'asesor_gt',
                3       => 'asesor_hn',
                default => 'asesor_sv',
            };

            return $this->loginViaHttp($username, 'Password123!');
        }

        return match ($idPais) {
            2       => $this->loginViaHttp('asesor_gt', 'AsesorGt123!'),
            3       => $this->loginViaHttp('asesor_hn', 'AsesorHn123!'),
            default => $this->loginViaHttp('asesor_sv', 'AsesorSv123!'),
        };
    }

    /**
     * Inject a session matching AuthController::loginSubmit() 8-key contract.
     *
     * @param array<string, mixed> $overrides
     */
    protected function withAuthenticatedSession(
        string $rol = 'Supervisor',
        int $idPais = 1,
        string $paisNombre = 'El Salvador',
        ?string $codigoEmpleado = null,
        string $usuario = 'supervisor_sv',
        string $nombre = 'Elena Rostova',
        array $overrides = []
    ): static {
        if ($codigoEmpleado === null) {
            $usuarioTable = $this->resolveTableName('Usuario');
            if ($usuarioTable !== null) {
                $row = $this->db->table($usuarioTable)
                    ->where('Usuario', $usuario)
                    ->get()
                    ->getRowArray();
                if ($row !== null) {
                    $codigoEmpleado = (string) ($this->normalizeRow($row)['codigo_empleado'] ?? '');
                }
            }

            if ($codigoEmpleado === null || $codigoEmpleado === '') {
                $codigoEmpleado = $this->authSeedSource === 'app' ? 'EMP-SV-002' : 'EMP-SUP-01';
            }
        }

        $payload = array_merge([
            'isLoggedIn'      => true,
            'codigo_empleado' => $codigoEmpleado,
            'usuario'         => $usuario,
            'nombre'          => $nombre,
            'rol'             => $rol,
            'id_pais'         => $idPais,
            'pais_nombre'     => $paisNombre,
            'login_time'      => time(),
        ], $overrides);

        $this->activeSession = $payload;
        $this->withSession($this->activeSession);

        return $this;
    }

    protected function actingAsUser(string $username): static
    {
        $usuarioTable = $this->resolveTableName('Usuario');
        $paisTable    = $this->resolveTableName('Pais');

        $this->assertNotNull($usuarioTable, 'Usuario table must exist for actingAsUser().');
        $this->assertNotNull($paisTable, 'Pais table must exist for actingAsUser().');

        $row = $this->db->table($usuarioTable)
            ->where('Usuario', $username)
            ->get()
            ->getRowArray();

        $this->assertNotNull($row, "Seeded user '{$username}' was not found in Usuario table.");
        $user = $this->normalizeRow($row);

        $idPais  = (int) $user['id_pais'];
        $paisRow = $this->db->table($paisTable)
            ->where('Id_pais', $idPais)
            ->get()
            ->getRowArray();

        $this->assertNotNull($paisRow, "Pais row for Id_pais={$idPais} not found.");
        $pais = $this->normalizeRow($paisRow);

        return $this->withAuthenticatedSession(
            rol: (string) $user['rol'],
            idPais: $idPais,
            paisNombre: (string) $pais['pais'],
            codigoEmpleado: (string) $user['codigo_empleado'],
            usuario: (string) $user['usuario'],
            nombre: (string) $user['nombre'],
        );
    }

    protected function actingAsGuest(): static
    {
        $this->resetHttpTestState();
        $_SESSION = [];

        return $this;
    }

    /**
     * Inject CSRF token field and sync CSRF cookie when cookie-based CSRF is active.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function withCsrf(array $data = []): array
    {
        $tokenName = csrf_token();
        $tokenHash = csrf_hash();

        $securityConfig = config(SecurityConfig::class);
        if ($securityConfig->csrfProtection === 'cookie' && ! $securityConfig->tokenRandomize) {
            $cookieConfig = config(CookieConfig::class);
            $cookieName   = $cookieConfig->prefix . $securityConfig->cookieName;

            $_COOKIE[$cookieName] = $tokenHash;
            service('superglobals')->setCookie($cookieName, $tokenHash);
        }

        if (! array_key_exists($tokenName, $data)) {
            $data[$tokenName] = $tokenHash;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, string|list<string>> $headers
     */
    protected function getPage(string $uri, array $query = [], array $headers = []): TestResponse
    {
        $this->resetResponseServices();
        service('superglobals')->setPostArray([]);
        $_POST = [];

        $this->withSession($this->activeSession);
        $this->withBodyFormat('');
        $this->withBody('');
        $this->withHeaders($headers);

        try {
            return $this->get($this->appendQuery($uri, $query));
        } finally {
            $this->withHeaders([]);
            $this->withBodyFormat('');
            $this->withBody('');
        }
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, string|list<string>> $headers
     */
    protected function getJson(string $uri, array $query = [], array $headers = []): TestResponse
    {
        $this->resetResponseServices();
        service('superglobals')->setPostArray([]);
        $_POST = [];

        $this->withSession($this->activeSession);
        $this->withBodyFormat('');
        $this->withBody('');
        $this->withHeaders(array_merge(['Accept' => 'application/json'], $headers));

        try {
            return $this->get($this->appendQuery($uri, $query));
        } finally {
            $this->withHeaders([]);
            $this->withBodyFormat('');
            $this->withBody('');
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $headers
     */
    protected function postForm(string $uri, array $data = [], array $headers = []): TestResponse
    {
        $this->resetResponseServices();

        $this->withSession($this->activeSession);
        $this->withBodyFormat('');
        $this->withBody('');
        $this->withHeaders($headers);

        try {
            return $this->post($uri, $this->withCsrf($data));
        } finally {
            $this->withHeaders([]);
            $this->withBodyFormat('');
            $this->withBody('');
            service('superglobals')->setPostArray([]);
            $_POST = [];
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $headers
     */
    protected function postRaw(string $uri, array $data = [], array $headers = []): TestResponse
    {
        $this->resetResponseServices();

        $this->withSession($this->activeSession);
        $this->withBodyFormat('');
        $this->withBody('');
        $this->withHeaders($headers);

        try {
            return $this->post($uri, $data);
        } finally {
            $this->withHeaders([]);
            $this->withBodyFormat('');
            $this->withBody('');
            service('superglobals')->setPostArray([]);
            $_POST = [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJsonResponse(TestResponse $response): array
    {
        $raw = $response->getJSON();
        $this->assertIsString($raw, 'Expected JSON response body.');
        $decoded = json_decode($raw, true);
        $this->assertIsArray($decoded, 'Response body did not decode as JSON array: ' . $raw);

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    protected function assertJsonOk(TestResponse $response): array
    {
        $response->assertStatus(200);
        $decoded = $this->decodeJsonResponse($response);
        $this->assertSame('ok', $decoded['status'] ?? null, 'Expected JSON payload status === "ok".');

        return $decoded;
    }

    // =========================================================================
    // Dynamic Geo Catalog, Brand, Agency & Audit Helpers
    // =========================================================================

    /**
     * @return array<string, mixed>
     */
    protected function findPaisByName(string $name): array
    {
        $table = $this->resolveTableName('Pais');
        $this->assertNotNull($table, 'Pais table does not exist.');

        $row = $this->db->table($table)
            ->where('LOWER(Pais)', strtolower($name))
            ->get()
            ->getRowArray();

        $this->assertNotNull($row, "Country '{$name}' not found in Pais table.");

        return $this->normalizeRow($row);
    }

    /**
     * @return array<string, mixed>
     */
    protected function findDepartamentoByName(string $name, ?int $idPais = null): array
    {
        $table = $this->resolveTableName('Departamento');
        $this->assertNotNull($table, 'Departamento table does not exist.');

        $builder = $this->db->table($table)->where('LOWER(Departamento)', strtolower($name));
        if ($idPais !== null) {
            $builder->where('Id_pais', $idPais);
        }

        $row = $builder->get()->getRowArray();
        $this->assertNotNull($row, "Departamento '{$name}' not found.");

        return $this->normalizeRow($row);
    }

    /**
     * @return array<string, mixed>
     */
    protected function findAnyDepartamentoForPais(int $idPais): array
    {
        $table = $this->resolveTableName('Departamento');
        $this->assertNotNull($table, 'Departamento table does not exist.');

        $row = $this->db->table($table)
            ->where('Id_pais', $idPais)
            ->orderBy('Id_departamento', 'ASC')
            ->get()
            ->getRowArray();

        $this->assertNotNull($row, "No Departamento found for Id_pais={$idPais}.");

        return $this->normalizeRow($row);
    }

    /**
     * @return array<string, mixed>
     */
    protected function findMunicipioByName(string $name, ?int $idDepartamento = null): array
    {
        $table = $this->resolveTableName('Municipio');
        $this->assertNotNull($table, 'Municipio table does not exist.');

        $builder = $this->db->table($table)->where('LOWER(Municipio)', strtolower($name));
        if ($idDepartamento !== null) {
            $builder->where('Id_departamento', $idDepartamento);
        }

        $row = $builder->get()->getRowArray();
        $this->assertNotNull($row, "Municipio '{$name}' not found.");

        return $this->normalizeRow($row);
    }

    /**
     * @return array<string, mixed>
     */
    protected function findAnyMunicipioForDepartamento(int $idDepartamento): array
    {
        $table = $this->resolveTableName('Municipio');
        $this->assertNotNull($table, 'Municipio table does not exist.');

        $row = $this->db->table($table)
            ->where('Id_departamento', $idDepartamento)
            ->orderBy('Id_municipio', 'ASC')
            ->get()
            ->getRowArray();

        $this->assertNotNull($row, "No Municipio found for Id_departamento={$idDepartamento}.");

        return $this->normalizeRow($row);
    }

    /**
     * @return array<string, mixed>
     */
    protected function findDistritoByName(string $name, ?int $idMunicipio = null): array
    {
        $table = $this->resolveTableName('Distrito');
        $this->assertNotNull($table, 'Distrito table does not exist.');

        $builder = $this->db->table($table)->where('LOWER(Distrito)', strtolower($name));
        if ($idMunicipio !== null) {
            $builder->where('Id_municipio', $idMunicipio);
        }

        $row = $builder->get()->getRowArray();
        $this->assertNotNull($row, "Distrito '{$name}' not found.");

        return $this->normalizeRow($row);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function findAnyDistritoForMunicipio(int $idMunicipio): ?array
    {
        $table = $this->resolveTableName('Distrito');
        $this->assertNotNull($table, 'Distrito table does not exist.');

        $row = $this->db->table($table)
            ->where('Id_municipio', $idMunicipio)
            ->orderBy('Id_distrito', 'ASC')
            ->get()
            ->getRowArray();

        return $row !== null ? $this->normalizeRow($row) : null;
    }

    /**
     * @return array{
     *   id_pais: int,
     *   pais: string,
     *   requiere_distrito: int,
     *   id_departamento: int,
     *   departamento: string,
     *   id_municipio: int,
     *   municipio: string,
     *   id_distrito: int|null,
     *   distrito: string|null
     * }
     */
    protected function getGeoChainForPais(string $paisName): array
    {
        $pais             = $this->findPaisByName($paisName);
        $idPais           = (int) $pais['id_pais'];
        $requiereDistrito = (int) ($pais['requiere_distrito'] ?? 1);

        $depto   = $this->findAnyDepartamentoForPais($idPais);
        $idDepto = (int) $depto['id_departamento'];

        $muni   = $this->findAnyMunicipioForDepartamento($idDepto);
        $idMuni = (int) $muni['id_municipio'];

        $dist = $this->findAnyDistritoForMunicipio($idMuni);
        if ($requiereDistrito === 1) {
            $this->assertNotNull(
                $dist,
                "Expected at least one Distrito for Municipio ID {$idMuni} in '{$paisName}' (requiere_distrito=1)."
            );
        }

        return [
            'id_pais'           => $idPais,
            'pais'              => (string) $pais['pais'],
            'requiere_distrito' => $requiereDistrito,
            'id_departamento'   => $idDepto,
            'departamento'      => (string) $depto['departamento'],
            'id_municipio'      => $idMuni,
            'municipio'         => (string) $muni['municipio'],
            'id_distrito'       => $dist !== null ? (int) $dist['id_distrito'] : null,
            'distrito'          => $dist !== null ? (string) $dist['distrito'] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function findMarcaByName(string $name): array
    {
        $table = $this->resolveTableName('Marca');
        $this->assertNotNull($table, 'Marca table does not exist.');

        $row = $this->db->table($table)
            ->where('LOWER(Marca)', strtolower($name))
            ->get()
            ->getRowArray();

        $this->assertNotNull($row, "Brand '{$name}' not found in Marca table.");

        return $this->normalizeRow($row);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function getAllMarcas(): array
    {
        $table = $this->resolveTableName('Marca');
        $this->assertNotNull($table, 'Marca table does not exist.');

        $rows = $this->db->table($table)
            ->orderBy('Id_marca', 'ASC')
            ->get()
            ->getResultArray();

        return $this->normalizeRows($rows);
    }

    /**
     * @return list<int>
     */
    protected function getDefaultBrandIds(int $count = 1): array
    {
        $marcas = $this->getAllMarcas();
        $this->assertGreaterThanOrEqual(
            $count,
            count($marcas),
            "Expected at least {$count} seeded brand(s) in Marca table."
        );

        return array_map(
            static fn (array $m): int => (int) $m['id_marca'],
            array_slice($marcas, 0, $count)
        );
    }

    /**
     * Insert an extensible country and geographic hierarchy into existing tables.
     *
     * @return array{
     *   id_pais: int,
     *   pais: string,
     *   requiere_distrito: int,
     *   id_departamento: int,
     *   departamento: string,
     *   id_municipio: int,
     *   municipio: string,
     *   id_distrito: int|null,
     *   distrito: string|null
     * }
     */
    protected function seedExtensibleCountryWithDivisions(
        string $paisName,
        int $requiereDistrito,
        string $deptoName,
        string $muniName,
        ?string $distName = null
    ): array {
        $paisTable  = $this->resolveTableName('Pais');
        $deptoTable = $this->resolveTableName('Departamento');
        $muniTable  = $this->resolveTableName('Municipio');
        $distTable  = $this->resolveTableName('Distrito');

        $this->assertNotNull($paisTable, 'Pais table does not exist.');
        $this->assertNotNull($deptoTable, 'Departamento table does not exist.');
        $this->assertNotNull($muniTable, 'Municipio table does not exist.');

        $existingPais = $this->db->table($paisTable)
            ->where('LOWER(Pais)', strtolower($paisName))
            ->get()
            ->getRowArray();

        if ($existingPais === null) {
            $paisCols   = $this->getTableColumnsNormalized($paisTable);
            $paisInsert = ['Pais' => $paisName];
            if (in_array('requiere_distrito', $paisCols, true)) {
                $paisInsert['requiere_distrito'] = $requiereDistrito;
            }
            $this->db->table($paisTable)->insert($paisInsert);
            $idPais = (int) $this->db->insertID();
        } else {
            $idPais = (int) $this->normalizeRow($existingPais)['id_pais'];
        }

        $this->db->table($deptoTable)->insert([
            'Departamento' => $deptoName,
            'Id_pais'      => $idPais,
        ]);
        $idDepto = (int) $this->db->insertID();

        $this->db->table($muniTable)->insert([
            'Municipio'       => $muniName,
            'Id_departamento' => $idDepto,
        ]);
        $idMuni = (int) $this->db->insertID();

        $idDist = null;
        if ($distName !== null) {
            $this->assertNotNull($distTable, 'Distrito table does not exist.');
            $this->db->table($distTable)->insert([
                'Distrito'     => $distName,
                'Id_municipio' => $idMuni,
            ]);
            $idDist = (int) $this->db->insertID();
        }

        return [
            'id_pais'           => $idPais,
            'pais'              => $paisName,
            'requiere_distrito' => $requiereDistrito,
            'id_departamento'   => $idDepto,
            'departamento'      => $deptoName,
            'id_municipio'      => $idMuni,
            'municipio'         => $muniName,
            'id_distrito'       => $idDist,
            'distrito'          => $distName,
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function buildValidAgencyPayload(string $paisName = 'El Salvador', array $overrides = []): array
    {
        $chain = $this->getGeoChainForPais($paisName);

        $defaultCoords = match (strtolower($paisName)) {
            'guatemala' => ['latitud' => '14.63491000', 'longitud' => '-90.50688000'],
            'honduras'  => ['latitud' => '14.07227000', 'longitud' => '-87.19213000'],
            default     => ['latitud' => '13.69294000', 'longitud' => '-89.21819000'],
        };

        $payload = [
            'nombre_agencia'  => 'Agencia E2E ' . str_replace('.', '', uniqid('', true)),
            'direccion'       => 'Av. Principal #100, ' . $chain['municipio'],
            'id_pais'         => $chain['id_pais'],
            'id_departamento' => $chain['id_departamento'],
            'id_municipio'    => $chain['id_municipio'],
            'latitud'         => $defaultCoords['latitud'],
            'longitud'        => $defaultCoords['longitud'],
            'marcas'          => $this->getDefaultBrandIds(1),
        ];

        if ($chain['requiere_distrito'] === 1 || $chain['id_distrito'] !== null) {
            $payload['id_distrito'] = $chain['id_distrito'];
        }

        return array_merge($payload, $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function createAgencyViaHttp(string $supervisorUsername = 'supervisor_sv', array $overrides = []): array
    {
        $this->actingAsUser($supervisorUsername);
        $paisName = (string) ($this->activeSession['pais_nombre'] ?? 'El Salvador');
        $payload  = $this->buildValidAgencyPayload($paisName, $overrides);

        $beforeCount = $this->countAgencies();
        $beforeMaxId = $this->getMaxAgencyId();

        $response = $this->postForm('agencias/guardar', $payload);
        $status   = $response->response()->getStatusCode();

        $this->assertTrue(
            in_array($status, [200, 201, 302, 303], true),
            "Expected successful HTTP status creating agency as {$supervisorUsername}, got {$status}."
        );

        $expectedName = trim((string) $payload['nombre_agencia']);
        $created      = $this->findAgencyByName($expectedName);
        $this->assertNotNull($created, "Agency '{$expectedName}' was not persisted in Agencias table.");
        $this->assertGreaterThan(
            $beforeMaxId,
            (int) ($created['idagencia'] ?? 0),
            "Expected newly created agency '{$expectedName}' to have Idagencia > {$beforeMaxId}."
        );
        $this->assertSame(
            $beforeCount + 1,
            $this->countAgencies(),
            "Expected Agencias row count to increase from {$beforeCount} to " . ($beforeCount + 1) . '.'
        );

        return $created;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function findAgencyByName(string $nombre): ?array
    {
        $table = $this->resolveTableName('Agencias');
        $this->assertNotNull($table, 'Agencias table does not exist.');

        $row = $this->db->table($table)
            ->where('nombre_agencia', $nombre)
            ->orderBy('Idagencia', 'DESC')
            ->get()
            ->getRowArray();

        return $row !== null ? $this->normalizeRow($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function findAgencyById(int $idagencia): ?array
    {
        $table = $this->resolveTableName('Agencias');
        $this->assertNotNull($table, 'Agencias table does not exist.');

        $row = $this->db->table($table)
            ->where('Idagencia', $idagencia)
            ->get()
            ->getRowArray();

        return $row !== null ? $this->normalizeRow($row) : null;
    }

    protected function countAgencies(): int
    {
        $table = $this->resolveTableName('Agencias');
        $this->assertNotNull($table, 'Agencias table does not exist.');

        return (int) $this->db->table($table)->countAllResults();
    }

    protected function getMaxAgencyId(): int
    {
        $table = $this->resolveTableName('Agencias');
        $this->assertNotNull($table, 'Agencias table does not exist.');

        $row = $this->db->table($table)
            ->selectMax('Idagencia', 'max_id')
            ->get()
            ->getRowArray();

        if ($row === null) {
            return 0;
        }

        $norm = $this->normalizeRow($row);

        return (int) ($norm['max_id'] ?? 0);
    }

    /**
     * @return list<int>
     */
    protected function getAgencyBrandIds(int $idagencia): array
    {
        $table = $this->resolveTableName('Agencia_Marca');
        $this->assertNotNull($table, 'Agencia_Marca table does not exist.');

        $rows = $this->db->table($table)
            ->where('Idagencia', $idagencia)
            ->orderBy('Id_marca', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(
            fn (array $r): int => (int) $this->normalizeRow($r)['id_marca'],
            $rows
        );
    }

    /**
     * @return list<string>
     */
    protected function getAgencyBrandNames(int $idagencia): array
    {
        $pivotTable = $this->resolveTableName('Agencia_Marca');
        $marcaTable = $this->resolveTableName('Marca');

        $this->assertNotNull($pivotTable, 'Agencia_Marca table does not exist.');
        $this->assertNotNull($marcaTable, 'Marca table does not exist.');

        $brandIds = $this->getAgencyBrandIds($idagencia);
        if ($brandIds === []) {
            return [];
        }

        $rows = $this->db->table($marcaTable)
            ->whereIn('Id_marca', $brandIds)
            ->orderBy('Marca', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(
            fn (array $r): string => (string) $this->normalizeRow($r)['marca'],
            $rows
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function getAuditLogsForAgency(int $idagencia, ?string $tipoOperacion = null): array
    {
        $table = $this->resolveTableName('Auditoria_Agencias');
        $this->assertNotNull($table, 'Auditoria_Agencias table does not exist.');

        $builder = $this->db->table($table)
            ->where('Idagencia', $idagencia)
            ->orderBy('Id_auditoria', 'ASC');

        if ($tipoOperacion !== null) {
            $builder->where('UPPER(tipo_operacion)', strtoupper($tipoOperacion));
        }

        return $this->normalizeRows($builder->get()->getResultArray());
    }

    /**
     * @return array<string, mixed>
     */
    protected function assertAuditRecorded(
        int $idagencia,
        string $tipoOperacion,
        string $expectedCodigoEmpleado,
        ?string $expectedMotivo = null
    ): array {
        $logs = $this->getAuditLogsForAgency($idagencia, $tipoOperacion);
        $this->assertNotEmpty(
            $logs,
            "Expected audit log entry '{$tipoOperacion}' for agency {$idagencia}."
        );

        $latest = $logs[count($logs) - 1];
        $this->assertSame($expectedCodigoEmpleado, (string) ($latest['codigo_empleado'] ?? ''));
        $this->assertNotEmpty($latest['fecha_hora'] ?? null, 'Audit fecha_hora must not be empty.');
        $this->assertSame(strtoupper($tipoOperacion), strtoupper((string) ($latest['tipo_operacion'] ?? '')));

        if (strtoupper($tipoOperacion) === 'ACTUALIZAR') {
            $this->assertNotEmpty($latest['datos_anteriores'] ?? null, 'ACTUALIZAR audit must capture datos_anteriores.');
            $this->assertNotEmpty($latest['datos_nuevos'] ?? null, 'ACTUALIZAR audit must capture datos_nuevos.');
        } elseif (strtoupper($tipoOperacion) === 'CREAR') {
            $this->assertNotEmpty($latest['datos_nuevos'] ?? null, 'CREAR audit must capture datos_nuevos.');
        }

        if ($expectedMotivo !== null) {
            $this->assertSame(
                $expectedMotivo,
                (string) ($latest['motivo_deshabilitacion'] ?? ''),
                'Audit motivo_deshabilitacion did not match expected value.'
            );
        }

        return $latest;
    }

    /**
     * @return array<string, mixed>
     */
    protected function assertAgencySoftDisabled(
        int $idagencia,
        string $expectedCodigoEmpleado,
        string $expectedMotivo
    ): array {
        $agency = $this->findAgencyById($idagencia);
        $this->assertNotNull($agency, "Agency {$idagencia} was physically deleted instead of soft-disabled.");
        $this->assertSame(0, (int) ($agency['activo'] ?? -1), 'Soft-disabled agency must have activo === 0.');
        $this->assertSame($expectedCodigoEmpleado, (string) ($agency['deshabilitado_por'] ?? ''));
        $this->assertNotEmpty($agency['deshabilitado_en'] ?? null, 'deshabilitado_en timestamp must be recorded.');
        $this->assertSame($expectedMotivo, (string) ($agency['motivo_deshabilitacion'] ?? ''));

        return $agency;
    }

    protected function assertCorporatePalettePresent(string $html): void
    {
        foreach (self::PALETTE as $color) {
            $this->assertStringContainsStringIgnoringCase(
                $color,
                $html,
                "Corporate palette color {$color} was not found in response HTML."
            );
        }
    }

    protected function assertAgencyManagementMenuPresent(string $html): void
    {
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->assertStringContainsStringIgnoringCase(
            'Gestión de agencias',
            $decoded,
            'Expected "Gestión de agencias" dropdown menu in navigation HTML.'
        );

        foreach (['agencias', 'agencias/crear', 'agencias/mapa'] as $route) {
            $pattern = '~href=["\'](?:[^"\']*/)?' . preg_quote($route, '~') . '(?=[?#"\'])~i';
            $this->assertMatchesRegularExpression(
                $pattern,
                $decoded,
                "Expected menu link to '{$route}' inside HTML."
            );
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function appendQuery(string $uri, array $query): string
    {
        if ($query === []) {
            return $uri;
        }

        $separator = str_contains($uri, '?') ? '&' : '?';

        return $uri . $separator . http_build_query($query);
    }
}
