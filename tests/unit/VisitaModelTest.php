<?php

namespace Tests\Unit;

use App\Database\Seeds\AuthSeeder;
use App\Models\AgenciaModel;
use App\Models\VisitaModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use InvalidArgumentException;

/**
 * Unit & integration tests for VisitaModel (commercial advisor visits).
 *
 * @internal
 */
final class VisitaModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace   = 'App';
    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $seed        = AuthSeeder::class;

    protected VisitaModel $visitaModel;
    protected int $sampleAgenciaId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->visitaModel = new VisitaModel();

        $agenciaModel          = new AgenciaModel();
        $this->sampleAgenciaId = $agenciaModel->createWithBrandsAndAudit([
            'nombre_agencia'  => 'Agencia Visitas Test',
            'direccion'       => 'Av. Comercial #99',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 1,
            'latitud'         => 13.6929,
            'longitud'        => -89.2182,
        ], [1], 'EMP-SV-002');
    }

    public function testCreateVisitAndQueryByAgenciaAndAsesor(): void
    {
        $id1 = $this->visitaModel->createVisit([
            'idagencia'     => $this->sampleAgenciaId,
            'codigo_asesor' => 'EMP-SV-003',
            'fecha_visita'  => '2026-11-16',
            'hora_visita'   => '09:15',
            'latitud'       => 13.693,
            'longitud'      => -89.218,
            'observaciones' => 'Primera visita',
        ]);

        $id2 = $this->visitaModel->createVisit([
            'Idagencia'     => $this->sampleAgenciaId,
            'Codigo_Asesor' => 'EMP-SV-003',
            'fecha_visita'  => '2026-11-17',
            'hora_visita'   => '14:30:45',
            'latitud'       => 13.6935,
            'longitud'      => -89.2185,
            'observaciones' => 'Seguimiento',
        ]);

        $this->assertGreaterThan(0, $id1);
        $this->assertGreaterThan($id1, $id2);

        $row = $this->visitaModel->find($id1);
        $this->assertNotNull($row);
        $this->assertSame('09:15:00', $row['hora_visita']);
        $this->assertNotEmpty($row['creado_en']);

        $byAgencia = $this->visitaModel->findByAgencia($this->sampleAgenciaId);
        $this->assertCount(2, $byAgencia);
        $this->assertSame($id2, $byAgencia[0]['Id_visita']);
        $this->assertSame($id2, $byAgencia[0]['id_visita']);
        $this->assertSame('Mario Asesor SV', $byAgencia[0]['asesor_nombre']);
        $this->assertSame('Agencia Visitas Test', $byAgencia[0]['nombre_agencia']);

        $byAsesor = $this->visitaModel->findByAsesor('EMP-SV-003');
        $this->assertCount(2, $byAsesor);
        $this->assertSame($id2, $byAsesor[0]['Id_visita']);
    }

    public function testCreateVisitRejectsInvalidReferencesAndFormats(): void
    {
        $badPayloads = [
            // Non-existent agency
            [
                'idagencia'     => 9999,
                'codigo_asesor' => 'EMP-SV-003',
                'fecha_visita'  => '2026-11-17',
                'hora_visita'   => '10:00',
                'latitud'       => 13.7,
                'longitud'      => -89.2,
            ],
            // Non-existent advisor
            [
                'idagencia'     => $this->sampleAgenciaId,
                'codigo_asesor' => 'EMP-NO-EXISTE',
                'fecha_visita'  => '2026-11-17',
                'hora_visita'   => '10:00',
                'latitud'       => 13.7,
                'longitud'      => -89.2,
            ],
            // Invalid calendar date
            [
                'idagencia'     => $this->sampleAgenciaId,
                'codigo_asesor' => 'EMP-SV-003',
                'fecha_visita'  => '2026-02-30',
                'hora_visita'   => '10:00',
                'latitud'       => 13.7,
                'longitud'      => -89.2,
            ],
            // Invalid time
            [
                'idagencia'     => $this->sampleAgenciaId,
                'codigo_asesor' => 'EMP-SV-003',
                'fecha_visita'  => '2026-11-17',
                'hora_visita'   => '25:99',
                'latitud'       => 13.7,
                'longitud'      => -89.2,
            ],
            // Zero coordinates (0, 0)
            [
                'idagencia'     => $this->sampleAgenciaId,
                'codigo_asesor' => 'EMP-SV-003',
                'fecha_visita'  => '2026-11-17',
                'hora_visita'   => '10:00',
                'latitud'       => 0,
                'longitud'      => 0,
            ],
        ];

        foreach ($badPayloads as $payload) {
            $threw = false;
            try {
                $this->visitaModel->createVisit($payload);
            } catch (InvalidArgumentException $e) {
                $threw = true;
            }
            $this->assertTrue($threw);
        }

        $this->assertSame(0, $this->db->table('Visitas')->countAllResults());
    }

    public function testCreateVisitBoundaryDatesTimesWhitespaceObservacionesAndEmptyQueries(): void
    {
        // Leap-year date, midnight '00:00' -> '00:00:00', whitespace observaciones -> null
        $idLeap = $this->visitaModel->createVisit([
            'idagencia'     => $this->sampleAgenciaId,
            'codigo_asesor' => 'EMP-SV-003',
            'fecha_visita'  => '2024-02-29',
            'hora_visita'   => '00:00',
            'latitud'       => -90,
            'longitud'      => 180,
            'observaciones' => "   \t\n  ",
        ]);

        $leapRow = $this->visitaModel->find($idLeap);
        $this->assertNotNull($leapRow);
        $this->assertSame('2024-02-29', $leapRow['fecha_visita']);
        $this->assertSame('00:00:00', $leapRow['hora_visita']);
        $this->assertNull($leapRow['observaciones']);
        $this->assertEqualsWithDelta(-90.0, $leapRow['latitud'], 0.000001);
        $this->assertEqualsWithDelta(180.0, $leapRow['longitud'], 0.000001);

        // End-of-day boundary '23:59:59' and trimmed observaciones
        $idTrimmed = $this->visitaModel->createVisit([
            'idagencia'     => $this->sampleAgenciaId,
            'codigo_asesor' => 'EMP-SV-003',
            'fecha_visita'  => '2026-12-31',
            'hora_visita'   => '23:59:59',
            'latitud'       => 13.701,
            'longitud'      => -89.201,
            'observaciones' => '  Visita de cierre  ',
        ]);
        $trimmedRow = $this->visitaModel->find($idTrimmed);
        $this->assertNotNull($trimmedRow);
        $this->assertSame('23:59:59', $trimmedRow['hora_visita']);
        $this->assertSame('Visita de cierre', $trimmedRow['observaciones']);

        // Omitted observaciones -> null
        $idOmitted = $this->visitaModel->createVisit([
            'idagencia'     => $this->sampleAgenciaId,
            'codigo_asesor' => 'EMP-SV-003',
            'fecha_visita'  => '2026-12-31',
            'hora_visita'   => '23:59',
            'latitud'       => 13.702,
            'longitud'      => -89.202,
        ]);
        $omittedRow = $this->visitaModel->find($idOmitted);
        $this->assertNotNull($omittedRow);
        $this->assertSame('23:59:00', $omittedRow['hora_visita']);
        $this->assertNull($omittedRow['observaciones']);

        // Boundary rejections
        $boundaryBadPayloads = [
            // Non-leap year Feb 29
            [
                'idagencia'     => $this->sampleAgenciaId,
                'codigo_asesor' => 'EMP-SV-003',
                'fecha_visita'  => '2025-02-29',
                'hora_visita'   => '10:00',
                'latitud'       => 13.7,
                'longitud'      => -89.2,
            ],
            // Date with non-ISO format
            [
                'idagencia'     => $this->sampleAgenciaId,
                'codigo_asesor' => 'EMP-SV-003',
                'fecha_visita'  => '29/02/2024',
                'hora_visita'   => '10:00',
                'latitud'       => 13.7,
                'longitud'      => -89.2,
            ],
            // Invalid time '24:00'
            [
                'idagencia'     => $this->sampleAgenciaId,
                'codigo_asesor' => 'EMP-SV-003',
                'fecha_visita'  => '2026-11-17',
                'hora_visita'   => '24:00',
                'latitud'       => 13.7,
                'longitud'      => -89.2,
            ],
            // Unpadded hour '9:15'
            [
                'idagencia'     => $this->sampleAgenciaId,
                'codigo_asesor' => 'EMP-SV-003',
                'fecha_visita'  => '2026-11-17',
                'hora_visita'   => '9:15',
                'latitud'       => 13.7,
                'longitud'      => -89.2,
            ],
            // Invalid second '09:15:60'
            [
                'idagencia'     => $this->sampleAgenciaId,
                'codigo_asesor' => 'EMP-SV-003',
                'fecha_visita'  => '2026-11-17',
                'hora_visita'   => '09:15:60',
                'latitud'       => 13.7,
                'longitud'      => -89.2,
            ],
            // Missing / non-positive agencia id
            [
                'idagencia'     => 0,
                'codigo_asesor' => 'EMP-SV-003',
                'fecha_visita'  => '2026-11-17',
                'hora_visita'   => '10:00',
                'latitud'       => 13.7,
                'longitud'      => -89.2,
            ],
            // Whitespace advisor code
            [
                'idagencia'     => $this->sampleAgenciaId,
                'codigo_asesor' => '   ',
                'fecha_visita'  => '2026-11-17',
                'hora_visita'   => '10:00',
                'latitud'       => 13.7,
                'longitud'      => -89.2,
            ],
            // Out-of-range latitude & longitude
            [
                'idagencia'     => $this->sampleAgenciaId,
                'codigo_asesor' => 'EMP-SV-003',
                'fecha_visita'  => '2026-11-17',
                'hora_visita'   => '10:00',
                'latitud'       => 90.0001,
                'longitud'      => -89.2,
            ],
            [
                'idagencia'     => $this->sampleAgenciaId,
                'codigo_asesor' => 'EMP-SV-003',
                'fecha_visita'  => '2026-11-17',
                'hora_visita'   => '10:00',
                'latitud'       => 13.7,
                'longitud'      => -180.0001,
            ],
        ];

        foreach ($boundaryBadPayloads as $payload) {
            $threw = false;
            try {
                $this->visitaModel->createVisit($payload);
            } catch (InvalidArgumentException $e) {
                $threw = true;
            }
            $this->assertTrue($threw, 'Expected InvalidArgumentException for payload: ' . json_encode($payload));
        }

        $this->assertSame(3, $this->db->table('Visitas')->countAllResults());
        $this->assertSame([], $this->visitaModel->findByAgencia(0));
        $this->assertSame([], $this->visitaModel->findByAgencia(-1));
        $this->assertSame([], $this->visitaModel->findByAsesor(''));
        $this->assertSame([], $this->visitaModel->findByAsesor('   '));
    }
}
