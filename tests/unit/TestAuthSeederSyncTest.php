<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Database\Seeds\TestAuthSeeder;

/**
 * Verifies TestAuthSeeder creates all HU-002 tables, seeds catalogs while
 * preserving the 12 auth test fixtures, and resets cleanly in reverse FK order.
 *
 * @internal
 */
final class TestAuthSeederSyncTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = false;
    protected $migrateOnce = false;
    protected $refresh     = false;
    protected $seedOnce    = false;
    protected $seed        = TestAuthSeeder::class;
    protected $DBGroup     = 'tests';

    public function testTestAuthSeederPopulatesHu002TablesAndIsIdempotentWithChildRows(): void
    {
        $this->assertSame(3, $this->db->table('Pais')->countAllResults());
        $this->assertSame(12, $this->db->table('Usuario')->countAllResults());
        $this->assertGreaterThanOrEqual(7, $this->db->table('Marca')->countAllResults());
        $this->assertGreaterThan(0, $this->db->table('Departamento')->countAllResults());
        $this->assertGreaterThan(0, $this->db->table('Municipio')->countAllResults());
        $this->assertGreaterThan(0, $this->db->table('Distrito')->countAllResults());

        // Create dependent child rows across Agencias, Agencia_Marca, Auditoria_Agencias, and Visitas
        $this->db->table('Agencias')->insert([
            'nombre_agencia'  => 'Agencia Test Isolation',
            'direccion'       => 'Av. Principal #1',
            'Id_pais'         => 1,
            'Id_departamento' => 1,
            'Id_municipio'    => 1,
            'Id_distrito'     => 1,
            'latitud'         => 13.69294,
            'longitud'        => -89.21819,
            'Id_marca'        => 1,
            'activo'          => 1,
        ]);
        $idAgencia = (int) $this->db->insertID();

        $this->db->table('Agencia_Marca')->insert([
            'Idagencia' => $idAgencia,
            'Id_marca'  => 1,
        ]);

        $this->db->table('Auditoria_Agencias')->insert([
            'Idagencia'       => $idAgencia,
            'Codigo_Empleado' => 'EMP-SUP-01',
            'fecha_hora'      => '2026-11-17 10:00:00',
            'tipo_operacion'  => 'CREAR',
            'datos_nuevos'    => '{"id":1}',
        ]);

        $this->db->table('Visitas')->insert([
            'Idagencia'     => $idAgencia,
            'Codigo_Asesor' => 'EMP-ASE-01',
            'fecha_visita'  => '2026-11-17',
            'hora_visita'   => '11:00:00',
            'latitud'       => 13.69294,
            'longitud'      => -89.21819,
        ]);

        // Re-running TestAuthSeeder must cleanly empty child tables in reverse FK order without errors
        $this->seed(TestAuthSeeder::class);

        $this->assertSame(0, $this->db->table('Visitas')->countAllResults());
        $this->assertSame(0, $this->db->table('Auditoria_Agencias')->countAllResults());
        $this->assertSame(0, $this->db->table('Agencia_Marca')->countAllResults());
        $this->assertSame(0, $this->db->table('Agencias')->countAllResults());
        $this->assertSame(12, $this->db->table('Usuario')->countAllResults());
        $this->assertSame(3, $this->db->table('Pais')->countAllResults());
    }
}
