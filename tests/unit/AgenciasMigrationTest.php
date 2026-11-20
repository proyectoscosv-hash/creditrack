<?php

namespace Tests\Unit;

use App\Database\Seeds\AuthSeeder;
use App\Models\UserModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Migrations;
use Config\Services;

/**
 * Integration tests for CreateAgenciasModuleTables migration, AuthSeeder,
 * SQLite foreign key enforcement, and down() rollback safety.
 *
 * @internal
 */
final class AgenciasMigrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace   = 'App';
    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $seed        = AuthSeeder::class;

    public function testMigrationCreatesAllHu002TablesAndSeedsCatalogs(): void
    {
        $expectedTables = [
            'Pais',
            'Usuario',
            'Marca',
            'Departamento',
            'Municipio',
            'Distrito',
            'Agencias',
            'Agencia_Marca',
            'Auditoria_Agencias',
            'Visitas',
        ];

        foreach ($expectedTables as $table) {
            $this->assertTrue($this->db->tableExists($table), "Expected table {$table} to exist.");
        }

        $this->assertTrue($this->db->fieldExists('requiere_distrito', 'Pais'));

        $sv = $this->db->table('Pais')->where('Id_pais', 1)->get()->getRowArray();
        $gt = $this->db->table('Pais')->where('Id_pais', 2)->get()->getRowArray();
        $hn = $this->db->table('Pais')->where('Id_pais', 3)->get()->getRowArray();

        $this->assertSame(1, (int) $sv['requiere_distrito']);
        $this->assertSame(0, (int) $gt['requiere_distrito']);
        $this->assertSame(1, (int) $hn['requiere_distrito']);

        $this->assertGreaterThanOrEqual(7, $this->db->table('Marca')->countAllResults());
        $this->assertGreaterThan(0, $this->db->table('Departamento')->countAllResults());
        $this->assertGreaterThan(0, $this->db->table('Municipio')->countAllResults());
        $this->assertGreaterThan(0, $this->db->table('Distrito')->countAllResults());

        // Guatemala (Id_pais = 2) has no districts seeded
        $gtDistrictCount = $this->db->table('Distrito')
            ->join('Municipio', 'Municipio.Id_municipio = Distrito.Id_municipio')
            ->join('Departamento', 'Departamento.Id_departamento = Municipio.Id_departamento')
            ->where('Departamento.Id_pais', 2)
            ->countAllResults();
        $this->assertSame(0, $gtDistrictCount);
    }

    public function testSqliteForeignKeysAndUniqueConstraintsAreEnforced(): void
    {
        // Confirm PRAGMA foreign_keys is ON
        $pragma = $this->db->query('PRAGMA foreign_keys')->getRowArray();
        $this->assertSame(1, (int) ($pragma['foreign_keys'] ?? 0));

        // 1. Reject Agencias with non-existent Id_pais
        $threwInvalidCountry = false;
        try {
            $this->db->table('Agencias')->insert([
                'nombre_agencia'  => 'Agencia Invalida',
                'direccion'       => 'Calle 1',
                'Id_pais'         => 999,
                'Id_departamento' => 1,
                'Id_municipio'    => 1,
                'Id_distrito'     => 1,
                'latitud'         => 13.7,
                'longitud'        => -89.2,
                'activo'          => 1,
            ]);
        } catch (DatabaseException $e) {
            $threwInvalidCountry = true;
        }
        $this->assertTrue($threwInvalidCountry, 'Expected FK violation for invalid Id_pais.');

        // 2. Allow Guatemala agency with Id_distrito = null
        $this->db->table('Agencias')->insert([
            'nombre_agencia'  => 'Agencia Guatemala Central',
            'direccion'       => '6a Avenida Zona 1',
            'Id_pais'         => 2,
            'Id_departamento' => 5,
            'Id_municipio'    => 7,
            'Id_distrito'     => null,
            'latitud'         => 14.634915,
            'longitud'        => -90.506882,
            'Id_marca'        => 1,
            'activo'          => 1,
        ]);
        $idAgencia = (int) $this->db->insertID();
        $this->assertGreaterThan(0, $idAgencia);

        // 3. Enforce UNIQUE(Idagencia, Id_marca) on Agencia_Marca
        $this->db->table('Agencia_Marca')->insert([
            'Idagencia' => $idAgencia,
            'Id_marca'  => 1,
        ]);

        $threwDuplicateBrand = false;
        try {
            $this->db->table('Agencia_Marca')->insert([
                'Idagencia' => $idAgencia,
                'Id_marca'  => 1,
            ]);
        } catch (DatabaseException $e) {
            $threwDuplicateBrand = true;
        }
        $this->assertTrue($threwDuplicateBrand, 'Expected UNIQUE constraint violation on Agencia_Marca.');

        // 4. Reject Visitas with invalid Codigo_Asesor
        $threwInvalidAdvisor = false;
        try {
            $this->db->table('Visitas')->insert([
                'Idagencia'     => $idAgencia,
                'Codigo_Asesor' => 'NO-EXISTE',
                'fecha_visita'  => '2026-11-17',
                'hora_visita'   => '10:30:00',
                'latitud'       => 14.634915,
                'longitud'      => -90.506882,
            ]);
        } catch (DatabaseException $e) {
            $threwInvalidAdvisor = true;
        }
        $this->assertTrue($threwInvalidAdvisor, 'Expected FK violation for invalid Codigo_Asesor in Visitas.');
    }

    public function testMigrationDownRollbackPreservesUsuarioForeignKeyToPais(): void
    {
        $runner = Services::migrations(new Migrations(), $this->db, false);
        $runner->setNamespace('App');

        // Roll back only CreateAgenciasModuleTables (force runs down() when already in history)
        $migrationPath = APPPATH . 'Database/Migrations/2026-11-17-000001_CreateAgenciasModuleTables.php';
        $this->assertTrue($runner->force($migrationPath, 'App', 'tests'));
        $this->db->resetDataCache();

        foreach (['Marca', 'Departamento', 'Municipio', 'Distrito', 'Agencias', 'Agencia_Marca', 'Auditoria_Agencias', 'Visitas'] as $dropped) {
            $this->assertFalse($this->db->tableExists($dropped), "Table {$dropped} should have been dropped.");
        }

        $this->assertTrue($this->db->tableExists('Pais'));
        $this->assertTrue($this->db->tableExists('Usuario'));
        $this->assertFalse($this->db->fieldExists('requiere_distrito', 'Pais'));

        // Verify Usuario FK points to Pais (NOT temp_Pais) via valid insert & invalid-country rejection
        $userModel = new UserModel($this->db);
        $this->assertTrue((bool) $userModel->insert([
            'Codigo_Empleado' => 'EMP-ROLLBACK-01',
            'Nombre'          => 'Usuario Rollback',
            'Usuario'         => 'rollback_user',
            'Contrasena'      => password_hash('Secret123!', PASSWORD_BCRYPT),
            'Id_Pais'         => 1,
            'rol'             => 'Supervisor',
        ]));

        $threwFk = false;
        try {
            $userModel->insert([
                'Codigo_Empleado' => 'EMP-ROLLBACK-BAD',
                'Nombre'          => 'Usuario Bad Country',
                'Usuario'         => 'rollback_bad',
                'Contrasena'      => password_hash('Secret123!', PASSWORD_BCRYPT),
                'Id_Pais'         => 999,
                'rol'             => 'Supervisor',
            ]);
        } catch (DatabaseException $e) {
            $threwFk = true;
        }
        $this->assertTrue($threwFk, 'Usuario FK to Pais must remain intact after down().');

        // Re-apply latest migration cleanly
        $this->assertTrue($runner->latest());
        $this->db->resetDataCache();
        $this->assertTrue($this->db->tableExists('Agencias'));
        $this->assertTrue($this->db->fieldExists('requiere_distrito', 'Pais'));
    }

    public function testAuthSeederDoubleRunIsIdempotentAndRegressZeroSucceedsWithPopulatedChildRows(): void
    {
        // 1. Populate dependent child rows across Agencias, Agencia_Marca, Auditoria_Agencias, and Visitas
        $this->db->table('Agencias')->insert([
            'nombre_agencia'    => 'Agencia Idempotencia SV',
            'direccion'         => 'Alameda Juan Pablo II #50',
            'Id_pais'           => 1,
            'Id_departamento'   => 1,
            'Id_municipio'      => 1,
            'Id_distrito'       => 1,
            'latitud'           => 13.7012,
            'longitud'          => -89.2045,
            'Id_marca'          => 1,
            'activo'            => 1,
            'deshabilitado_por' => null,
            'creado_en'         => '2026-11-18 10:00:00',
            'actualizado_en'    => '2026-11-18 10:00:00',
        ]);
        $idAgencia = (int) $this->db->insertID();
        $this->assertGreaterThan(0, $idAgencia);

        $this->db->table('Agencia_Marca')->insert([
            'Idagencia' => $idAgencia,
            'Id_marca'  => 1,
        ]);

        $this->db->table('Auditoria_Agencias')->insert([
            'Idagencia'       => $idAgencia,
            'Codigo_Empleado' => 'EMP-SV-002',
            'fecha_hora'      => '2026-11-18 10:00:00',
            'tipo_operacion'  => 'CREAR',
            'datos_nuevos'    => '{"nombre_agencia":"Agencia Idempotencia SV"}',
        ]);

        $this->db->table('Visitas')->insert([
            'Idagencia'     => $idAgencia,
            'Codigo_Asesor' => 'EMP-SV-003',
            'fecha_visita'  => '2026-11-18',
            'hora_visita'   => '10:15:00',
            'latitud'       => 13.7012,
            'longitud'      => -89.2045,
            'observaciones' => 'Visita de verificación',
            'creado_en'     => '2026-11-18 10:15:00',
        ]);

        // 2. Run AuthSeeder a second time with populated child rows -> must remain idempotent
        $this->seed(AuthSeeder::class);

        $this->assertSame(3, $this->db->table('Pais')->countAllResults());
        $this->assertSame(11, $this->db->table('Usuario')->countAllResults());
        $this->assertSame(7, $this->db->table('Marca')->countAllResults());
        $this->assertSame(11, $this->db->table('Departamento')->countAllResults());
        $this->assertSame(16, $this->db->table('Municipio')->countAllResults());
        $this->assertSame(13, $this->db->table('Distrito')->countAllResults());
        $this->assertSame(1, $this->db->table('Agencias')->countAllResults());
        $this->assertSame(1, $this->db->table('Agencia_Marca')->countAllResults());
        $this->assertSame(1, $this->db->table('Auditoria_Agencias')->countAllResults());
        $this->assertSame(1, $this->db->table('Visitas')->countAllResults());

        // 3. Full rollback via regress(0) with populated child rows must drop all 10 tables cleanly
        $runner = Services::migrations(new Migrations(), $this->db, false);
        $runner->setNamespace('App');
        $this->assertTrue($runner->regress(0));
        $this->db->resetDataCache();

        $allTables = [
            'Pais',
            'Usuario',
            'Marca',
            'Departamento',
            'Municipio',
            'Distrito',
            'Agencias',
            'Agencia_Marca',
            'Auditoria_Agencias',
            'Visitas',
        ];
        foreach ($allTables as $table) {
            $this->assertFalse($this->db->tableExists($table), "Expected table {$table} to be dropped by regress(0).");
        }

        // 4. Re-apply latest migrations and re-seed cleanly so DatabaseTestTrait teardown stays healthy
        $this->assertTrue($runner->latest());
        $this->db->resetDataCache();
        $this->seed(AuthSeeder::class);

        foreach ($allTables as $table) {
            $this->assertTrue($this->db->tableExists($table), "Expected table {$table} to be restored by latest().");
        }
        $this->assertSame(3, $this->db->table('Pais')->countAllResults());
        $this->assertSame(11, $this->db->table('Usuario')->countAllResults());
        $this->assertSame(7, $this->db->table('Marca')->countAllResults());
    }
}
