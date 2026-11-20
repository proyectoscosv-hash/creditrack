<?php

namespace Tests\Unit;

use App\Database\Seeds\AuthSeeder;
use App\Models\AgenciaModel;
use App\Models\AuditoriaAgenciaModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use InvalidArgumentException;

/**
 * Unit & integration tests for AuditoriaAgenciaModel.
 *
 * @internal
 */
final class AuditoriaAgenciaModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace   = 'App';
    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $seed        = AuthSeeder::class;

    protected AuditoriaAgenciaModel $auditoriaModel;
    protected int $sampleAgenciaId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditoriaModel = new AuditoriaAgenciaModel();

        $agenciaModel          = new AgenciaModel();
        $this->sampleAgenciaId = $agenciaModel->createWithBrandsAndAudit([
            'nombre_agencia'  => 'Agencia Auditoría Test',
            'direccion'       => 'Calle Test #1',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 1,
            'latitud'         => 13.7,
            'longitud'        => -89.2,
        ], [1], 'EMP-SV-002');
    }

    public function testRecordEntryValidatesInputs(): void
    {
        $invalidCases = [
            [0, 'EMP-SV-002', AuditoriaAgenciaModel::OP_CREAR, null],
            [$this->sampleAgenciaId, '   ', AuditoriaAgenciaModel::OP_CREAR, null],
            [$this->sampleAgenciaId, 'EMP-SV-002', 'ELIMINAR', null],
            [$this->sampleAgenciaId, 'EMP-SV-002', AuditoriaAgenciaModel::OP_DESHABILITAR, '   '],
        ];

        foreach ($invalidCases as [$idAgencia, $emp, $op, $motivo]) {
            $threw = false;
            try {
                $this->auditoriaModel->recordEntry($idAgencia, $emp, $op, null, ['ok' => true], $motivo);
            } catch (InvalidArgumentException $e) {
                $threw = true;
            }
            $this->assertTrue($threw);
        }
    }

    public function testFindByAgenciaOrdersNewestFirstAndJoinsUserAndDecodesSnapshots(): void
    {
        $idUpdate = $this->auditoriaModel->recordEntry(
            $this->sampleAgenciaId,
            'EMP-SV-002',
            AuditoriaAgenciaModel::OP_ACTUALIZAR,
            ['nombre_agencia' => 'Antes'],
            ['nombre_agencia' => 'Después'],
            null
        );
        $this->assertGreaterThan(0, $idUpdate);

        $entries = $this->auditoriaModel->findByAgencia($this->sampleAgenciaId);
        $this->assertCount(2, $entries);
        $this->assertSame(AuditoriaAgenciaModel::OP_ACTUALIZAR, $entries[0]['tipo_operacion']);
        $this->assertSame('Elena Supervisora SV', $entries[0]['usuario_nombre']);
        $this->assertSame('supervisor_sv', $entries[0]['usuario_login']);
        $this->assertSame(['nombre_agencia' => 'Antes'], $entries[0]['datos_anteriores_decoded']);
        $this->assertSame(['nombre_agencia' => 'Después'], $entries[0]['datos_nuevos_decoded']);
        $this->assertSame(AuditoriaAgenciaModel::OP_CREAR, $entries[1]['tipo_operacion']);
    }
}
