<?php

namespace Tests\Unit;

use App\Database\Seeds\AuthSeeder;
use App\Models\AgenciaModel;
use App\Models\AuditoriaAgenciaModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use DomainException;
use InvalidArgumentException;
use RuntimeException;

/**
 * Unit & integration tests for AgenciaModel transactional CRUD,
 * cascading geo rules, Guatemala optional district, multi-brand M:N,
 * soft-disable, audit snapshots, and geolocation query filtering.
 *
 * @internal
 */
final class AgenciaModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace   = 'App';
    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $seed        = AuthSeeder::class;

    protected AgenciaModel $agenciaModel;
    protected AuditoriaAgenciaModel $auditoriaModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agenciaModel   = new AgenciaModel();
        $this->auditoriaModel = new AuditoriaAgenciaModel();
    }

    public function testModelUsesCustomSoftDisableInsteadOfCiSoftDeletes(): void
    {
        $this->assertFalse($this->agenciaModel->useSoftDeletes);
    }

    public function testCoordinateParsingAndValidationBoundaries(): void
    {
        $this->assertNull(AgenciaModel::parseCoordinate(null));
        $this->assertNull(AgenciaModel::parseCoordinate(''));
        $this->assertNull(AgenciaModel::parseCoordinate('abc'));
        $this->assertNull(AgenciaModel::parseCoordinate(false));
        $this->assertNull(AgenciaModel::parseCoordinate(['13.7']));
        $this->assertNull(AgenciaModel::parseCoordinate(NAN));

        $this->assertFalse(AgenciaModel::isValidCoordinates(null, -89.2));
        $this->assertFalse(AgenciaModel::isValidCoordinates(0, 0));
        $this->assertFalse(AgenciaModel::isValidCoordinates('0.0', '0.0'));
        $this->assertFalse(AgenciaModel::isValidCoordinates(-90.0001, -89.2));
        $this->assertFalse(AgenciaModel::isValidCoordinates(90.0001, -89.2));
        $this->assertFalse(AgenciaModel::isValidCoordinates(13.7, -180.0001));
        $this->assertFalse(AgenciaModel::isValidCoordinates(13.7, 180.0001));

        $this->assertTrue(AgenciaModel::isValidCoordinates(-90, 180));
        $this->assertTrue(AgenciaModel::isValidCoordinates('13.69294', '-89.21819'));
    }

    public function testCreateElSalvadorAgencyWithMultipleBrandsAndAudit(): void
    {
        $id = $this->agenciaModel->createWithBrandsAndAudit([
            'nombre_agencia'  => 'Agencia San Salvador Central',
            'direccion'       => 'Alameda Roosevelt #101',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 1,
            'latitud'         => '13.69294000',
            'longitud'        => '-89.21819000',
        ], [1, 2, 1], 'EMP-SV-002');

        $this->assertGreaterThan(0, $id);

        $agency = $this->agenciaModel->findByIdAndPais($id, 1);
        $this->assertNotNull($agency);
        $this->assertSame('Agencia San Salvador Central', $agency['nombre_agencia']);
        $this->assertSame(1, $agency['activo']);
        $this->assertSame(1, $agency['requiere_distrito']);
        $this->assertSame([1, 2], $agency['marca_ids']);
        $this->assertSame(['Honda', 'Yamaha'], $agency['marca_nombres']);

        $audits = $this->auditoriaModel->findByAgencia($id);
        $this->assertCount(1, $audits);
        $this->assertSame(AuditoriaAgenciaModel::OP_CREAR, $audits[0]['tipo_operacion']);
        $this->assertSame('EMP-SV-002', $audits[0]['Codigo_Empleado']);
        $this->assertNull($audits[0]['datos_anteriores_decoded']);
        $this->assertIsArray($audits[0]['datos_nuevos_decoded']);
        $this->assertSame('Agencia San Salvador Central', $audits[0]['datos_nuevos_decoded']['nombre_agencia']);
    }

    public function testCreateGuatemalaAgencyAllowsNullAndEmptyDistrict(): void
    {
        $idNull = $this->agenciaModel->createWithBrandsAndAudit([
            'nombre_agencia'  => 'Agencia Zona 1 GT',
            'direccion'       => '6a Avenida 10-20 Zona 1',
            'id_pais'         => 2,
            'id_departamento' => 5,
            'id_municipio'    => 7,
            'id_distrito'     => null,
            'latitud'         => 14.634915,
            'longitud'        => -90.506882,
        ], [3], 'EMP-GT-002');

        $agency1 = $this->agenciaModel->findByIdAndPais($idNull, 2);
        $this->assertNotNull($agency1);
        $this->assertNull($agency1['Id_distrito']);
        $this->assertNull($agency1['id_distrito']);
        $this->assertSame(0, $agency1['requiere_distrito']);

        $idEmpty = $this->agenciaModel->createWithBrandsAndAudit([
            'nombre_agencia'  => 'Agencia Mixco GT',
            'direccion'       => 'Calzada Roosevelt Km 14',
            'id_pais'         => 2,
            'id_departamento' => 5,
            'id_municipio'    => 8,
            'id_distrito'     => '',
            'latitud'         => 14.6305,
            'longitud'        => -90.6061,
        ], [4, 5], 'EMP-GT-002');

        $agency2 = $this->agenciaModel->findByIdAndPais($idEmpty, 2);
        $this->assertNotNull($agency2);
        $this->assertNull($agency2['Id_distrito']);
        $this->assertSame([5, 4], $agency2['marca_ids']);
        $this->assertSame(['Bajaj', 'Hero'], $agency2['marca_nombres']);

        $idZeroInt = $this->agenciaModel->createWithBrandsAndAudit([
            'nombre_agencia'  => 'Agencia Villa Nueva GT',
            'direccion'       => 'Km 16 Ruta al Pacífico',
            'id_pais'         => 2,
            'id_departamento' => 5,
            'id_municipio'    => 9,
            'id_distrito'     => 0,
            'latitud'         => 14.5269,
            'longitud'        => -90.5875,
        ], [1], 'EMP-GT-002');

        $agency3 = $this->agenciaModel->findByIdAndPais($idZeroInt, 2);
        $this->assertNotNull($agency3);
        $this->assertNull($agency3['Id_distrito']);

        $idZeroStr = $this->agenciaModel->createWithBrandsAndAudit([
            'nombre_agencia'  => 'Agencia Antigua GT',
            'direccion'       => '5a Calle Poniente',
            'id_pais'         => 2,
            'id_departamento' => 6,
            'id_municipio'    => 10,
            'id_distrito'     => '0',
            'latitud'         => 14.5586,
            'longitud'        => -90.7295,
        ], [2], 'EMP-GT-002');

        $agency4 = $this->agenciaModel->findByIdAndPais($idZeroStr, 2);
        $this->assertNotNull($agency4);
        $this->assertNull($agency4['Id_distrito']);
    }

    public function testCreateGuatemalaAgencyRejectsNonNumericAndInvalidDistrict(): void
    {
        foreach (['abc', '12abc', -1, true, 1] as $badDistrict) {
            $threw = false;
            try {
                $this->agenciaModel->createWithBrandsAndAudit([
                    'nombre_agencia'  => 'GT Distrito Invalido',
                    'direccion'       => '6a Avenida',
                    'id_pais'         => 2,
                    'id_departamento' => 5,
                    'id_municipio'    => 7,
                    'id_distrito'     => $badDistrict,
                    'latitud'         => 14.634915,
                    'longitud'        => -90.506882,
                ], [1], 'EMP-GT-002');
            } catch (InvalidArgumentException $e) {
                $threw = true;
            }
            $this->assertTrue($threw, 'Expected InvalidArgumentException for GT district: ' . var_export($badDistrict, true));
        }

        $this->assertSame(0, $this->db->table('Agencias')->countAllResults());
        $this->assertSame(0, $this->db->table('Agencia_Marca')->countAllResults());
        $this->assertSame(0, $this->db->table('Auditoria_Agencias')->countAllResults());
    }

    public function testCreateValidationRejectionsLeaveDatabaseClean(): void
    {
        $invalidPayloads = [
            // Missing district in El Salvador (requiere_distrito = 1)
            [
                [
                    'nombre_agencia'  => 'Sin Distrito SV',
                    'direccion'       => 'Dir',
                    'id_pais'         => 1,
                    'id_departamento' => 1,
                    'id_municipio'    => 1,
                    'id_distrito'     => null,
                    'latitud'         => 13.7,
                    'longitud'        => -89.2,
                ],
                [1],
            ],
            // Cross-country department (Guatemala dept for El Salvador)
            [
                [
                    'nombre_agencia'  => 'Depto Cruzado',
                    'direccion'       => 'Dir',
                    'id_pais'         => 1,
                    'id_departamento' => 5,
                    'id_municipio'    => 7,
                    'id_distrito'     => 1,
                    'latitud'         => 13.7,
                    'longitud'        => -89.2,
                ],
                [1],
            ],
            // Mismatched district (Santa Tecla district under SS Centro muni)
            [
                [
                    'nombre_agencia'  => 'Distrito Cruzado',
                    'direccion'       => 'Dir',
                    'id_pais'         => 1,
                    'id_departamento' => 1,
                    'id_municipio'    => 1,
                    'id_distrito'     => 5,
                    'latitud'         => 13.7,
                    'longitud'        => -89.2,
                ],
                [1],
            ],
            // Zero coordinates (0, 0)
            [
                [
                    'nombre_agencia'  => 'Coords Cero',
                    'direccion'       => 'Dir',
                    'id_pais'         => 1,
                    'id_departamento' => 1,
                    'id_municipio'    => 1,
                    'id_distrito'     => 1,
                    'latitud'         => 0,
                    'longitud'        => 0,
                ],
                [1],
            ],
            // Empty brands
            [
                [
                    'nombre_agencia'  => 'Sin Marcas',
                    'direccion'       => 'Dir',
                    'id_pais'         => 1,
                    'id_departamento' => 1,
                    'id_municipio'    => 1,
                    'id_distrito'     => 1,
                    'latitud'         => 13.7,
                    'longitud'        => -89.2,
                ],
                [],
            ],
            // Non-existent brand ID
            [
                [
                    'nombre_agencia'  => 'Marca Inexistente',
                    'direccion'       => 'Dir',
                    'id_pais'         => 1,
                    'id_departamento' => 1,
                    'id_municipio'    => 1,
                    'id_distrito'     => 1,
                    'latitud'         => 13.7,
                    'longitud'        => -89.2,
                ],
                [9999],
            ],
        ];

        foreach ($invalidPayloads as [$data, $brands]) {
            $threw = false;
            try {
                $this->agenciaModel->createWithBrandsAndAudit($data, $brands, 'EMP-SV-002');
            } catch (InvalidArgumentException $e) {
                $threw = true;
            }
            $this->assertTrue($threw, 'Expected InvalidArgumentException for invalid payload.');
        }

        $this->assertSame(0, $this->db->table('Agencias')->countAllResults());
        $this->assertSame(0, $this->db->table('Agencia_Marca')->countAllResults());
        $this->assertSame(0, $this->db->table('Auditoria_Agencias')->countAllResults());
    }

    public function testTransactionalRollbackWhenAuditWriteFails(): void
    {
        $failingAuditModel = $this->getMockBuilder(AuditoriaAgenciaModel::class)
            ->setConstructorArgs([$this->db])
            ->onlyMethods(['recordEntry'])
            ->getMock();
        $failingAuditModel->method('recordEntry')
            ->willThrowException(new RuntimeException('Simulated audit failure'));

        $this->agenciaModel->setAuditoriaModel($failingAuditModel);

        $threw = false;
        try {
            $this->agenciaModel->createWithBrandsAndAudit([
                'nombre_agencia'  => 'Agencia Rollback',
                'direccion'       => 'Av. Central',
                'id_pais'         => 1,
                'id_departamento' => 1,
                'id_municipio'    => 1,
                'id_distrito'     => 1,
                'latitud'         => 13.7,
                'longitud'        => -89.2,
            ], [1, 2], 'EMP-SV-002');
        } catch (RuntimeException $e) {
            $threw = true;
        }

        $this->assertTrue($threw);
        $this->assertSame(0, $this->db->table('Agencias')->countAllResults());
        $this->assertSame(0, $this->db->table('Agencia_Marca')->countAllResults());
        $this->assertSame(0, $this->db->table('Auditoria_Agencias')->countAllResults());
    }

    public function testUpdateWithBrandsAndCrossCountryIsolation(): void
    {
        $id = $this->agenciaModel->createWithBrandsAndAudit([
            'nombre_agencia'  => 'Agencia Original',
            'direccion'       => 'Calle Vieja #10',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 1,
            'latitud'         => 13.69,
            'longitud'        => -89.21,
        ], [1, 2], 'EMP-SV-002');

        // Attempt update as Guatemala supervisor -> rejected
        $threwWrongCountry = false;
        try {
            $this->agenciaModel->updateWithBrandsAndAudit($id, 2, [
                'nombre_agencia'  => 'Hijacked',
                'direccion'       => 'Dir',
                'id_pais'         => 2,
                'id_departamento' => 5,
                'id_municipio'    => 7,
                'id_distrito'     => null,
                'latitud'         => 14.6,
                'longitud'        => -90.5,
            ], [1], 'EMP-GT-002');
        } catch (RuntimeException $e) {
            $threwWrongCountry = true;
        }
        $this->assertTrue($threwWrongCountry);

        // Attempt changing id_pais inside payload -> rejected
        $threwChangePais = false;
        try {
            $this->agenciaModel->updateWithBrandsAndAudit($id, 1, [
                'nombre_agencia'  => 'Move Country',
                'direccion'       => 'Dir',
                'id_pais'         => 2,
                'id_departamento' => 5,
                'id_municipio'    => 7,
                'id_distrito'     => null,
                'latitud'         => 14.6,
                'longitud'        => -90.5,
            ], [1], 'EMP-SV-002');
        } catch (DomainException $e) {
            $threwChangePais = true;
        }
        $this->assertTrue($threwChangePais);

        // Valid update replacing brands [1, 2] -> [2, 3]
        $updated = $this->agenciaModel->updateWithBrandsAndAudit($id, 1, [
            'nombre_agencia'  => 'Agencia Actualizada',
            'direccion'       => 'Boulevard Los Héroes #250',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 2,
            'id_distrito'     => 3,
            'latitud'         => 13.705,
            'longitud'        => -89.15,
        ], [2, 3], 'EMP-SV-002');

        $this->assertSame('Agencia Actualizada', $updated['nombre_agencia']);
        $this->assertSame([3, 2], $updated['marca_ids']);
        $this->assertSame(['Suzuki', 'Yamaha'], $updated['marca_nombres']);

        $audits = $this->auditoriaModel->findByAgencia($id);
        $this->assertCount(2, $audits);
        $this->assertSame(AuditoriaAgenciaModel::OP_ACTUALIZAR, $audits[0]['tipo_operacion']);
        $this->assertSame('Agencia Original', $audits[0]['datos_anteriores_decoded']['nombre_agencia']);
        $this->assertSame('Agencia Actualizada', $audits[0]['datos_nuevos_decoded']['nombre_agencia']);
    }

    public function testSoftDisablePreservesRecordAndLogsReason(): void
    {
        $id = $this->agenciaModel->createWithBrandsAndAudit([
            'nombre_agencia'  => 'Agencia Temporal',
            'direccion'       => 'Calle 5',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 1,
            'latitud'         => 13.7,
            'longitud'        => -89.2,
        ], [1], 'EMP-SV-002');

        // Empty reason rejected
        $threwEmptyReason = false;
        try {
            $this->agenciaModel->softDisableWithAudit($id, 1, '   ', 'EMP-SV-002');
        } catch (InvalidArgumentException $e) {
            $threwEmptyReason = true;
        }
        $this->assertTrue($threwEmptyReason);

        // Valid disable
        $disabled = $this->agenciaModel->softDisableWithAudit($id, 1, 'Cierre por reubicación', 'EMP-SV-002');
        $this->assertSame(0, $disabled['activo']);
        $this->assertSame('Cierre por reubicación', $disabled['motivo_deshabilitacion']);
        $this->assertSame('EMP-SV-002', $disabled['deshabilitado_por']);
        $this->assertNotNull($disabled['deshabilitado_en']);

        // Record still exists in DB
        $this->assertNotNull($this->agenciaModel->findByIdRaw($id));

        // Double disable rejected
        $threwDouble = false;
        try {
            $this->agenciaModel->softDisableWithAudit($id, 1, 'Otro motivo', 'EMP-SV-002');
        } catch (DomainException $e) {
            $threwDouble = true;
        }
        $this->assertTrue($threwDouble);

        $audits = $this->auditoriaModel->findByAgencia($id);
        $this->assertSame(AuditoriaAgenciaModel::OP_DESHABILITAR, $audits[0]['tipo_operacion']);
        $this->assertSame('Cierre por reubicación', $audits[0]['motivo_deshabilitacion']);
        $this->assertSame(1, $audits[0]['datos_anteriores_decoded']['activo']);
        $this->assertSame(0, $audits[0]['datos_nuevos_decoded']['activo']);
    }

    public function testCountryScopedConsultationAndGeolocationFiltersWithoutDuplication(): void
    {
        // Multi-brand active agency in SV (San Salvador Centro)
        $idSv1 = $this->agenciaModel->createWithBrandsAndAudit([
            'nombre_agencia'  => 'SV MultiMarca Activa',
            'direccion'       => 'Centro Histórico',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 1,
            'latitud'         => 13.6989,
            'longitud'        => -89.1914,
        ], [1, 2, 5], 'EMP-SV-002');

        // Active agency in SV (Santa Tecla)
        $idSv2 = $this->agenciaModel->createWithBrandsAndAudit([
            'nombre_agencia'  => 'SV Santa Tecla',
            'direccion'       => 'Paseo El Carmen',
            'id_pais'         => 1,
            'id_departamento' => 2,
            'id_municipio'    => 3,
            'id_distrito'     => 5,
            'latitud'         => 13.6769,
            'longitud'        => -89.2797,
        ], [3], 'EMP-SV-002');

        // Disabled agency in SV
        $idSvDisabled = $this->agenciaModel->createWithBrandsAndAudit([
            'nombre_agencia'  => 'SV Inactiva',
            'direccion'       => 'Cerrada',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 2,
            'latitud'         => 13.72,
            'longitud'        => -89.21,
        ], [1], 'EMP-SV-002');
        $this->agenciaModel->softDisableWithAudit($idSvDisabled, 1, 'Cerrada', 'EMP-SV-002');

        // Agency with invalid/null coords inserted directly in DB
        $this->db->table('Agencias')->insert([
            'nombre_agencia'  => 'SV Sin Coordenadas',
            'direccion'       => 'Pendiente',
            'Id_pais'         => 1,
            'Id_departamento' => 1,
            'Id_municipio'    => 1,
            'Id_distrito'     => 1,
            'latitud'         => null,
            'longitud'        => null,
            'activo'          => 1,
        ]);

        // Guatemala agency (must never appear in SV queries)
        $this->agenciaModel->createWithBrandsAndAudit([
            'nombre_agencia'  => 'GT Exclusiva',
            'direccion'       => 'Zona 10',
            'id_pais'         => 2,
            'id_departamento' => 5,
            'id_municipio'    => 7,
            'id_distrito'     => null,
            'latitud'         => 14.5995,
            'longitud'        => -90.515,
        ], [1], 'EMP-GT-002');

        // Consultation list: country scoped + brand filter returns multi-brand agency ONCE with all 3 brands
        $byHonda = $this->agenciaModel->findAllByPaisWithDetails(1, [
            'estado'   => 'activas',
            'id_marca' => 1,
        ]);
        $this->assertCount(1, $byHonda);
        $this->assertSame($idSv1, $byHonda[0]['Idagencia']);
        $this->assertSame([5, 1, 2], $byHonda[0]['marca_ids']);
        $this->assertSame(['Bajaj', 'Honda', 'Yamaha'], $byHonda[0]['marca_nombres']);

        // Search filter
        $bySearch = $this->agenciaModel->findAllByPaisWithDetails(1, ['q' => 'Carmen']);
        $this->assertCount(1, $bySearch);
        $this->assertSame($idSv2, $bySearch[0]['Idagencia']);

        // Geolocation default: only active SV agencies with valid coordinates (excludes disabled, null coords, GT)
        $geoAll = $this->agenciaModel->findForGeolocation(1);
        $this->assertCount(2, $geoAll);
        $geoIds = array_column($geoAll, 'id');
        $this->assertContains($idSv1, $geoIds);
        $this->assertContains($idSv2, $geoIds);
        $this->assertNotContains($idSvDisabled, $geoIds);

        // Geolocation brand name filter + department filter
        $geoHonda = $this->agenciaModel->findForGeolocation(1, ['marca' => 'honda', 'id_departamento' => 1]);
        $this->assertCount(1, $geoHonda);
        $this->assertSame($idSv1, $geoHonda[0]['id']);
        $this->assertSame(['Bajaj', 'Honda', 'Yamaha'], $geoHonda[0]['marcas']);
        $this->assertSame('El Salvador', $geoHonda[0]['pais']);

        // Include inactive when explicitly requested
        $geoWithInactive = $this->agenciaModel->findForGeolocation(1, ['incluir_inactivas' => '1']);
        $this->assertCount(3, $geoWithInactive);
    }

    public function testCreateWithEmptyOrNonExistentEmployeeRollsBackCleanly(): void
    {
        foreach (['', '   '] as $emptyEmp) {
            $threwEmpty = false;
            try {
                $this->agenciaModel->createWithBrandsAndAudit([
                    'nombre_agencia'  => 'Agencia Sin Empleado',
                    'direccion'       => 'Av. Central',
                    'id_pais'         => 1,
                    'id_departamento' => 1,
                    'id_municipio'    => 1,
                    'id_distrito'     => 1,
                    'latitud'         => 13.7,
                    'longitud'        => -89.2,
                ], [1], $emptyEmp);
            } catch (InvalidArgumentException $e) {
                $threwEmpty = true;
            }
            $this->assertTrue($threwEmpty);
        }

        $threwMissingFk = false;
        try {
            $this->agenciaModel->createWithBrandsAndAudit([
                'nombre_agencia'  => 'Agencia Empleado Inexistente',
                'direccion'       => 'Av. Central',
                'id_pais'         => 1,
                'id_departamento' => 1,
                'id_municipio'    => 1,
                'id_distrito'     => 1,
                'latitud'         => 13.7,
                'longitud'        => -89.2,
            ], [1, 2], 'EMP-NO-EXISTE');
        } catch (\Throwable $e) {
            $threwMissingFk = true;
        }
        $this->assertTrue($threwMissingFk);
        $this->assertSame(0, $this->db->table('Agencias')->countAllResults());
        $this->assertSame(0, $this->db->table('Agencia_Marca')->countAllResults());
        $this->assertSame(0, $this->db->table('Auditoria_Agencias')->countAllResults());

        // Subsequent valid transaction on same DB connection must still succeed
        $validId = $this->agenciaModel->createWithBrandsAndAudit([
            'nombre_agencia'  => 'Agencia Post Rollback',
            'direccion'       => 'Av. Central #2',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 1,
            'latitud'         => 13.7,
            'longitud'        => -89.2,
        ], [1], 'EMP-SV-002');
        $this->assertGreaterThan(0, $validId);
    }

    public function testUpdateAndSoftDisableRollbackWhenAuditWriteFails(): void
    {
        $id = $this->agenciaModel->createWithBrandsAndAudit([
            'nombre_agencia'  => 'Agencia Estable',
            'direccion'       => 'Calle Original #10',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 1,
            'latitud'         => 13.69,
            'longitud'        => -89.21,
        ], [1, 2], 'EMP-SV-002');

        $failingAuditModel = $this->getMockBuilder(AuditoriaAgenciaModel::class)
            ->setConstructorArgs([$this->db])
            ->onlyMethods(['recordEntry'])
            ->getMock();
        $failingAuditModel->method('recordEntry')
            ->willThrowException(new RuntimeException('Simulated audit failure'));

        $this->agenciaModel->setAuditoriaModel($failingAuditModel);

        $threwUpdate = false;
        try {
            $this->agenciaModel->updateWithBrandsAndAudit($id, 1, [
                'nombre_agencia'  => 'Nombre Revertido',
                'direccion'       => 'Dir Revertida',
                'id_pais'         => 1,
                'id_departamento' => 1,
                'id_municipio'    => 2,
                'id_distrito'     => 3,
                'latitud'         => 13.71,
                'longitud'        => -89.15,
            ], [3, 4], 'EMP-SV-002');
        } catch (RuntimeException $e) {
            $threwUpdate = true;
        }
        $this->assertTrue($threwUpdate);

        $afterFailedUpdate = $this->agenciaModel->findByIdAndPais($id, 1);
        $this->assertNotNull($afterFailedUpdate);
        $this->assertSame('Agencia Estable', $afterFailedUpdate['nombre_agencia']);
        $this->assertSame('Calle Original #10', $afterFailedUpdate['direccion']);
        $this->assertSame(1, $afterFailedUpdate['Id_municipio']);
        $this->assertSame(1, $afterFailedUpdate['Id_distrito']);
        $this->assertSame([1, 2], $afterFailedUpdate['marca_ids']);
        $this->assertCount(1, $this->auditoriaModel->findByAgencia($id));

        $threwDisable = false;
        try {
            $this->agenciaModel->softDisableWithAudit($id, 1, 'Cierre fallido', 'EMP-SV-002');
        } catch (RuntimeException $e) {
            $threwDisable = true;
        }
        $this->assertTrue($threwDisable);

        $afterFailedDisable = $this->agenciaModel->findByIdAndPais($id, 1);
        $this->assertNotNull($afterFailedDisable);
        $this->assertSame(1, $afterFailedDisable['activo']);
        $this->assertNull($afterFailedDisable['motivo_deshabilitacion']);
        $this->assertNull($afterFailedDisable['deshabilitado_en']);
        $this->assertNull($afterFailedDisable['deshabilitado_por']);
        $this->assertCount(1, $this->auditoriaModel->findByAgencia($id));
    }

    public function testSearchQueryWithApostrophesLikeWildcardsAndEstadoFilters(): void
    {
        $idApostrophe = $this->agenciaModel->createWithBrandsAndAudit([
            'nombre_agencia'  => "Agencia O'Brien Centro",
            'direccion'       => "Av. D'Artagnan #12",
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 1,
            'latitud'         => 13.691,
            'longitud'        => -89.211,
        ], [1], 'EMP-SV-002');

        $idWildcard = $this->agenciaModel->createWithBrandsAndAudit([
            'nombre_agencia'  => 'Sucursal 100% Real_SV',
            'direccion'       => 'Calle !Especial #5',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 2,
            'latitud'         => 13.715,
            'longitud'        => -89.205,
        ], [2], 'EMP-SV-002');

        $this->agenciaModel->createWithBrandsAndAudit([
            'nombre_agencia'  => 'Sucursal 1000 RealXSV',
            'direccion'       => 'Calle Normal #6',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 1,
            'latitud'         => 13.702,
            'longitud'        => -89.202,
        ], [3], 'EMP-SV-002');

        $idInactive = $this->agenciaModel->createWithBrandsAndAudit([
            'nombre_agencia'  => 'Agencia Deshabilitada SV',
            'direccion'       => 'Zona Inactiva',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 1,
            'latitud'         => 13.709,
            'longitud'        => -89.209,
        ], [1], 'EMP-SV-002');
        $this->agenciaModel->softDisableWithAudit($idInactive, 1, 'Cierre temporal', 'EMP-SV-002');

        // Apostrophe search (mixed case & lowercase)
        $resNameApos = $this->agenciaModel->findAllByPaisWithDetails(1, ['q' => "O'Brien"]);
        $this->assertCount(1, $resNameApos);
        $this->assertSame($idApostrophe, $resNameApos[0]['Idagencia']);
        $this->assertSame(1, $resNameApos[0]['requiere_distrito']);

        $resDirApos = $this->agenciaModel->findAllByPaisWithDetails(1, ['q' => "d'artagnan"]);
        $this->assertCount(1, $resDirApos);
        $this->assertSame($idApostrophe, $resDirApos[0]['Idagencia']);

        // Literal LIKE wildcards (%, _, !) must not act as wildcards
        $resPercent = $this->agenciaModel->findAllByPaisWithDetails(1, ['q' => '100%']);
        $this->assertCount(1, $resPercent);
        $this->assertSame($idWildcard, $resPercent[0]['Idagencia']);

        $resUnderscore = $this->agenciaModel->findAllByPaisWithDetails(1, ['q' => 'Real_SV']);
        $this->assertCount(1, $resUnderscore);
        $this->assertSame($idWildcard, $resUnderscore[0]['Idagencia']);

        $resBang = $this->agenciaModel->findAllByPaisWithDetails(1, ['q' => '!Especial']);
        $this->assertCount(1, $resBang);
        $this->assertSame($idWildcard, $resBang[0]['Idagencia']);

        // Estado filters: inactivas and todas
        $inactivas = $this->agenciaModel->findAllByPaisWithDetails(1, ['estado' => 'inactivas']);
        $this->assertCount(1, $inactivas);
        $this->assertSame($idInactive, $inactivas[0]['Idagencia']);

        $todas = $this->agenciaModel->findAllByPaisWithDetails(1, ['estado' => 'todas']);
        $this->assertCount(4, $todas);
    }

    public function testGeolocationExcludesZeroZeroAndOutOfRangeDbRowsAndSupportsMuniDistFilters(): void
    {
        $idDist1 = $this->agenciaModel->createWithBrandsAndAudit([
            'nombre_agencia'  => 'SV Dist 1',
            'direccion'       => 'Dir 1',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 1,
            'latitud'         => 13.698,
            'longitud'        => -89.191,
        ], [1], 'EMP-SV-002');

        $idDist2 = $this->agenciaModel->createWithBrandsAndAudit([
            'nombre_agencia'  => 'SV Dist 2',
            'direccion'       => 'Dir 2',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 2,
            'latitud'         => 13.725,
            'longitud'        => -89.203,
        ], [2], 'EMP-SV-002');

        $idOtherMuni = $this->agenciaModel->createWithBrandsAndAudit([
            'nombre_agencia'  => 'SV Santa Tecla Geo',
            'direccion'       => 'Dir 3',
            'id_pais'         => 1,
            'id_departamento' => 2,
            'id_municipio'    => 3,
            'id_distrito'     => 5,
            'latitud'         => 13.676,
            'longitud'        => -89.279,
        ], [1], 'EMP-SV-002');

        // Insert invalid (0,0) and out-of-range coordinates directly into DB
        $invalidRows = [
            ['nombre_agencia' => 'Zero Zero', 'latitud' => 0.0, 'longitud' => 0.0],
            ['nombre_agencia' => 'Out of range Lat', 'latitud' => 91.5, 'longitud' => -89.2],
            ['nombre_agencia' => 'Out of range Lng', 'latitud' => 13.7, 'longitud' => -181.2],
        ];
        foreach ($invalidRows as $bad) {
            $this->db->table('Agencias')->insert([
                'nombre_agencia'  => $bad['nombre_agencia'],
                'direccion'       => 'Bad Coords',
                'Id_pais'         => 1,
                'Id_departamento' => 1,
                'Id_municipio'    => 1,
                'Id_distrito'     => 1,
                'latitud'         => $bad['latitud'],
                'longitud'        => $bad['longitud'],
                'activo'          => 1,
            ]);
        }

        $allValid = $this->agenciaModel->findForGeolocation(1);
        $this->assertCount(3, $allValid);
        $allIds = array_column($allValid, 'id');
        $this->assertContains($idDist1, $allIds);
        $this->assertContains($idDist2, $allIds);
        $this->assertContains($idOtherMuni, $allIds);

        $byMuni = $this->agenciaModel->findForGeolocation(1, ['id_municipio' => 1]);
        $this->assertCount(2, $byMuni);
        $muniIds = array_column($byMuni, 'id');
        $this->assertContains($idDist1, $muniIds);
        $this->assertContains($idDist2, $muniIds);

        $byDist = $this->agenciaModel->findForGeolocation(1, ['id_municipio' => 1, 'id_distrito' => 2]);
        $this->assertCount(1, $byDist);
        $this->assertSame($idDist2, $byDist[0]['id']);
    }

    public function testSyncBrandsFailureRollsBackPartialUpdateAndDeletedBrands(): void
    {
        $model = new class ($this->db) extends AgenciaModel {
            public bool $forceBadBrandInSync = false;

            protected function syncBrands(int $idAgencia, array $cleanMarcaIds): void
            {
                if ($this->forceBadBrandInSync) {
                    parent::syncBrands($idAgencia, [999999]);

                    return;
                }

                parent::syncBrands($idAgencia, $cleanMarcaIds);
            }
        };

        $id = $model->createWithBrandsAndAudit([
            'nombre_agencia'  => 'Agencia Pivot Atomica',
            'direccion'       => 'Calle Original #7',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 1,
            'latitud'         => 13.69,
            'longitud'        => -89.21,
        ], [1, 2], 'EMP-SV-002');

        $model->forceBadBrandInSync = true;

        // Case 1: DBDebug = true (default)
        $threwDebugOn = false;
        try {
            $model->updateWithBrandsAndAudit($id, 1, [
                'nombre_agencia'  => 'Agencia Corrupta 1',
                'direccion'       => 'Calle Corrupta 1',
                'id_pais'         => 1,
                'id_departamento' => 1,
                'id_municipio'    => 1,
                'id_distrito'     => 1,
                'latitud'         => 13.70,
                'longitud'        => -89.20,
            ], [3], 'EMP-SV-002');
        } catch (\Throwable $e) {
            $threwDebugOn = true;
        }
        $this->assertTrue($threwDebugOn);

        $after1 = $this->agenciaModel->findByIdAndPais($id, 1);
        $this->assertNotNull($after1);
        $this->assertSame('Agencia Pivot Atomica', $after1['nombre_agencia']);
        $this->assertSame([1, 2], $after1['marca_ids']);

        // Case 2: DBDebug = false (simulates production silent query failure setting transStatus = false)
        $dbDebugProp = new \ReflectionProperty(\CodeIgniter\Database\BaseConnection::class, 'DBDebug');
        $dbDebugProp->setAccessible(true);
        $prevDbDebug = $dbDebugProp->getValue($this->db);
        $dbDebugProp->setValue($this->db, false);

        $threwDebugOff = false;
        try {
            $model->updateWithBrandsAndAudit($id, 1, [
                'nombre_agencia'  => 'Agencia Corrupta 2',
                'direccion'       => 'Calle Corrupta 2',
                'id_pais'         => 1,
                'id_departamento' => 1,
                'id_municipio'    => 1,
                'id_distrito'     => 1,
                'latitud'         => 13.70,
                'longitud'        => -89.20,
            ], [3], 'EMP-SV-002');
        } catch (\Throwable $e) {
            $threwDebugOff = true;
        } finally {
            $dbDebugProp->setValue($this->db, $prevDbDebug);
        }
        $this->assertTrue($threwDebugOff);

        $after2 = $this->agenciaModel->findByIdAndPais($id, 1);
        $this->assertNotNull($after2);
        $this->assertSame('Agencia Pivot Atomica', $after2['nombre_agencia']);
        $this->assertSame([1, 2], $after2['marca_ids']);
        $this->assertCount(1, $this->auditoriaModel->findByAgencia($id));
    }

    public function testCreateAndUpdateRejectStrictInvalidMarcaIdsItems(): void
    {
        $invalidItems = ['abc', true, '1abc', 1.9, 0, -1, ['nested']];

        foreach ($invalidItems as $badItem) {
            foreach ([[$badItem], [1, $badItem]] as $marcaIds) {
                $threwCreate = false;
                try {
                    $this->agenciaModel->createWithBrandsAndAudit([
                        'nombre_agencia'  => 'Agencia Marca Invalida',
                        'direccion'       => 'Calle Test #1',
                        'id_pais'         => 1,
                        'id_departamento' => 1,
                        'id_municipio'    => 1,
                        'id_distrito'     => 1,
                        'latitud'         => 13.7,
                        'longitud'        => -89.2,
                    ], $marcaIds, 'EMP-SV-002');
                } catch (InvalidArgumentException $e) {
                    $threwCreate = true;
                }
                $this->assertTrue($threwCreate, 'Expected InvalidArgumentException on create for marcaIds: ' . var_export($marcaIds, true));
            }
        }

        $this->assertSame(0, $this->db->table('Agencias')->countAllResults());
        $this->assertSame(0, $this->db->table('Agencia_Marca')->countAllResults());

        // Valid creation with string integer with surrounding spaces (' 2 ') succeeds, then invalid update is rejected
        $id = $this->agenciaModel->createWithBrandsAndAudit([
            'nombre_agencia'  => 'Agencia Marca Valida',
            'direccion'       => 'Calle Test #2',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 1,
            'latitud'         => 13.7,
            'longitud'        => -89.2,
        ], [' 2 ', 1], 'EMP-SV-002');

        foreach ($invalidItems as $badItem) {
            $threwUpdate = false;
            try {
                $this->agenciaModel->updateWithBrandsAndAudit($id, 1, [
                    'nombre_agencia'  => 'Agencia Marca Valida',
                    'direccion'       => 'Calle Test #2',
                    'id_pais'         => 1,
                    'id_departamento' => 1,
                    'id_municipio'    => 1,
                    'id_distrito'     => 1,
                    'latitud'         => 13.7,
                    'longitud'        => -89.2,
                ], [1, $badItem], 'EMP-SV-002');
            } catch (InvalidArgumentException $e) {
                $threwUpdate = true;
            }
            $this->assertTrue($threwUpdate, 'Expected InvalidArgumentException on update for badItem: ' . var_export($badItem, true));
        }

        $saved = $this->agenciaModel->findByIdAndPais($id, 1);
        $this->assertNotNull($saved);
        $this->assertSame([1, 2], $saved['marca_ids']);
    }

    public function testSoftDisableWithNonExistentEmployeeFkFailureUnderDbDebugFalseAndTrue(): void
    {
        $id = $this->agenciaModel->createWithBrandsAndAudit([
            'nombre_agencia'  => 'Agencia FK SoftDisable',
            'direccion'       => 'Calle Prueba #11',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 1,
            'latitud'         => 13.69,
            'longitud'        => -89.21,
        ], [1], 'EMP-SV-002');

        // Empty/whitespace employee rejected before DB write
        foreach (['', '   '] as $emptyEmp) {
            $threwEmpty = false;
            try {
                $this->agenciaModel->softDisableWithAudit($id, 1, 'Cierre prueba', $emptyEmp);
            } catch (InvalidArgumentException $e) {
                $threwEmpty = true;
            }
            $this->assertTrue($threwEmpty);
        }

        // Case 1: Non-existent employee FK violation with DBDebug = true
        $threwDebugOn = false;
        try {
            $this->agenciaModel->softDisableWithAudit($id, 1, 'Cierre FK debug on', 'EMP-NO-EXISTE');
        } catch (\Throwable $e) {
            $threwDebugOn = true;
        }
        $this->assertTrue($threwDebugOn);

        $after1 = $this->agenciaModel->findByIdAndPais($id, 1);
        $this->assertNotNull($after1);
        $this->assertSame(1, $after1['activo']);
        $this->assertNull($after1['motivo_deshabilitacion']);
        $this->assertNull($after1['deshabilitado_en']);
        $this->assertNull($after1['deshabilitado_por']);
        $this->assertCount(1, $this->auditoriaModel->findByAgencia($id));

        // Case 2: Non-existent employee FK violation with DBDebug = false (triggers update() === false + transStatus = false)
        $dbDebugProp = new \ReflectionProperty(\CodeIgniter\Database\BaseConnection::class, 'DBDebug');
        $dbDebugProp->setAccessible(true);
        $prevDbDebug = $dbDebugProp->getValue($this->db);
        $dbDebugProp->setValue($this->db, false);

        $threwDebugOff = false;
        try {
            $this->agenciaModel->softDisableWithAudit($id, 1, 'Cierre FK debug off', 'EMP-NO-EXISTE');
        } catch (RuntimeException $e) {
            $threwDebugOff = true;
        } finally {
            $dbDebugProp->setValue($this->db, $prevDbDebug);
        }
        $this->assertTrue($threwDebugOff);

        $after2 = $this->agenciaModel->findByIdAndPais($id, 1);
        $this->assertNotNull($after2);
        $this->assertSame(1, $after2['activo']);
        $this->assertNull($after2['motivo_deshabilitacion']);
        $this->assertNull($after2['deshabilitado_en']);
        $this->assertNull($after2['deshabilitado_por']);
        $this->assertCount(1, $this->auditoriaModel->findByAgencia($id));

        // Subsequent valid soft-disable on same connection must succeed (resetTransStatus verified)
        $disabled = $this->agenciaModel->softDisableWithAudit($id, 1, 'Cierre válido post-rollback', 'EMP-SV-002');
        $this->assertSame(0, $disabled['activo']);
        $this->assertSame('EMP-SV-002', $disabled['deshabilitado_por']);
        $this->assertCount(2, $this->auditoriaModel->findByAgencia($id));
    }

    public function testPostgreConnectionWithNonEmptyPrefixCompiledSqlForSearchAndGeolocation(): void
    {
        $pgDb = new class (['DBPrefix' => 'cr_']) extends \CodeIgniter\Database\Postgre\Connection {
            /** @var list<string> */
            public array $capturedSql = [];

            public function __construct(array $params)
            {
                parent::__construct($params);
                $this->connID  = true;
                $this->pretend = true;
            }

            public function initialize()
            {
                $this->connID = true;
            }

            public function table($tableName)
            {
                if (in_array($tableName, ['', '0', []], true)) {
                    throw new \CodeIgniter\Database\Exceptions\DatabaseException('You must set the database table to be used with your query.');
                }

                return new \CodeIgniter\Database\Postgre\Builder($tableName, $this);
            }

            public function escape($str)
            {
                if ($str instanceof \Stringable) {
                    if ($str instanceof \CodeIgniter\Database\RawSql) {
                        return $str->__toString();
                    }
                    $str = (string) $str;
                }

                if (is_string($str)) {
                    return "'" . str_replace("'", "''", $str) . "'";
                }

                if (is_bool($str)) {
                    return $str ? 'TRUE' : 'FALSE';
                }

                return parent::escape($str);
            }

            protected function _escapeString(string $str): string
            {
                return str_replace("'", "''", $str);
            }

            public function query(string $sql, $binds = null, bool $setEscapeFlags = true, string $queryClass = '')
            {
                /** @var \CodeIgniter\Database\Query $query */
                $query               = parent::query($sql, $binds, $setEscapeFlags, $queryClass);
                $this->capturedSql[] = $query->getQuery();

                $conn   = $this->connID;
                $result = false;

                return new class ($conn, $result) extends \CodeIgniter\Database\Postgre\Result {
                    public function getResultArray(): array
                    {
                        return [];
                    }

                    public function freeResult()
                    {
                    }
                };
            }
        };

        $pgAgenciaModel = new AgenciaModel($pgDb);

        $pgAgenciaModel->findAllByPaisWithDetails(1, [
            'estado'   => 'activas',
            'id_marca' => 2,
            'q'        => "O'Brien 100%_!Test",
        ]);

        $this->assertCount(1, $pgDb->capturedSql);
        $searchSql = $pgDb->capturedSql[0];
        $this->assertStringContainsString('FROM "cr_agencias"', $searchSql);
        $this->assertStringContainsString('JOIN "cr_pais"', $searchSql);
        $this->assertStringContainsString('JOIN "cr_departamento"', $searchSql);
        $this->assertStringContainsString('JOIN "cr_municipio"', $searchSql);
        $this->assertStringContainsString('LEFT JOIN "cr_distrito"', $searchSql);
        $this->assertStringContainsString('"cr_agencias"."id_pais" = 1', $searchSql);
        $this->assertStringContainsString('"cr_agencias"."activo" = 1', $searchSql);
        $this->assertStringContainsString(
            'EXISTS (SELECT 1 FROM "cr_agencia_marca" am INNER JOIN "cr_marca" m ON m."id_marca" = am."id_marca" WHERE am."idagencia" = "cr_agencias"."idagencia" AND am."id_marca" = 2)',
            $searchSql
        );
        $this->assertStringContainsString(
            'LOWER("cr_agencias"."nombre_agencia") LIKE \'%o\'\'brien 100!%!_!!test%\' ESCAPE \'!\'',
            $searchSql
        );
        $this->assertStringContainsString(
            'LOWER("cr_agencias"."direccion") LIKE \'%o\'\'brien 100!%!_!!test%\' ESCAPE \'!\'',
            $searchSql
        );

        $pgAgenciaModel->findForGeolocation(1, [
            'marca'           => "Honda's",
            'id_departamento' => 1,
            'id_municipio'    => 2,
            'id_distrito'     => 3,
        ]);

        $this->assertCount(2, $pgDb->capturedSql);
        $geoSql = $pgDb->capturedSql[1];
        $this->assertStringContainsString('FROM "cr_agencias"', $geoSql);
        $this->assertStringContainsString('"cr_agencias"."latitud" IS NOT NULL', $geoSql);
        $this->assertStringContainsString('"cr_agencias"."longitud" IS NOT NULL', $geoSql);
        $this->assertStringContainsString('"cr_agencias"."latitud" >= -90 AND "cr_agencias"."latitud" <= 90', $geoSql);
        $this->assertStringContainsString('"cr_agencias"."longitud" >= -180 AND "cr_agencias"."longitud" <= 180', $geoSql);
        $this->assertStringContainsString('NOT ("cr_agencias"."latitud" = 0 AND "cr_agencias"."longitud" = 0)', $geoSql);
        $this->assertStringContainsString('"cr_agencias"."id_departamento" = 1', $geoSql);
        $this->assertStringContainsString('"cr_agencias"."id_municipio" = 2', $geoSql);
        $this->assertStringContainsString('"cr_agencias"."id_distrito" = 3', $geoSql);
        $this->assertStringContainsString(
            'EXISTS (SELECT 1 FROM "cr_agencia_marca" am INNER JOIN "cr_marca" m ON m."id_marca" = am."id_marca" WHERE am."idagencia" = "cr_agencias"."idagencia" AND LOWER(m."marca") = \'honda\'\'s\')',
            $geoSql
        );

        // Verify empty likeEscapeChar falls back to '!' cleanly
        $pgDb->likeEscapeChar = '';
        $pgAgenciaModel->findAllByPaisWithDetails(1, ['q' => '100%_test']);
        $this->assertCount(3, $pgDb->capturedSql);
        $this->assertStringContainsString('ESCAPE \'!\'', $pgDb->capturedSql[2]);
        $this->assertStringContainsString('\'%100!%!_test%\'', $pgDb->capturedSql[2]);
    }
}
