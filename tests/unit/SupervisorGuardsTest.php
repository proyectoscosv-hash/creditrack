<?php

namespace Tests\Unit;

use App\Database\Seeds\AuthSeeder;
use App\Filters\SupervisorFilter;
use App\Filters\UserAdminGuardFilter;
use App\Models\AgenciaModel;
use App\Services\AgencyAccessService;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Filters;
use Config\Services;
use stdClass;

/**
 * Unit and DB-backed access-control tests for AgencyAccessService,
 * SupervisorFilter, and UserAdminGuardFilter (Milestone M2).
 *
 * @internal
 */
final class SupervisorGuardsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace   = 'App';
    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $seed        = AuthSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearSessionAndGlobals();
        $this->resetResponseService();
    }

    protected function tearDown(): void
    {
        $this->clearSessionAndGlobals();
        $this->resetResponseService();
        parent::tearDown();
    }

    private function clearSessionAndGlobals(): void
    {
        $_GET     = [];
        $_POST    = [];
        $_SESSION = [];
        session()->remove([
            'isLoggedIn',
            'codigo_empleado',
            'usuario',
            'nombre',
            'rol',
            'id_pais',
            'pais_nombre',
            'login_time',
        ]);
    }

    private function resetResponseService(): void
    {
        Services::injectMock('response', new Response(config(\Config\App::class)));
    }

    /**
     * @param array<string, mixed>  $get
     * @param array<string, mixed>  $post
     * @param array<string, string> $headers
     */
    private function makeRequest(
        string $path,
        string $method = 'GET',
        array $get = [],
        array $post = [],
        array $headers = [],
        ?string $body = null,
    ): IncomingRequest {
        $appConfig = config(\Config\App::class);
        $uri       = new SiteURI($appConfig, $path);
        $request   = new IncomingRequest($appConfig, $uri, $body, new UserAgent());
        $request->setMethod($method);
        $request->setGlobal('get', $get);
        $request->setGlobal('post', $post);

        foreach ($headers as $name => $value) {
            $request->setHeader($name, $value);
        }

        return $request;
    }

    public function testFilterAndServiceRegistrations(): void
    {
        $filtersConfig = new Filters();

        $this->assertArrayHasKey('supervisor', $filtersConfig->aliases);
        $this->assertSame(SupervisorFilter::class, $filtersConfig->aliases['supervisor']);

        $this->assertArrayHasKey('user_admin_guard', $filtersConfig->aliases);
        $this->assertSame(UserAdminGuardFilter::class, $filtersConfig->aliases['user_admin_guard']);

        $this->assertNotContains('supervisor', $filtersConfig->globals['before']);
        $this->assertNotContains('user_admin_guard', $filtersConfig->globals['before']);
        $this->assertArrayNotHasKey('supervisor', $filtersConfig->globals['before']);
        $this->assertArrayNotHasKey('user_admin_guard', $filtersConfig->globals['before']);

        $this->assertArrayHasKey('supervisor', $filtersConfig->filters);
        $this->assertArrayHasKey('user_admin_guard', $filtersConfig->filters);

        $service1 = Services::agencyAccess();
        $service2 = Services::agencyAccess();
        $service3 = Services::agencyAccess(false);

        $this->assertInstanceOf(AgencyAccessService::class, $service1);
        $this->assertSame($service1, $service2);
        $this->assertNotSame($service1, $service3);
    }

    public function testIsApiRequestDetection(): void
    {
        $service = new AgencyAccessService();

        $this->assertFalse($service->isApiRequest($this->makeRequest('agencias')));
        $this->assertFalse($service->isApiRequest($this->makeRequest('usuarios/crear')));
        $this->assertTrue($service->isApiRequest($this->makeRequest('api')));
        $this->assertTrue($service->isApiRequest($this->makeRequest('api/agencias/geolocalizacion')));
        $this->assertTrue($service->isApiRequest($this->makeRequest('API/Geo/Departamentos')));
        $this->assertTrue($service->isApiRequest($this->makeRequest('agencias', 'GET', [], [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])));
        $this->assertTrue($service->isApiRequest($this->makeRequest('usuarios', 'GET', [], [], [
            'Accept' => 'application/json',
        ])));
        $this->assertTrue($service->isApiRequest($this->makeRequest('agencias/guardar', 'POST', [], [], [
            'Content-Type' => 'application/json; charset=utf-8',
        ])));
        $this->assertTrue($service->isApiRequest($this->makeRequest('index.php/api/geo/departamentos')));
        $this->assertTrue($service->isApiRequest($this->makeRequest('index.php//api/geo/departamentos')));
        $this->assertTrue($service->isApiRequest($this->makeRequest('index.php///api/geo')));
        $this->assertTrue($service->isApiRequest($this->makeRequest('index.php//api')));
        $this->assertTrue($service->isApiRequest($this->makeRequest('INDEX.PHP//API/Agencias')));
        $this->assertTrue($service->isApiRequest($this->makeRequest('api?id_pais=1')));
        $this->assertFalse($service->isApiRequest($this->makeRequest('api-docs')));
        $this->assertFalse($service->isApiRequest($this->makeRequest('index.php//agencias')));
    }

    public function testInspectSessionContextValidationAndPaisFallback(): void
    {
        $service = new AgencyAccessService();

        // Unauthenticated states
        $this->assertSame('unauthenticated', $service->inspectSessionContext()['status']);
        session()->set(['isLoggedIn' => false, 'rol' => 'Supervisor', 'id_pais' => 1]);
        $this->assertSame('unauthenticated', $service->inspectSessionContext()['status']);
        session()->set(['isLoggedIn' => '1', 'rol' => 'Supervisor', 'id_pais' => 1]);
        $this->assertSame('unauthenticated', $service->inspectSessionContext()['status']);

        // Forbidden roles (non-Supervisor, wrong case, whitespace-padded, non-string, missing)
        foreach (['Administrador', 'Asesor', 'supervisor', 'SUPERVISOR', 'Supervisor ', ' Supervisor', '', 'SuperHacker', null, ['Supervisor'], 123] as $badRole) {
            session()->set([
                'isLoggedIn' => true,
                'rol'        => $badRole,
                'id_pais'    => 1,
            ]);
            $ctx = $service->inspectSessionContext();
            $this->assertSame('forbidden', $ctx['status']);
            $this->assertFalse($service->isSupervisor());
            $this->assertNull($service->getSupervisorCountryId());
            $this->assertSame('', $service->getSupervisorCountryName());
        }

        // Forbidden id_pais (bool, array, object, float, 0, negative, non-numeric, missing)
        foreach ([null, '', 0, -1, '0', '-2', 'abc', '1.5', 1.5, 1.0, true, false, ['1'], new stdClass()] as $badPais) {
            session()->set([
                'isLoggedIn' => true,
                'rol'        => 'Supervisor',
                'id_pais'    => $badPais,
            ]);
            $this->assertSame('forbidden', $service->inspectSessionContext()['status']);
        }

        // Minimal valid Supervisor session without pais_nombre -> resolves from PaisModel
        session()->set([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-SV-002',
            'rol'             => 'Supervisor',
            'id_pais'         => 1,
            'pais_nombre'     => null,
        ]);
        $ctxSv = $service->inspectSessionContext();
        $this->assertSame('ok', $ctxSv['status']);
        $this->assertSame(1, $ctxSv['id_pais']);
        $this->assertSame('El Salvador', $ctxSv['pais_nombre']);
        $this->assertTrue($service->isSupervisor());
        $this->assertSame(1, $service->getSupervisorCountryId());
        $this->assertSame('El Salvador', $service->getSupervisorCountryName());

        // String positive integer id_pais (PostgreSQL compatibility)
        session()->set([
            'isLoggedIn'  => true,
            'rol'         => 'Supervisor',
            'id_pais'     => '2',
            'pais_nombre' => 'Guatemala',
        ]);
        $ctxGt = $service->inspectSessionContext();
        $this->assertSame('ok', $ctxGt['status']);
        $this->assertSame(2, $ctxGt['id_pais']);
        $this->assertSame('Guatemala', $ctxGt['pais_nombre']);

        // Mismatched session pais_nombre is overridden by DB truth (PaisModel::findById)
        session()->set([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-SV-002',
            'rol'             => 'Supervisor',
            'id_pais'         => 1,
            'pais_nombre'     => 'Guatemala',
        ]);
        $ctxMismatch = $service->inspectSessionContext();
        $this->assertSame('ok', $ctxMismatch['status']);
        $this->assertSame('El Salvador', $ctxMismatch['pais_nombre']);
        $resMismatch = $service->authorizeSupervisorRequest($this->makeRequest('agencias', 'GET', ['pais' => 'Guatemala']));
        $this->assertInstanceOf(ResponseInterface::class, $resMismatch);
        $this->assertSame(403, $resMismatch->getStatusCode());

        // Non-existent id_pais in Pais table fails closed even if session pais_nombre is spoofed
        session()->set([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-FAKE-999',
            'rol'             => 'Supervisor',
            'id_pais'         => 999,
            'pais_nombre'     => 'Guatemala',
        ]);
        $ctxUnknownPais = $service->inspectSessionContext();
        $this->assertSame('forbidden', $ctxUnknownPais['status']);
        $this->assertFalse($service->isSupervisor());
        $this->assertNull($service->getSupervisorCountryId());
        $this->assertSame('', $service->getSupervisorCountryName());
        $resUnknownPais = $service->authorizeSupervisorRequest(
            $this->makeRequest('agencias', 'GET', ['pais' => 'Guatemala'])
        );
        $this->assertInstanceOf(ResponseInterface::class, $resUnknownPais);
        $this->assertSame(403, $resUnknownPais->getStatusCode());
    }

    public function testCheckRequestCountryParamsPreventsPollutionAndTampering(): void
    {
        $service = new AgencyAccessService();

        // Empty / null / matching GET params
        $this->assertTrue($service->checkRequestCountryParams($this->makeRequest('agencias'), 1, 'El Salvador'));
        $this->assertTrue($service->checkRequestCountryParams(
            $this->makeRequest('agencias', 'GET', ['id_pais' => '', 'pais' => '   ']),
            1,
            'El Salvador'
        ));
        $this->assertTrue($service->checkRequestCountryParams(
            $this->makeRequest('agencias', 'GET', ['id_pais' => '1', 'pais' => 'el salvador']),
            1,
            'El Salvador'
        ));
        $this->assertTrue($service->checkRequestCountryParams(
            $this->makeRequest('agencias', 'GET', ['pais' => '1']),
            1,
            'El Salvador'
        ));

        // Cross-country GET / POST / PUT raw / JSON body
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('agencias', 'GET', ['id_pais' => '2']),
            1,
            'El Salvador'
        ));
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('agencias', 'GET', ['Id_pais' => '2']),
            1,
            'El Salvador'
        ));
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('agencias', 'GET', ['pais' => 'Guatemala']),
            1,
            'El Salvador'
        ));
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('agencias/guardar', 'POST', [], ['id_pais' => '2']),
            1,
            'El Salvador'
        ));
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('agencias/actualizar/1', 'PUT', [], [], [], 'id_pais=2'),
            1,
            'El Salvador'
        ));
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('api/agencias', 'POST', [], [], ['Content-Type' => 'application/json'], '{"id_pais":2}'),
            1,
            'El Salvador'
        ));
        $this->assertTrue($service->checkRequestCountryParams(
            $this->makeRequest('api/agencias', 'POST', [], [], ['Content-Type' => 'application/json'], '{"id_pais":1,"pais":"El Salvador"}'),
            1,
            'El Salvador'
        ));

        // Parameter pollution (valid pais + mismatched id_pais across sources or keys)
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('agencias', 'GET', ['pais' => 'El Salvador', 'id_pais' => '2']),
            1,
            'El Salvador'
        ));
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('agencias', 'POST', ['id_pais' => '1'], ['id_pais' => '2']),
            1,
            'El Salvador'
        ));

        // Non-scalar / float / negative / zero tampering + malformed JSON safety
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('agencias', 'GET', ['id_pais' => ['1']]),
            1,
            'El Salvador'
        ));
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('agencias', 'GET', ['pais' => '1.5']),
            1,
            'El Salvador'
        ));
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('agencias', 'GET', ['id_pais' => '0']),
            1,
            'El Salvador'
        ));
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('api/agencias', 'POST', [], [], ['Content-Type' => 'application/json'], '{"id_pais": true}'),
            1,
            'El Salvador'
        ));
        $this->assertTrue($service->checkRequestCountryParams(
            $this->makeRequest('api/agencias', 'POST', [], [], ['Content-Type' => 'application/json'], '{malformed-json'),
            1,
            'El Salvador'
        ));

        // Case-insensitive GET / POST / PUT / nested form keys
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('agencias', 'GET', ['Id_Pais' => '2']),
            1,
            'El Salvador'
        ));
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('agencias', 'GET', ['ID_PAIS' => '2']),
            1,
            'El Salvador'
        ));
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('agencias', 'GET', ['PAIS' => 'Guatemala']),
            1,
            'El Salvador'
        ));
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('agencias', 'GET', ['Pais' => 'Guatemala']),
            1,
            'El Salvador'
        ));
        $this->assertTrue($service->checkRequestCountryParams(
            $this->makeRequest('agencias', 'GET', ['ID_PAIS' => '1', 'PAIS' => 'el salvador']),
            1,
            'El Salvador'
        ));
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('agencias/guardar', 'POST', [], ['ID_PAIS' => '2']),
            1,
            'El Salvador'
        ));
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('agencias/actualizar/1', 'PUT', [], [], [], 'ID_PAIS=2'),
            1,
            'El Salvador'
        ));
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('agencias/actualizar/1', 'PUT', [], [], [], 'agencia[id_pais]=2'),
            1,
            'El Salvador'
        ));
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('agencias', 'GET', ['filtros' => ['id_pais' => '2']]),
            1,
            'El Salvador'
        ));

        // Nested & array JSON payloads + parse_str false-positive prevention on JSON strings
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('api/agencias', 'POST', [], [], ['Content-Type' => 'application/json'], '[{"id_pais":2}]'),
            1,
            'El Salvador'
        ));
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('api/agencias', 'POST', [], [], ['Content-Type' => 'application/json'], '[{"id_pais":1},{"id_pais":2}]'),
            1,
            'El Salvador'
        ));
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('api/agencias', 'POST', [], [], ['Content-Type' => 'application/json'], '{"agencia":{"id_pais":2}}'),
            1,
            'El Salvador'
        ));
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('api/agencias', 'POST', [], [], ['Content-Type' => 'application/json'], '{"agencia":{"Id_Pais":2}}'),
            1,
            'El Salvador'
        ));
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('api/agencias', 'POST', [], [], ['Content-Type' => 'application/json'], '{"agencia":{"PAIS":"Guatemala"}}'),
            1,
            'El Salvador'
        ));
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('api/agencias', 'POST', [], [], ['Content-Type' => 'application/json'], '{"id_pais":1,"agencia":{"id_pais":2}}'),
            1,
            'El Salvador'
        ));
        $this->assertFalse($service->checkRequestCountryParams(
            $this->makeRequest('api/agencias', 'POST', [], [], ['Content-Type' => 'application/json'], '{"agencia":{"id_pais":["1"]}}'),
            1,
            'El Salvador'
        ));
        $this->assertTrue($service->checkRequestCountryParams(
            $this->makeRequest('api/agencias', 'POST', [], [], ['Content-Type' => 'application/json'], '{"agencia":{"id_pais":1,"pais":"El Salvador"}}'),
            1,
            'El Salvador'
        ));
        $this->assertTrue($service->checkRequestCountryParams(
            $this->makeRequest('api/agencias', 'POST', [], [], ['Content-Type' => 'application/json'], '{"direccion":"Calle 1 & id_pais=2"}'),
            1,
            'El Salvador'
        ));
    }

    public function testEntityAndGeoOwnershipChecksDistinguishNotFoundFromForbidden(): void
    {
        $agenciaModel = new AgenciaModel();

        $svAgencyId = $agenciaModel->createWithBrandsAndAudit([
            'nombre_agencia'  => 'Agencia SV Prueba M2',
            'direccion'       => 'Calle El Salvador #100',
            'id_pais'         => 1,
            'id_departamento' => 1,
            'id_municipio'    => 1,
            'id_distrito'     => 1,
            'latitud'         => 13.69294,
            'longitud'        => -89.21819,
        ], [1], 'EMP-SV-002');

        $gtAgencyId = $agenciaModel->createWithBrandsAndAudit([
            'nombre_agencia'  => 'Agencia GT Prueba M2',
            'direccion'       => 'Zona 10 Guatemala',
            'id_pais'         => 2,
            'id_departamento' => 5,
            'id_municipio'    => 7,
            'id_distrito'     => null,
            'latitud'         => 14.63491,
            'longitud'        => -90.50688,
        ], [2], 'EMP-GT-002');

        $service = new AgencyAccessService();

        // Record extraction & fail-closed checks
        $this->assertSame(1, $service->extractCountryIdFromRecord(['id_pais' => 1]));
        $this->assertSame(2, $service->extractCountryIdFromRecord(['Id_pais' => '2']));
        $this->assertSame(3, $service->extractCountryIdFromRecord(['Id_Pais' => 3]));
        $this->assertNull($service->extractCountryIdFromRecord(['pais' => 'El Salvador']));

        $geoRows = $agenciaModel->findForGeolocation(1);
        $this->assertNotEmpty($geoRows);
        $this->assertFalse($service->isAgencyOwnedByCountry($geoRows[0], 1));

        // Unauthenticated authorizeAgencyAccess
        $this->assertSame('unauthenticated', $service->authorizeAgencyAccess($svAgencyId)['status']);

        // Non-supervisor authorizeAgencyAccess
        session()->set(['isLoggedIn' => true, 'rol' => 'Administrador', 'id_pais' => 1]);
        $this->assertSame('forbidden', $service->authorizeAgencyAccess($svAgencyId)['status']);

        // Supervisor SV session
        session()->set([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-SV-002',
            'rol'             => 'Supervisor',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ]);

        $svRaw = $agenciaModel->findByIdRaw($svAgencyId);
        $gtRaw = $agenciaModel->findByIdRaw($gtAgencyId);
        $this->assertNotNull($svRaw);
        $this->assertNotNull($gtRaw);
        $this->assertTrue($service->isAgencyOwnedByCurrentSupervisor($svRaw));
        $this->assertFalse($service->isAgencyOwnedByCurrentSupervisor($gtRaw));

        $okRes = $service->authorizeAgencyAccess($svAgencyId);
        $this->assertSame('ok', $okRes['status']);
        $this->assertSame('Agencia SV Prueba M2', $okRes['agency']['nombre_agencia']);
        $this->assertArrayHasKey('marcas', $okRes['agency']);

        // Cross-country agency -> forbidden (403), NOT not_found (404)
        $crossRes = $service->authorizeAgencyAccess($gtAgencyId);
        $this->assertSame('forbidden', $crossRes['status']);

        // Missing or non-positive agency ID -> not_found (404)
        $this->assertSame('not_found', $service->authorizeAgencyAccess(999999)['status']);
        $this->assertSame('not_found', $service->authorizeAgencyAccess(0)['status']);

        // Transitive geo ownership checks (SV=1, GT=2)
        $this->assertTrue($service->isDepartamentoInCountry(1, 1));
        $this->assertFalse($service->isDepartamentoInCountry(5, 1));
        $this->assertSame('forbidden', $service->checkDepartamentoOwnership(5, 1)['status']);
        $this->assertSame('not_found', $service->checkDepartamentoOwnership(99999, 1)['status']);

        $this->assertTrue($service->isMunicipioInCountry(1, 1));
        $this->assertFalse($service->isMunicipioInCountry(7, 1));
        $this->assertSame('forbidden', $service->checkMunicipioOwnership(7, 1)['status']);
        $this->assertSame('not_found', $service->checkMunicipioOwnership(99999, 1)['status']);

        $this->assertTrue($service->isDistritoInCountry(1, 1));
        $this->assertSame('forbidden', $service->checkDistritoOwnership(9, 1)['status']); // HN distrito
        $this->assertSame('not_found', $service->checkDistritoOwnership(99999, 1)['status']);
        $this->assertTrue($service->isMunicipioInCountry(7, 2)); // GT municipio for GT supervisor
    }

    public function testSupervisorFilterDirectBehavior(): void
    {
        $filter = new SupervisorFilter();

        // Web unauthenticated -> 302 /login
        $this->resetResponseService();
        $res = $filter->before($this->makeRequest('agencias'));
        $this->assertInstanceOf(RedirectResponse::class, $res);
        $this->assertSame(302, $res->getStatusCode());
        $this->assertStringContainsString('login', (string) $res->getHeaderLine('Location'));

        // API unauthenticated -> 401 JSON
        $this->resetResponseService();
        $res = $filter->before($this->makeRequest('api/agencias/geolocalizacion'));
        $this->assertInstanceOf(ResponseInterface::class, $res);
        $this->assertSame(401, $res->getStatusCode());
        $json = json_decode((string) $res->getBody(), true);
        $this->assertSame('unauthenticated', $json['error'] ?? null);

        // Authenticated Administrador -> 403 Web & 403 JSON
        session()->set(['isLoggedIn' => true, 'rol' => 'Administrador', 'id_pais' => 1, 'pais_nombre' => 'El Salvador']);
        $this->resetResponseService();
        $resWeb = $filter->before($this->makeRequest('agencias'));
        $this->assertInstanceOf(ResponseInterface::class, $resWeb);
        $this->assertSame(403, $resWeb->getStatusCode());

        $this->resetResponseService();
        $resApi = $filter->before($this->makeRequest('api/geo/departamentos'));
        $this->assertInstanceOf(ResponseInterface::class, $resApi);
        $this->assertSame(403, $resApi->getStatusCode());
        $this->assertSame('forbidden', json_decode((string) $resApi->getBody(), true)['error'] ?? null);

        // Authenticated Supervisor with missing/invalid id_pais -> 403
        session()->set(['isLoggedIn' => true, 'rol' => 'Supervisor', 'id_pais' => 0]);
        $this->resetResponseService();
        $resBadPais = $filter->before($this->makeRequest('agencias'));
        $this->assertInstanceOf(ResponseInterface::class, $resBadPais);
        $this->assertSame(403, $resBadPais->getStatusCode());

        // Valid Supervisor -> cross-country query/POST/JSON -> 403; own-country -> null
        session()->set(['isLoggedIn' => true, 'rol' => 'Supervisor', 'id_pais' => 1, 'pais_nombre' => 'El Salvador']);

        $this->resetResponseService();
        $resCrossGet = $filter->before($this->makeRequest('api/geo/departamentos', 'GET', ['id_pais' => '2']));
        $this->assertInstanceOf(ResponseInterface::class, $resCrossGet);
        $this->assertSame(403, $resCrossGet->getStatusCode());

        $this->resetResponseService();
        $resCrossPost = $filter->before($this->makeRequest('agencias/guardar', 'POST', [], ['pais' => 'Guatemala']));
        $this->assertInstanceOf(ResponseInterface::class, $resCrossPost);
        $this->assertSame(403, $resCrossPost->getStatusCode());

        $this->resetResponseService();
        $resCrossJson = $filter->before($this->makeRequest(
            'api/agencias',
            'POST',
            [],
            [],
            ['Content-Type' => 'application/json'],
            '{"id_pais":2}'
        ));
        $this->assertInstanceOf(ResponseInterface::class, $resCrossJson);
        $this->assertSame(403, $resCrossJson->getStatusCode());

        $this->resetResponseService();
        $this->assertNull($filter->before($this->makeRequest('agencias', 'GET', ['id_pais' => '1'])));
    }

    public function testUserAdminGuardFilterDirectBehavior(): void
    {
        $filter = new UserAdminGuardFilter();

        // Unauthenticated web -> 302, API/AJAX -> 401 JSON
        $this->resetResponseService();
        $resUnauthWeb = $filter->before($this->makeRequest('usuarios'));
        $this->assertInstanceOf(RedirectResponse::class, $resUnauthWeb);
        $this->assertSame(302, $resUnauthWeb->getStatusCode());

        $this->resetResponseService();
        $resUnauthApi = $filter->before($this->makeRequest('api/usuarios'));
        $this->assertInstanceOf(ResponseInterface::class, $resUnauthApi);
        $this->assertSame(401, $resUnauthApi->getStatusCode());

        // Supervisor -> 403 Web & 403 JSON
        session()->set(['isLoggedIn' => true, 'rol' => 'Supervisor', 'id_pais' => 1]);
        $this->resetResponseService();
        $resSupWeb = $filter->before($this->makeRequest('roles/editar/1'));
        $this->assertInstanceOf(ResponseInterface::class, $resSupWeb);
        $this->assertSame(403, $resSupWeb->getStatusCode());

        $this->resetResponseService();
        $resSupAjax = $filter->before($this->makeRequest('permisos', 'GET', [], [], ['Accept' => 'application/json']));
        $this->assertInstanceOf(ResponseInterface::class, $resSupAjax);
        $this->assertSame(403, $resSupAjax->getStatusCode());
        $this->assertSame('forbidden', json_decode((string) $resSupAjax->getBody(), true)['error'] ?? null);

        // Unknown / tampered / padded role -> 403
        foreach (['supervisor', 'Supervisor ', ' Supervisor', 'Administrador ', ' Asesor', 'SuperHacker', '', null, ['Administrador']] as $badRole) {
            session()->set(['isLoggedIn' => true, 'rol' => $badRole, 'id_pais' => 1]);
            $this->resetResponseService();
            $resBad = $filter->before($this->makeRequest('usuarios'));
            $this->assertInstanceOf(ResponseInterface::class, $resBad);
            $this->assertSame(403, $resBad->getStatusCode());
        }

        // Administrador & Asesor -> null (allowed past guard filter)
        foreach (['Administrador', 'Asesor'] as $allowedRole) {
            session()->set(['isLoggedIn' => true, 'rol' => $allowedRole, 'id_pais' => 1]);
            $this->resetResponseService();
            $this->assertNull($filter->before($this->makeRequest('usuarios')));
        }
    }

    public function testNonApiErrorResponsesResetContentTypeAfterApiJsonResponse(): void
    {
        $service = new AgencyAccessService();

        $resApi403 = $service->forbiddenResponse($this->makeRequest('api/agencias'));
        $this->assertSame(403, $resApi403->getStatusCode());
        $this->assertStringContainsString('application/json', $resApi403->getHeaderLine('Content-Type'));

        $resWeb403 = $service->forbiddenResponse($this->makeRequest('agencias'));
        $this->assertSame(403, $resWeb403->getStatusCode());
        $this->assertStringContainsString('text/html', $resWeb403->getHeaderLine('Content-Type'));
        $this->assertSame('text/html; charset=UTF-8', $resWeb403->getHeaderLine('Content-Type'));

        $resApi404 = $service->notFoundResponse($this->makeRequest('api/usuarios'));
        $this->assertSame(404, $resApi404->getStatusCode());
        $this->assertStringContainsString('application/json', $resApi404->getHeaderLine('Content-Type'));

        $resWeb404 = $service->notFoundResponse($this->makeRequest('usuarios'));
        $this->assertSame(404, $resWeb404->getStatusCode());
        $this->assertStringContainsString('text/html', $resWeb404->getHeaderLine('Content-Type'));
        $this->assertSame('text/html; charset=UTF-8', $resWeb404->getHeaderLine('Content-Type'));
    }
}
