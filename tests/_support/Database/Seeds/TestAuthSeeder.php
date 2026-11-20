<?php

namespace Tests\Support\Database\Seeds;

use App\Database\Seeds\GeoCatalogSeeder;
use App\Database\Seeds\MarcaSeeder;
use CodeIgniter\Database\Seeder;

class TestAuthSeeder extends Seeder
{
    /**
     * Cached password hashes to ensure fast, deterministic test execution
     * while utilizing genuine bcrypt password hashes.
     *
     * @var array<string, string>|null
     */
    private static ?array $cachedHashes = null;

    /**
     * Get pre-computed bcrypt hashes for seed passwords.
     *
     * @return array<string, string>
     */
    private static function getHashes(): array
    {
        if (self::$cachedHashes === null) {
            self::$cachedHashes = [
                'admin_default' => password_hash('AdminPass123!', PASSWORD_BCRYPT),
                'super_sv'      => password_hash('SuperSv123!', PASSWORD_BCRYPT),
                'super_gt'      => password_hash('SuperGt123!', PASSWORD_BCRYPT),
                'asesor_sv'     => password_hash('AsesorSv123!', PASSWORD_BCRYPT),
                'asesor_gt'     => password_hash('AsesorGt123!', PASSWORD_BCRYPT),
                'asesor_hn'     => password_hash('AsesorHn123!', PASSWORD_BCRYPT),
                'warn'          => password_hash('WarnPass123!', PASSWORD_BCRYPT),
                'locked'        => password_hash('LockedPass123!', PASSWORD_BCRYPT),
                'special'       => password_hash('P@$$w0rd!#%^&*()_+ñáéíóú', PASSWORD_BCRYPT),
            ];
        }

        return self::$cachedHashes;
    }

    public function run(): void
    {
        // 1. Ensure schema exists using Forge (automatically applies DBPrefix)
        $this->createSchema();

        // 2. Clear tables in strict reverse FK dependency order to guarantee test isolation
        $tablesToEmpty = [
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
        foreach ($tablesToEmpty as $table) {
            if ($this->db->tableExists($table)) {
                $this->db->table($table)->emptyTable();
            }
        }

        if ($this->db->DBDriver === 'SQLite3' && $this->db->tableExists('sqlite_sequence')) {
            $this->db->query('DELETE FROM sqlite_sequence');
        }

        // 3. Seed Countries (Pais)
        $paises = [
            ['Id_pais' => 1, 'Pais' => 'El Salvador', 'requiere_distrito' => 1],
            ['Id_pais' => 2, 'Pais' => 'Guatemala', 'requiere_distrito' => 0],
            ['Id_pais' => 3, 'Pais' => 'Honduras', 'requiere_distrito' => 1],
        ];
        $this->db->table('Pais')->insertBatch($paises);

        // 4. Seed Users (Usuario) with bcrypt password hashes
        $hashes = self::getHashes();

        $usuarios = [
            // 1. Admin El Salvador
            [
                'Codigo_Empleado'   => 'EMP-ADM-01',
                'Nombre'            => 'Carlos Admin',
                'Usuario'           => 'admin_sv',
                'Contrasena'        => $hashes['admin_default'],
                'Id_Pais'           => 1,
                'telefono'          => '555-0100',
                'rol'               => 'Administrador',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => '2026-11-10 10:00:00',
                'actualizado_en'    => '2026-11-10 10:00:00',
            ],
            // 2. Alias admin_user for cross-feature workflows
            [
                'Codigo_Empleado'   => 'EMP-ADM-02',
                'Nombre'            => 'Carlos Morales',
                'Usuario'           => 'admin_user',
                'Contrasena'        => $hashes['admin_default'],
                'Id_Pais'           => 1,
                'telefono'          => '555-0101',
                'rol'               => 'Administrador',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => '2026-11-10 10:00:00',
                'actualizado_en'    => '2026-11-10 10:00:00',
            ],
            // 3. Supervisor El Salvador (EMP-SV-002 matching UsuarioSeeder)
            [
                'Codigo_Empleado'   => 'EMP-SV-002',
                'Nombre'            => 'Elena Rostova',
                'Usuario'           => 'supervisor_sv',
                'Contrasena'        => $hashes['super_sv'],
                'Id_Pais'           => 1,
                'telefono'          => '555-0201',
                'rol'               => 'Supervisor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => '2026-11-10 10:00:00',
                'actualizado_en'    => '2026-11-10 10:00:00',
            ],
            // 4. Supervisor Guatemala (EMP-GT-002 matching UsuarioSeeder)
            [
                'Codigo_Empleado'   => 'EMP-GT-002',
                'Nombre'            => 'Carlos Supervisor',
                'Usuario'           => 'supervisor_gt',
                'Contrasena'        => $hashes['super_gt'],
                'Id_Pais'           => 2,
                'telefono'          => '555-0202',
                'rol'               => 'Supervisor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => '2026-11-10 10:00:00',
                'actualizado_en'    => '2026-11-10 10:00:00',
            ],
            // Legacy supervisor aliases for backwards compatibility with earlier test suites
            [
                'Codigo_Empleado'   => 'EMP-SUP-01',
                'Nombre'            => 'Elena Rostova (Alias)',
                'Usuario'           => 'supervisor_sup01',
                'Contrasena'        => $hashes['super_sv'],
                'Id_Pais'           => 1,
                'telefono'          => '555-0201',
                'rol'               => 'Supervisor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => '2026-11-10 10:00:00',
                'actualizado_en'    => '2026-11-10 10:00:00',
            ],
            [
                'Codigo_Empleado'   => 'EMP-SUP-02',
                'Nombre'            => 'Beatriz Supervisor (Alias)',
                'Usuario'           => 'supervisor_sup02',
                'Contrasena'        => $hashes['super_gt'],
                'Id_Pais'           => 2,
                'telefono'          => '555-0202',
                'rol'               => 'Supervisor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => '2026-11-10 10:00:00',
                'actualizado_en'    => '2026-11-10 10:00:00',
            ],
            // 5. Asesor El Salvador
            [
                'Codigo_Empleado'   => 'EMP-ASE-01',
                'Nombre'            => 'Juan Perez',
                'Usuario'           => 'asesor_sv',
                'Contrasena'        => $hashes['asesor_sv'],
                'Id_Pais'           => 1,
                'telefono'          => '555-0301',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => '2026-11-10 10:00:00',
                'actualizado_en'    => '2026-11-10 10:00:00',
            ],
            // 6. Asesor Guatemala
            [
                'Codigo_Empleado'   => 'EMP-ASE-02',
                'Nombre'            => 'Ana Gomez',
                'Usuario'           => 'asesor_gt',
                'Contrasena'        => $hashes['asesor_gt'],
                'Id_Pais'           => 2,
                'telefono'          => '555-0302',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => '2026-11-10 10:00:00',
                'actualizado_en'    => '2026-11-10 10:00:00',
            ],
            // 7. Asesor Honduras
            [
                'Codigo_Empleado'   => 'EMP-ASE-03',
                'Nombre'            => 'Alejandro Asesor',
                'Usuario'           => 'asesor_hn',
                'Contrasena'        => $hashes['asesor_hn'],
                'Id_Pais'           => 3,
                'telefono'          => '555-0303',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => '2026-11-10 10:00:00',
                'actualizado_en'    => '2026-11-10 10:00:00',
            ],
            // 8. User with 2 prior failed attempts (for reset on success & 3rd lockout tests)
            [
                'Codigo_Empleado'   => 'EMP-WRN-01',
                'Nombre'            => 'Wendy Advertencia',
                'Usuario'           => 'user_warning',
                'Contrasena'        => $hashes['warn'],
                'Id_Pais'           => 1,
                'telefono'          => '555-0401',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 2,
                'bloqueado'         => 0,
                'ultimo_fallo'      => '2026-11-10 10:00:00',
                'ultimo_login'      => null,
                'creado_en'         => '2026-11-10 10:00:00',
                'actualizado_en'    => '2026-11-10 10:00:00',
            ],
            // 9. User pre-locked (3 attempts, bloqueado = 1) for 4th attempt rejection test
            [
                'Codigo_Empleado'   => 'EMP-LCK-01',
                'Nombre'            => 'Lorenzo Bloqueado',
                'Usuario'           => 'locked_user',
                'Contrasena'        => $hashes['locked'],
                'Id_Pais'           => 1,
                'telefono'          => '555-0501',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 3,
                'bloqueado'         => 1,
                'ultimo_fallo'      => '2026-11-10 10:00:00',
                'ultimo_login'      => null,
                'creado_en'         => '2026-11-10 10:00:00',
                'actualizado_en'    => '2026-11-10 10:00:00',
            ],
            // 10. User with special / multibyte characters in password
            [
                'Codigo_Empleado'   => 'EMP-SPC-01',
                'Nombre'            => 'Silvia Especial',
                'Usuario'           => 'special_pwd',
                'Contrasena'        => $hashes['special'],
                'Id_Pais'           => 1,
                'telefono'          => '555-0601',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => '2026-11-10 10:00:00',
                'actualizado_en'    => '2026-11-10 10:00:00',
            ],
            // 11. Clean account for sequential lockout progression
            [
                'Codigo_Empleado'   => 'EMP-CLN-01',
                'Nombre'            => 'Clara Limpia',
                'Usuario'           => 'user_clean',
                'Contrasena'        => $hashes['admin_default'],
                'Id_Pais'           => 1,
                'telefono'          => '555-0701',
                'rol'               => 'Supervisor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => '2026-11-10 10:00:00',
                'actualizado_en'    => '2026-11-10 10:00:00',
            ],
            // 12. XSS escaping test fixture
            [
                'Codigo_Empleado'   => 'EMP-XSS-01',
                'Nombre'            => 'Juan <script>alert(1)</script>',
                'Usuario'           => 'xss_user',
                'Contrasena'        => $hashes['admin_default'],
                'Id_Pais'           => 1,
                'telefono'          => '555-0801',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => '2026-11-10 10:00:00',
                'actualizado_en'    => '2026-11-10 10:00:00',
            ],
        ];

        $this->db->table('Usuario')->insertBatch($usuarios);

        // 5. Seed HU-002 Reference Catalogs (Marca & Geo Divisions)
        $this->db->table('Marca')->insertBatch(MarcaSeeder::getCatalog());
        $this->db->table('Departamento')->insertBatch(GeoCatalogSeeder::getDepartamentos());
        $this->db->table('Municipio')->insertBatch(GeoCatalogSeeder::getMunicipios());
        $this->db->table('Distrito')->insertBatch(GeoCatalogSeeder::getDistritos());
    }

    private function createSchema(): void
    {
        // Pais table
        $this->forge->addField([
            'Id_pais' => [
                'type'           => 'INTEGER',
                'auto_increment' => true,
            ],
            'Pais' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'requiere_distrito' => [
                'type'    => 'INTEGER',
                'default' => 1,
                'null'    => false,
            ],
        ]);
        $this->forge->addPrimaryKey('Id_pais');
        $this->forge->createTable('Pais', true);

        if (! $this->db->fieldExists('requiere_distrito', 'Pais')) {
            $this->forge->addColumn('Pais', [
                'requiere_distrito' => [
                    'type'    => 'INTEGER',
                    'default' => 1,
                    'null'    => false,
                ],
            ]);
        }

        // Usuario table
        $this->forge->addField([
            'Codigo_Empleado' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'Nombre' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'Usuario' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'Contrasena' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'Id_Pais' => [
                'type' => 'INTEGER',
                'null' => false,
            ],
            'telefono' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'rol' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'intentos_fallidos' => [
                'type'    => 'INTEGER',
                'default' => 0,
                'null'    => false,
            ],
            'bloqueado' => [
                'type'    => 'INTEGER',
                'default' => 0,
                'null'    => false,
            ],
            'ultimo_fallo' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'ultimo_login' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'creado_en' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'actualizado_en' => [
                'type' => 'TEXT',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('Codigo_Empleado');
        $this->forge->addUniqueKey('Usuario');
        $this->forge->createTable('Usuario', true);

        // Marca table
        $this->forge->addField([
            'Id_marca' => [
                'type'           => 'INTEGER',
                'auto_increment' => true,
            ],
            'Marca' => [
                'type'   => 'TEXT',
                'null'   => false,
                'unique' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('Id_marca');
        $this->forge->createTable('Marca', true);

        // Departamento table
        $this->forge->addField([
            'Id_departamento' => [
                'type'           => 'INTEGER',
                'auto_increment' => true,
            ],
            'Departamento' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'Id_pais' => [
                'type' => 'INTEGER',
                'null' => false,
            ],
        ]);
        $this->forge->addPrimaryKey('Id_departamento');
        $this->forge->addForeignKey('Id_pais', 'Pais', 'Id_pais', 'CASCADE', 'CASCADE');
        $this->forge->createTable('Departamento', true);

        // Municipio table
        $this->forge->addField([
            'Id_municipio' => [
                'type'           => 'INTEGER',
                'auto_increment' => true,
            ],
            'Municipio' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'Id_departamento' => [
                'type' => 'INTEGER',
                'null' => false,
            ],
        ]);
        $this->forge->addPrimaryKey('Id_municipio');
        $this->forge->addForeignKey('Id_departamento', 'Departamento', 'Id_departamento', 'CASCADE', 'CASCADE');
        $this->forge->createTable('Municipio', true);

        // Distrito table
        $this->forge->addField([
            'Id_distrito' => [
                'type'           => 'INTEGER',
                'auto_increment' => true,
            ],
            'Distrito' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'Id_municipio' => [
                'type' => 'INTEGER',
                'null' => false,
            ],
        ]);
        $this->forge->addPrimaryKey('Id_distrito');
        $this->forge->addForeignKey('Id_municipio', 'Municipio', 'Id_municipio', 'CASCADE', 'CASCADE');
        $this->forge->createTable('Distrito', true);

        // Agencias table
        $this->forge->addField([
            'Idagencia' => [
                'type'           => 'INTEGER',
                'auto_increment' => true,
            ],
            'nombre_agencia' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'direccion' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'Id_pais' => [
                'type' => 'INTEGER',
                'null' => false,
            ],
            'Id_departamento' => [
                'type' => 'INTEGER',
                'null' => false,
            ],
            'Id_municipio' => [
                'type' => 'INTEGER',
                'null' => false,
            ],
            'Id_distrito' => [
                'type' => 'INTEGER',
                'null' => true,
            ],
            'latitud' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,8',
                'null'       => true,
            ],
            'longitud' => [
                'type'       => 'DECIMAL',
                'constraint' => '11,8',
                'null'       => true,
            ],
            'Id_marca' => [
                'type' => 'INTEGER',
                'null' => true,
            ],
            'activo' => [
                'type'    => 'INTEGER',
                'default' => 1,
                'null'    => false,
            ],
            'motivo_deshabilitacion' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'deshabilitado_en' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'deshabilitado_por' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'creado_en' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'actualizado_en' => [
                'type' => 'TEXT',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('Idagencia');
        $this->forge->addForeignKey('Id_pais', 'Pais', 'Id_pais', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('Id_departamento', 'Departamento', 'Id_departamento', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('Id_municipio', 'Municipio', 'Id_municipio', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('Id_distrito', 'Distrito', 'Id_distrito', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('Id_marca', 'Marca', 'Id_marca', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('deshabilitado_por', 'Usuario', 'Codigo_Empleado', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('Agencias', true);

        // Agencia_Marca table
        $this->forge->addField([
            'Id_agencia_marca' => [
                'type'           => 'INTEGER',
                'auto_increment' => true,
            ],
            'Idagencia' => [
                'type' => 'INTEGER',
                'null' => false,
            ],
            'Id_marca' => [
                'type' => 'INTEGER',
                'null' => false,
            ],
        ]);
        $this->forge->addPrimaryKey('Id_agencia_marca');
        $this->forge->addUniqueKey(['Idagencia', 'Id_marca']);
        $this->forge->addForeignKey('Idagencia', 'Agencias', 'Idagencia', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('Id_marca', 'Marca', 'Id_marca', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('Agencia_Marca', true);

        // Auditoria_Agencias table
        $this->forge->addField([
            'Id_auditoria' => [
                'type'           => 'INTEGER',
                'auto_increment' => true,
            ],
            'Idagencia' => [
                'type' => 'INTEGER',
                'null' => false,
            ],
            'Codigo_Empleado' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'fecha_hora' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'tipo_operacion' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'datos_anteriores' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'datos_nuevos' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'motivo_deshabilitacion' => [
                'type' => 'TEXT',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('Id_auditoria');
        $this->forge->addForeignKey('Idagencia', 'Agencias', 'Idagencia', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('Codigo_Empleado', 'Usuario', 'Codigo_Empleado', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('Auditoria_Agencias', true);

        // Visitas table
        $this->forge->addField([
            'Id_visita' => [
                'type'           => 'INTEGER',
                'auto_increment' => true,
            ],
            'Idagencia' => [
                'type' => 'INTEGER',
                'null' => false,
            ],
            'Codigo_Asesor' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'fecha_visita' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'hora_visita' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'latitud' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,8',
                'null'       => false,
            ],
            'longitud' => [
                'type'       => 'DECIMAL',
                'constraint' => '11,8',
                'null'       => false,
            ],
            'observaciones' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'creado_en' => [
                'type' => 'TEXT',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('Id_visita');
        $this->forge->addForeignKey('Idagencia', 'Agencias', 'Idagencia', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('Codigo_Asesor', 'Usuario', 'Codigo_Empleado', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('Visitas', true);

        $this->db->resetDataCache();
    }
}
