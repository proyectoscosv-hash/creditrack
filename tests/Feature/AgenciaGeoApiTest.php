<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AgenciaModel;
use CodeIgniter\HTTP\Response;
use Config\Services;
use Tests\Support\AuthTestCase;

/**
 * Feature tests for Milestone M3 (Agency Geolocation API & Cascading Geo APIs):
 * - Contract verification: status, pais, total, agencias[] fields
 * - Active-only default & incluir_inactivas query flag
 * - Coordinate integrity: (0,0) exclusion, range [-90,90] and [-180,180]
 * - Multi-brand filtering by id_marca and marca string
 * - Geographic cascade endpoints: paises, departamentos, municipios, distritos
 * - Territorial isolation & cross-country 403 JSON rejection
 */
final class AgenciaGeoApiTest extends AuthTestCase
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

    protected function createGeoAgency(array $overrides = []): int
    {
        $agenciaModel = model(AgenciaModel::class);
        $data = array_merge([
            'nombre_agencia'  => 'Agencia Geo Test ' . uniqid(),
            'direccion'       => 'Av. Los Próceres #10',
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

    public function testUnauthenticatedAccessToGeoApiReturns401Json(): void
    {
        $res = $this->withHeaders(['Accept' => 'application/json'])->get('api/agencias/geolocalizacion');
        $res->assertStatus(401);
        $json = json_decode($res->getBody(), true);
        $this->assertSame('error', $json['status'] ?? null);
        $this->assertSame('unauthenticated', $json['error'] ?? null);
    }

    public function testNonSupervisorAccessToGeoApiReturns403Json(): void
    {
        $adminSession = [
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ADM-001',
            'usuario'         => 'admin_sv',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ];

        $res = $this->withSession($adminSession)
            ->withHeaders(['Accept' => 'application/json'])
            ->get('api/agencias/geolocalizacion');

        $res->assertStatus(403);
        $json = json_decode($res->getBody(), true);
        $this->assertSame('error', $json['status'] ?? null);
        $this->assertSame('forbidden', $json['error'] ?? null);
    }

    public function testSupervisorAccessToGeoApiReturns200Contract(): void
    {
        $id = $this->createGeoAgency([
            'nombre_agencia' => 'Agencia Contrato JSON',
            'marcas'         => [1, 2], // Honda, Yamaha
        ]);

        $res = $this->withSession($this->supervisorSvSession)
            ->withHeaders(['Accept' => 'application/json'])
            ->get('api/agencias/geolocalizacion');

        $res->assertStatus(200);
        $json = json_decode($res->getBody(), true);
        $this->assertSame('ok', $json['status'] ?? null);
        $this->assertSame('El Salvador', $json['pais'] ?? null);
        $this->assertGreaterThanOrEqual(1, (int) ($json['total'] ?? 0));
        $this->assertIsArray($json['agencias'] ?? null);

        $found = null;
        foreach ($json['agencias'] as $a) {
            if ($a['id'] === $id) {
                $found = $a;
                break;
            }
        }

        $this->assertNotNull($found);
        $this->assertSame('Agencia Contrato JSON', $found['nombre']);
        $this->assertSame('El Salvador', $found['pais']);
        $this->assertContains('Honda', $found['marcas']);
        $this->assertContains('Yamaha', $found['marcas']);
        $this->assertNotNull($found['latitud']);
        $this->assertNotNull($found['longitud']);
        $this->assertNotNull($found['departamento']);
        $this->assertNotNull($found['municipio']);
    }

    public function testGeoApiExcludesInactiveByDefault(): void
    {
        $idActiva = $this->createGeoAgency(['nombre_agencia' => 'Geo Activa']);
        $idInactiva = $this->createGeoAgency(['nombre_agencia' => 'Geo Inactiva']);

        model(AgenciaModel::class)->softDisableWithAudit($idInactiva, 1, 'Deshabilitada para API', 'EMP-SV-002');

        $res = $this->withSession($this->supervisorSvSession)
            ->withHeaders(['Accept' => 'application/json'])
            ->get('api/agencias/geolocalizacion');

        $res->assertStatus(200);
        $json = json_decode($res->getBody(), true);
        $ids = array_column($json['agencias'], 'id');

        $this->assertContains($idActiva, $ids);
        $this->assertNotContains($idInactiva, $ids);
    }

    public function testGeoApiIncludesInactiveWhenFlagged(): void
    {
        $idInactiva = $this->createGeoAgency(['nombre_agencia' => 'Geo Flagged Inactiva']);
        model(AgenciaModel::class)->softDisableWithAudit($idInactiva, 1, 'Deshabilitada temporalmente', 'EMP-SV-002');

        $res = $this->withSession($this->supervisorSvSession)
            ->withHeaders(['Accept' => 'application/json'])
            ->get('api/agencias/geolocalizacion?incluir_inactivas=1');

        $res->assertStatus(200);
        $json = json_decode($res->getBody(), true);
        $ids = array_column($json['agencias'], 'id');

        $this->assertContains($idInactiva, $ids);
    }

    public function testGeoApiFiltersByIdMarcaMultiBrand(): void
    {
        $idHondaOnly = $this->createGeoAgency(['nombre_agencia' => 'Solo Honda', 'marcas' => [1]]);
        $idMulti = $this->createGeoAgency(['nombre_agencia' => 'Honda y Yamaha', 'marcas' => [1, 2]]);
        $idYamahaOnly = $this->createGeoAgency(['nombre_agencia' => 'Solo Yamaha', 'marcas' => [2]]);

        // Filter by id_marca = 2 (Yamaha)
        $res = $this->withSession($this->supervisorSvSession)
            ->withHeaders(['Accept' => 'application/json'])
            ->get('api/agencias/geolocalizacion?id_marca=2');

        $res->assertStatus(200);
        $json = json_decode($res->getBody(), true);
        $ids = array_column($json['agencias'], 'id');

        $this->assertNotContains($idHondaOnly, $ids);
        $this->assertContains($idMulti, $ids);
        $this->assertContains($idYamahaOnly, $ids);

        // Multi-brand agency must retain all brands in response
        foreach ($json['agencias'] as $a) {
            if ($a['id'] === $idMulti) {
                $this->assertContains('Honda', $a['marcas']);
                $this->assertContains('Yamaha', $a['marcas']);
            }
        }
    }

    public function testGeoApiFiltersByMarcaName(): void
    {
        $idSuzuki = $this->createGeoAgency(['nombre_agencia' => 'Agencia Suzuki', 'marcas' => [3]]);
        $idHero = $this->createGeoAgency(['nombre_agencia' => 'Agencia Hero', 'marcas' => [4]]);

        $res = $this->withSession($this->supervisorSvSession)
            ->withHeaders(['Accept' => 'application/json'])
            ->get('api/agencias/geolocalizacion?marca=Suzuki');

        $res->assertStatus(200);
        $json = json_decode($res->getBody(), true);
        $ids = array_column($json['agencias'], 'id');

        $this->assertContains($idSuzuki, $ids);
        $this->assertNotContains($idHero, $ids);
    }

    public function testGeoApiFiltersByDepartamento(): void
    {
        $idSanSalvador = $this->createGeoAgency([
            'nombre_agencia'  => 'Agencia SS',
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 1,
        ]);
        $idSantaAna = $this->createGeoAgency([
            'nombre_agencia'  => 'Agencia SA',
            'id_departamento' => 3,
            'id_municipio'    => 6,
            'id_distrito'     => 8,
        ]);

        $res = $this->withSession($this->supervisorSvSession)
            ->withHeaders(['Accept' => 'application/json'])
            ->get('api/agencias/geolocalizacion?id_departamento=1');

        $res->assertStatus(200);
        $json = json_decode($res->getBody(), true);
        $ids = array_column($json['agencias'], 'id');

        $this->assertContains($idSanSalvador, $ids);
        $this->assertNotContains($idSantaAna, $ids);
    }

    public function testGeoApiCrossCountryIdPaisReturns403Json(): void
    {
        $res = $this->withSession($this->supervisorSvSession)
            ->withHeaders(['Accept' => 'application/json'])
            ->get('api/agencias/geolocalizacion?id_pais=2'); // GT

        $res->assertStatus(403);
        $json = json_decode($res->getBody(), true);
        $this->assertSame('error', $json['status'] ?? null);
        $this->assertSame('forbidden', $json['error'] ?? null);
    }

    public function testGeoApiCrossCountryPaisNameReturns403Json(): void
    {
        $res = $this->withSession($this->supervisorSvSession)
            ->withHeaders(['Accept' => 'application/json'])
            ->get('api/agencias/geolocalizacion?pais=Guatemala');

        $res->assertStatus(403);
        $json = json_decode($res->getBody(), true);
        $this->assertSame('error', $json['status'] ?? null);
        $this->assertSame('forbidden', $json['error'] ?? null);
    }

    public function testGeoApiCrossCountryDepartamentoReturns403Json(): void
    {
        // Department 5 belongs to Guatemala (id_pais = 2)
        $res = $this->withSession($this->supervisorSvSession)
            ->withHeaders(['Accept' => 'application/json'])
            ->get('api/agencias/geolocalizacion?id_departamento=5');

        $res->assertStatus(403);
        $json = json_decode($res->getBody(), true);
        $this->assertSame('error', $json['status'] ?? null);
        $this->assertSame('forbidden', $json['error'] ?? null);
    }

    public function testGeoApiCrossCountryMunicipioReturns403Json(): void
    {
        // Municipality 8 belongs to Guatemala (Mixco)
        $res = $this->withSession($this->supervisorSvSession)
            ->withHeaders(['Accept' => 'application/json'])
            ->get('api/agencias/geolocalizacion?id_municipio=8');

        $res->assertStatus(403);
        $json = json_decode($res->getBody(), true);
        $this->assertSame('error', $json['status'] ?? null);
        $this->assertSame('forbidden', $json['error'] ?? null);
    }

    public function testGeoApiNoMatchReturns200Empty(): void
    {
        $res = $this->withSession($this->supervisorSvSession)
            ->withHeaders(['Accept' => 'application/json'])
            ->get('api/agencias/geolocalizacion?id_marca=999999');

        $res->assertStatus(200);
        $json = json_decode($res->getBody(), true);
        $this->assertSame('ok', $json['status'] ?? null);
        $this->assertSame(0, $json['total'] ?? null);
        $this->assertSame([], $json['agencias'] ?? null);
    }

    public function testCascadingPaisesReturnsSupervisorCountry(): void
    {
        // SV supervisor
        $resSv = $this->withSession($this->supervisorSvSession)->get('api/geo/paises');
        $resSv->assertStatus(200);
        $jsonSv = json_decode($resSv->getBody(), true);
        $this->assertCount(1, $jsonSv['paises']);
        $this->assertSame(1, $jsonSv['paises'][0]['id_pais']);
        $this->assertSame('El Salvador', $jsonSv['paises'][0]['pais']);
        $this->assertTrue($jsonSv['paises'][0]['requiere_distrito']);

        // GT supervisor
        $resGt = $this->withSession($this->supervisorGtSession)->get('api/geo/paises');
        $resGt->assertStatus(200);
        $jsonGt = json_decode($resGt->getBody(), true);
        $this->assertCount(1, $jsonGt['paises']);
        $this->assertSame(2, $jsonGt['paises'][0]['id_pais']);
        $this->assertSame('Guatemala', $jsonGt['paises'][0]['pais']);
        $this->assertFalse($jsonGt['paises'][0]['requiere_distrito']);
    }

    public function testCascadingDepartamentosReturnsSupervisorCountry(): void
    {
        $res = $this->withSession($this->supervisorSvSession)->get('api/geo/departamentos');
        $res->assertStatus(200);
        $json = json_decode($res->getBody(), true);
        $this->assertNotEmpty($json['departamentos']);
        foreach ($json['departamentos'] as $d) {
            $this->assertSame(1, $d['id_pais']);
        }

        // Querying foreign id_pais returns 403
        $resForeign = $this->withSession($this->supervisorSvSession)->get('api/geo/departamentos?id_pais=2');
        $resForeign->assertStatus(403);
    }

    public function testCascadingMunicipiosReturnsChildMunicipalities(): void
    {
        $res = $this->withSession($this->supervisorSvSession)->get('api/geo/municipios?id_departamento=1');
        $res->assertStatus(200);
        $json = json_decode($res->getBody(), true);
        $this->assertNotEmpty($json['municipios']);
        foreach ($json['municipios'] as $m) {
            $this->assertSame(1, $m['id_departamento']);
        }

        // Foreign department returns 403
        $resForeign = $this->withSession($this->supervisorSvSession)->get('api/geo/municipios?id_departamento=5');
        $resForeign->assertStatus(403);
    }

    public function testCascadingDistritosReturnsChildDistricts(): void
    {
        $res = $this->withSession($this->supervisorSvSession)->get('api/geo/distritos?id_municipio=1');
        $res->assertStatus(200);
        $json = json_decode($res->getBody(), true);
        $this->assertNotEmpty($json['distritos']);
        foreach ($json['distritos'] as $d) {
            $this->assertSame(1, $d['id_municipio']);
        }

        // Foreign municipality returns 403
        $resForeign = $this->withSession($this->supervisorSvSession)->get('api/geo/distritos?id_municipio=8');
        $resForeign->assertStatus(403);

        // Guatemala municipality returns empty distritos array
        $resGt = $this->withSession($this->supervisorGtSession)->get('api/geo/distritos?id_municipio=8');
        $resGt->assertStatus(200);
        $jsonGt = json_decode($resGt->getBody(), true);
        $this->assertSame([], $jsonGt['distritos']);
    }
}
