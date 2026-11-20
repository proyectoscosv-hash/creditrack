<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AgenciaModel;
use App\Models\AuditoriaAgenciaModel;
use CodeIgniter\HTTP\Response;
use Config\Services;
use Tests\Support\AuthTestCase;

/**
 * Feature tests for Milestone M3 (Agency CRUD, Soft-Disable & Audit Trail):
 * - Consultation list with filtering (estado, id_marca, id_departamento, q)
 * - Registration form & transactional agency creation with M:N brands
 * - Guatemala optional district vs El Salvador mandatory district
 * - View agency detail with audit trail
 * - Update agency with brands synchronization and immutability of country
 * - Logical (soft) disable requiring motivo_deshabilitacion
 * - Anti-spoofing and audit trail logging in Auditoria_Agencias
 * - Territorial isolation (403 on cross-country access/mutation)
 */
final class AgenciaCrudTest extends AuthTestCase
{
    protected array $supervisorSvSession;
    protected array $supervisorGtSession;

    protected function setUp(): void
    {
        parent::setUp();
        $_GET     = [];
        $_POST    = [];
        $_REQUEST = [];
        if (class_exists(Services::class)) {
            service('superglobals')->setGlobalArray('post', []);
            service('superglobals')->setGlobalArray('get', []);
        }
        $_SESSION          = [];
        $this->headers     = [];
        $this->bodyFormat  = '';
        $this->requestBody = '';
        Services::injectMock('response', new Response(config(\Config\App::class)));

        $this->supervisorSvSession = [
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-SV-002',
            'usuario'         => 'supervisor_sv',
            'nombre'          => 'Elena Supervisora SV',
            'rol'             => 'Supervisor',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
            'login_time'      => time(),
        ];

        $this->supervisorGtSession = [
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-GT-002',
            'usuario'         => 'supervisor_gt',
            'nombre'          => 'Carlos Supervisor GT',
            'rol'             => 'Supervisor',
            'id_pais'         => 2,
            'pais_nombre'     => 'Guatemala',
            'login_time'      => time(),
        ];
    }

    protected function tearDown(): void
    {
        $_GET     = [];
        $_POST    = [];
        $_REQUEST = [];
        if (class_exists(Services::class)) {
            service('superglobals')->setGlobalArray('post', []);
            service('superglobals')->setGlobalArray('get', []);
        }
        $this->routes      = null;
        $this->headers     = [];
        $this->bodyFormat  = '';
        $this->requestBody = '';
        service('routes')->resetRoutes();
        service('routes')->loadRoutes();
        Services::injectMock('response', new Response(config(\Config\App::class)));
        $_SESSION = [];
        parent::tearDown();
    }

    /**
     * Helper to create a test agency directly via model
     */
    protected function createTestAgency(array $overrides = []): int
    {
        $agenciaModel = model(AgenciaModel::class);
        $data = array_merge([
            'nombre_agencia'  => 'Agencia Test ' . uniqid(),
            'direccion'       => 'Calle Test #123',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 1,
            'latitud'         => 13.69294000,
            'longitud'        => -89.21819000,
        ], $overrides);

        $marcas = $overrides['marcas'] ?? [1, 2];
        $codigo = $overrides['codigo_empleado'] ?? 'EMP-SV-002';

        return $agenciaModel->createWithBrandsAndAudit($data, $marcas, $codigo);
    }

    public function testSupervisorCanAccessAgencyList(): void
    {
        $this->createTestAgency(['nombre_agencia' => 'Agencia SV Visible']);

        $res = $this->withSession($this->supervisorSvSession)->get('agencias');
        $res->assertStatus(200);
        $body = $res->getBody();
        $this->assertStringContainsString('Agencia SV Visible', $body);
        $this->assertStringContainsString('Gestión de agencias', $body);
        $this->assertCorporatePalettePresent($body);
    }

    public function testAgencyListFiltersByEstadoActivasInactivasTodas(): void
    {
        $idActiva = $this->createTestAgency(['nombre_agencia' => 'Agencia Activa Filter']);
        $idInactiva = $this->createTestAgency(['nombre_agencia' => 'Agencia Inactiva Filter']);

        model(AgenciaModel::class)->softDisableWithAudit($idInactiva, 1, 'Motivo prueba filtro', 'EMP-SV-002');

        // Estado: activas
        $resActivas = $this->withSession($this->supervisorSvSession)->get('agencias?estado=activas');
        $resActivas->assertStatus(200);
        $this->assertStringContainsString('Agencia Activa Filter', $resActivas->getBody());
        $this->assertStringNotContainsString('Agencia Inactiva Filter', $resActivas->getBody());

        // Estado: inactivas
        $resInactivas = $this->withSession($this->supervisorSvSession)->get('agencias?estado=inactivas');
        $resInactivas->assertStatus(200);
        $this->assertStringNotContainsString('Agencia Activa Filter', $resInactivas->getBody());
        $this->assertStringContainsString('Agencia Inactiva Filter', $resInactivas->getBody());

        // Estado: todas
        $resTodas = $this->withSession($this->supervisorSvSession)->get('agencias?estado=todas');
        $resTodas->assertStatus(200);
        $this->assertStringContainsString('Agencia Activa Filter', $resTodas->getBody());
        $this->assertStringContainsString('Agencia Inactiva Filter', $resTodas->getBody());
    }

    public function testAgencyListFiltersByBrandAndDepartment(): void
    {
        $this->createTestAgency([
            'nombre_agencia'  => 'Agencia Honda SS',
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 1,
            'marcas'          => [1], // Honda
        ]);
        $this->createTestAgency([
            'nombre_agencia'  => 'Agencia Yamaha Santa Ana',
            'id_departamento' => 3,
            'id_municipio'    => 6,
            'id_distrito'     => 8,
            'marcas'          => [2], // Yamaha
        ]);

        $res = $this->withSession($this->supervisorSvSession)->get('agencias?id_departamento=1&id_marca=1');
        $res->assertStatus(200);
        $body = $res->getBody();
        $this->assertStringContainsString('Agencia Honda SS', $body);
        $this->assertStringNotContainsString('Agencia Yamaha Santa Ana', $body);
    }

    public function testAgencyListSearchesByNameAndAddress(): void
    {
        $this->createTestAgency([
            'nombre_agencia' => 'Moto Repuestos Santa Tecla',
            'direccion'      => 'Avenida Panamericana #45',
        ]);

        // Search by name
        $resName = $this->withSession($this->supervisorSvSession)->get('agencias?q=Santa+Tecla');
        $resName->assertStatus(200);
        $this->assertStringContainsString('Moto Repuestos Santa Tecla', $resName->getBody());

        // Search by address
        $resDir = $this->withSession($this->supervisorSvSession)->get('agencias?q=Panamericana');
        $resDir->assertStatus(200);
        $this->assertStringContainsString('Moto Repuestos Santa Tecla', $resDir->getBody());
    }

    public function testAgencyListSearchSqlInjectionSafe(): void
    {
        $this->createTestAgency(['nombre_agencia' => 'Agencia Real']);

        $res = $this->withSession($this->supervisorSvSession)->get("agencias?q=' OR '1'='1");
        $res->assertStatus(200);
        $this->assertStringNotContainsString('Agencia Real', $res->getBody());
    }

    public function testNonSupervisorCannotAccessAgencyList(): void
    {
        $adminSession = [
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ADM-001',
            'usuario'         => 'admin_sv',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ];

        $res = $this->withSession($adminSession)->get('agencias');
        $res->assertStatus(403);
    }

    public function testSupervisorCanAccessCreateForm(): void
    {
        $res = $this->withSession($this->supervisorSvSession)->get('agencias/crear');
        $res->assertStatus(200);
        $body = $res->getBody();
        $this->assertStringContainsString('name="nombre_agencia"', $body);
        $this->assertStringContainsString('name="direccion"', $body);
        $this->assertStringContainsString('name="id_pais"', $body);
        $this->assertStringContainsString('name="id_departamento"', $body);
        $this->assertStringContainsString('name="id_municipio"', $body);
        $this->assertStringContainsString('name="id_distrito"', $body);
        $this->assertStringContainsString('name="latitud"', $body);
        $this->assertStringContainsString('name="longitud"', $body);
        $this->assertStringContainsString('name="marcas[]"', $body);
        $this->assertCorporatePalettePresent($body);
    }

    public function testCreateAgencySuccessSingleBrand(): void
    {
        $name = 'Agencia Creada Single ' . uniqid();
        $payload = [
            'nombre_agencia'  => $name,
            'direccion'       => 'Paseo General Escalón #100',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 1,
            'latitud'         => '13.70123450',
            'longitud'        => '-89.22456780',
            'marcas'          => [1],
        ];

        $res = $this->withSession($this->supervisorSvSession)->post('agencias/guardar', $payload);
        $res->assertStatus(302);

        $row = model(AgenciaModel::class)->where('nombre_agencia', $name)->first();
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row['activo']);
        $this->assertEqualsWithDelta(13.7012345, (float) $row['latitud'], 0.00001);
        $this->assertEqualsWithDelta(-89.2245678, (float) $row['longitud'], 0.00001);
    }

    public function testCreateAgencySuccessMultipleBrands(): void
    {
        $name = 'Agencia Multi Marca ' . uniqid();
        $payload = [
            'nombre_agencia'  => $name,
            'direccion'       => 'Boulevard Los Próceres #200',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 1,
            'latitud'         => '13.68900000',
            'longitud'        => '-89.23100000',
            'marcas'          => [1, 2, 3], // Honda, Yamaha, Suzuki
        ];

        $res = $this->withSession($this->supervisorSvSession)->post('agencias/guardar', $payload);
        $res->assertStatus(302);

        $row = model(AgenciaModel::class)->where('nombre_agencia', $name)->first();
        $this->assertNotNull($row);

        $db = \Config\Database::connect('tests');
        $pivots = $db->table('Agencia_Marca')->where('Idagencia', $row['Idagencia'])->get()->getResultArray();
        $this->assertCount(3, $pivots);
    }

    public function testCreateAgencyGuatemalaWithoutDistrict(): void
    {
        $name = 'Agencia GT Mixco ' . uniqid();
        $payload = [
            'nombre_agencia'  => $name,
            'direccion'       => 'Calzada Roosevelt Km 14',
            'id_pais'         => 2,
            'id_departamento' => 5, // Guatemala
            'id_municipio'    => 8, // Mixco
            'id_distrito'     => '', // District empty for GT
            'latitud'         => '14.63491500',
            'longitud'        => '-90.50688200',
            'marcas'          => [1],
        ];

        $res = $this->withSession($this->supervisorGtSession)->post('agencias/guardar', $payload);
        $res->assertStatus(302);

        $row = model(AgenciaModel::class)->where('nombre_agencia', $name)->first();
        $this->assertNotNull($row);
        $this->assertNull($row['id_distrito'] ?? $row['Id_distrito'] ?? null);
    }

    public function testCreateAgencyElSalvadorRequiresDistrict(): void
    {
        $payload = [
            'nombre_agencia'  => 'Agencia SV Sin Distrito',
            'direccion'       => 'Av. Roosevelt',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => '', // Missing in SV!
            'latitud'         => '13.69294000',
            'longitud'        => '-89.21819000',
            'marcas'          => [1],
        ];

        $res = $this->withSession($this->supervisorSvSession)->post('agencias/guardar', $payload);
        $res->assertStatus(302);
        $res->assertSessionHas('error');

        $row = model(AgenciaModel::class)->where('nombre_agencia', 'Agencia SV Sin Distrito')->first();
        $this->assertNull($row);
    }

    public function testPostAgenciasAliasCreatesAgency(): void
    {
        $name = 'Agencia Alias ' . uniqid();
        $payload = [
            'nombre_agencia'  => $name,
            'direccion'       => 'Calle El Mirador #50',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 1,
            'latitud'         => '13.71000000',
            'longitud'        => '-89.24000000',
            'marcas'          => [1],
        ];

        $res = $this->withSession($this->supervisorSvSession)->post('agencias', $payload);
        $res->assertStatus(302);

        $row = model(AgenciaModel::class)->where('nombre_agencia', $name)->first();
        $this->assertNotNull($row);
    }

    public function testCreateAgencyValidationRejectsEmptyBrands(): void
    {
        $payload = [
            'nombre_agencia'  => 'Agencia Sin Marcas',
            'direccion'       => 'Calle Central #1',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 1,
            'latitud'         => '13.69294000',
            'longitud'        => '-89.21819000',
            'marcas'          => [],
        ];

        $res = $this->withSession($this->supervisorSvSession)->post('agencias/guardar', $payload);
        $res->assertStatus(302);
        $res->assertSessionHas('error');
    }

    public function testCreateAgencyValidationRejectsInvalidCoordinates(): void
    {
        $payload = [
            'nombre_agencia'  => 'Agencia Mala Coordenada',
            'direccion'       => 'Calle Central #1',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 1,
            'latitud'         => '95.00000000', // Invalid: > 90
            'longitud'        => '-89.21819000',
            'marcas'          => [1],
        ];

        $res = $this->withSession($this->supervisorSvSession)->post('agencias/guardar', $payload);
        $res->assertStatus(302);
        $res->assertSessionHas('error');
    }

    public function testCreateAgencyCrossCountryAttemptReturns403(): void
    {
        $payload = [
            'nombre_agencia'  => 'Agencia SV intentando GT',
            'direccion'       => 'Zona 10, Ciudad de Guatemala',
            'id_pais'         => 2, // Guatemala while logged in as SV supervisor!
            'id_departamento' => 5,
            'id_municipio'    => 7,
            'latitud'         => '14.60000000',
            'longitud'        => '-90.50000000',
            'marcas'          => [1],
        ];

        $res = $this->withSession($this->supervisorSvSession)->post('agencias/guardar', $payload);
        $res->assertStatus(403);
    }

    public function testCreateAgencyRecordsAuditCrear(): void
    {
        $name = 'Agencia Audit Crear ' . uniqid();
        $payload = [
            'nombre_agencia'  => $name,
            'direccion'       => 'Av. Las Magnolias #500',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 1,
            'latitud'         => '13.69500000',
            'longitud'        => '-89.22000000',
            'marcas'          => [1],
        ];

        $this->withSession($this->supervisorSvSession)->post('agencias/guardar', $payload);

        $row = model(AgenciaModel::class)->where('nombre_agencia', $name)->first();
        $this->assertNotNull($row);

        $audit = model(AuditoriaAgenciaModel::class)->findByAgencia((int) $row['Idagencia']);
        $this->assertNotEmpty($audit);
        $this->assertSame('CREAR', $audit[0]['tipo_operacion']);
        $this->assertSame('EMP-SV-002', $audit[0]['codigo_empleado']);
    }

    public function testViewAgencyDetailSuccess(): void
    {
        $id = $this->createTestAgency(['nombre_agencia' => 'Agencia Detalle Test']);

        $res = $this->withSession($this->supervisorSvSession)->get("agencias/ver/{$id}");
        $res->assertStatus(200);
        $body = $res->getBody();
        $this->assertStringContainsString('Agencia Detalle Test', $body);
        $this->assertStringContainsString('Trazabilidad de Auditoría', $body);
        $this->assertCorporatePalettePresent($body);
    }

    public function testViewAgencyDetailCrossCountryReturns403(): void
    {
        $idSv = $this->createTestAgency(['nombre_agencia' => 'Agencia SV Privada']);

        // Guatemala supervisor tries to view El Salvador agency
        $res = $this->withSession($this->supervisorGtSession)->get("agencias/ver/{$idSv}");
        $res->assertStatus(403);
    }

    public function testViewNonExistentAgencyReturns404(): void
    {
        $res = $this->withSession($this->supervisorSvSession)->get('agencias/ver/999999');
        $res->assertStatus(404);
    }

    public function testUpdateAgencySuccess(): void
    {
        $id = $this->createTestAgency(['nombre_agencia' => 'Agencia Pre Update']);

        $updatePayload = [
            'nombre_agencia'  => 'Agencia Post Update',
            'direccion'       => 'Nueva Direccion #999',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 1,
            'latitud'         => '13.72000000',
            'longitud'        => '-89.23000000',
            'marcas'          => [3, 4], // Replaced brands
        ];

        $res = $this->withSession($this->supervisorSvSession)->post("agencias/actualizar/{$id}", $updatePayload);
        $res->assertStatus(302);

        $row = model(AgenciaModel::class)->find($id);
        $this->assertSame('Agencia Post Update', $row['nombre_agencia']);
        $this->assertSame('Nueva Direccion #999', $row['direccion']);

        // Check updated audit trail
        $audit = model(AuditoriaAgenciaModel::class)->findByAgencia($id);
        $this->assertGreaterThanOrEqual(2, count($audit));
        $this->assertSame('ACTUALIZAR', $audit[0]['tipo_operacion']);
    }

    public function testUpdateAgencyCrossCountryAttemptReturns403(): void
    {
        $idSv = $this->createTestAgency();

        // GT supervisor tries to update SV agency
        $res = $this->withSession($this->supervisorGtSession)->post("agencias/actualizar/{$idSv}", [
            'nombre_agencia' => 'Hack Attempt',
        ]);
        $res->assertStatus(403);
    }

    public function testSoftDisableAgencySuccess(): void
    {
        $id = $this->createTestAgency(['nombre_agencia' => 'Agencia Por Deshabilitar']);

        $res = $this->withSession($this->supervisorSvSession)->post("agencias/deshabilitar/{$id}", [
            'motivo_deshabilitacion' => 'Cierre programado por reestructuracion',
        ]);
        $res->assertStatus(302);

        $row = model(AgenciaModel::class)->find($id);
        $this->assertSame(0, (int) $row['activo']);
        $this->assertSame('Cierre programado por reestructuracion', $row['motivo_deshabilitacion']);
        $this->assertSame('EMP-SV-002', $row['deshabilitado_por']);

        // Audit entry recorded
        $audit = model(AuditoriaAgenciaModel::class)->findByAgencia($id);
        $this->assertSame('DESHABILITAR', $audit[0]['tipo_operacion']);
    }

    public function testSoftDisableRequiresMotivo(): void
    {
        $id = $this->createTestAgency(['nombre_agencia' => 'Agencia Disable Sin Motivo']);

        $res = $this->withSession($this->supervisorSvSession)->post("agencias/deshabilitar/{$id}", [
            'motivo_deshabilitacion' => '   ',
        ]);
        $res->assertStatus(302);
        $res->assertSessionHas('error');

        $row = model(AgenciaModel::class)->find($id);
        $this->assertSame(1, (int) $row['activo']);
    }

    public function testSoftDisableCrossCountryReturns403(): void
    {
        $idSv = $this->createTestAgency();

        $res = $this->withSession($this->supervisorGtSession)->post("agencias/deshabilitar/{$idSv}", [
            'motivo_deshabilitacion' => 'Intento cross-country',
        ]);
        $res->assertStatus(403);
    }

    public function testPhysicalDeleteDisallowedOnAgencyRoutes(): void
    {
        $id = $this->createTestAgency();

        $res = $this->withSession($this->supervisorSvSession)->delete("agencias/{$id}");
        $this->assertContains($res->response()->getStatusCode(), [404, 405]);

        $row = model(AgenciaModel::class)->find($id);
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row['activo']);
    }
}
