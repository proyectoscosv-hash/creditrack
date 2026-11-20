<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AgenciaModel;
use CodeIgniter\HTTP\Response;
use Config\Services;
use Tests\Support\AuthTestCase;

/**
 * Feature tests for Milestone M4 (UI, Leaflet Views, Corporate Palette & Navigation):
 * - CA-02: Operational interactive Leaflet map view with active agency markers & popups
 * - CA-03: Create & edit forms embedded map with click-to-set coordinates
 * - CA-11: Exclusion of soft-disabled agencies and invalid/zero coordinates from map
 * - CA-12: Main menu "Gestión de agencias" dropdown visible exclusively to Supervisor
 * - Corporate 5-color palette presence across all agency views
 * - Non-regression of existing home.php ViewUITest assertions
 */
final class AgencyViewsUiTest extends AuthTestCase
{
    protected array $supervisorSvSession;
    protected array $supervisorGtSession;
    protected array $adminSvSession;
    protected array $asesorSvSession;

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

        $this->adminSvSession = [
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ADM-01',
            'usuario'         => 'admin_sv',
            'nombre'          => 'Carlos Admin',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
            'login_time'      => time(),
        ];

        $this->asesorSvSession = [
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ASE-01',
            'usuario'         => 'asesor_sv',
            'nombre'          => 'Ana Asesora',
            'rol'             => 'Asesor',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
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
            'nombre_agencia'  => 'Agencia UI Test ' . uniqid(),
            'direccion'       => 'Av. Roosevelt #101',
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

    /**
     * CA-02 / Test 1: Operational map view loads for Supervisor with Leaflet assets, OSM tiles and container.
     */
    public function testCA02_OperationalMapLoadsForSupervisorWithLeafletAssetsAndOsmTiles(): void
    {
        $res = $this->withSession($this->supervisorSvSession)->get('agencias/mapa');
        $res->assertStatus(200);

        $body = $res->getBody();
        $this->assertMatchesRegularExpression('/assets\/leaflet\/leaflet\.css/i', $body);
        $this->assertMatchesRegularExpression('/assets\/leaflet\/leaflet\.js/i', $body);
        $this->assertMatchesRegularExpression('/tile\.openstreetmap\.org/i', $body);
        $this->assertMatchesRegularExpression('/<div[^>]+id=["\']map["\']/i', $body);
        $this->assertStringContainsString('OpenStreetMap', $body);
    }

    /**
     * CA-02 / Test 2: Operational map exposes active agency markers and popup data.
     */
    public function testCA02_OperationalMapExposesActiveAgencyMarkersAndPopupData(): void
    {
        $uniqueName = 'Agencia Marker Popup ' . uniqid();
        $this->createTestAgency(['nombre_agencia' => $uniqueName]);

        $res = $this->withSession($this->supervisorSvSession)->get('agencias/mapa');
        $res->assertStatus(200);

        $body = $res->getBody();
        $this->assertMatchesRegularExpression('/bindPopup/i', $body);
        $this->assertMatchesRegularExpression('/nombre/i', $body);
        $this->assertMatchesRegularExpression('/direcci[oó]n/i', $body);
        $this->assertMatchesRegularExpression('/pa[ií]s/i', $body);
        $this->assertMatchesRegularExpression('/marcas/i', $body);
        $this->assertStringContainsString($uniqueName, $body);
    }

    /**
     * CA-02 / Test 3: Operational map restricted to Supervisor role (302 for guest, 403 for Admin/Asesor).
     */
    public function testCA02_OperationalMapRestrictedToSupervisorRole(): void
    {
        // Unauthenticated -> 302 to login
        $guestRes = $this->get('agencias/mapa');
        $this->assertTrue($guestRes->isRedirect() || $guestRes->response()->getStatusCode() === 302);

        // Administrador -> 403 Forbidden
        $adminRes = $this->withSession($this->adminSvSession)->get('agencias/mapa');
        $adminRes->assertStatus(403);

        // Asesor -> 403 Forbidden
        $asesorRes = $this->withSession($this->asesorSvSession)->get('agencias/mapa');
        $asesorRes->assertStatus(403);
    }

    /**
     * CA-03 / Test 4: Create form contains interactive map container and latitud/longitud inputs.
     */
    public function testCA03_CreateFormContainsInteractiveMapAndCoordinateInputs(): void
    {
        $res = $this->withSession($this->supervisorSvSession)->get('agencias/crear');
        $res->assertStatus(200);

        $body = $res->getBody();
        $this->assertMatchesRegularExpression('/assets\/leaflet\/leaflet\.css/i', $body);
        $this->assertMatchesRegularExpression('/assets\/leaflet\/leaflet\.js/i', $body);
        $this->assertMatchesRegularExpression('/<div[^>]+id=["\']map["\']/i', $body);
        $this->assertMatchesRegularExpression('/<input[^>]+name=["\']latitud["\']/i', $body);
        $this->assertMatchesRegularExpression('/<input[^>]+name=["\']longitud["\']/i', $body);
    }

    /**
     * CA-03 / Test 5: Edit form initializes map with existing agency coordinates without null/NaN.
     */
    public function testCA03_EditFormInitializesMapWithExistingAgencyCoordinates(): void
    {
        $id = $this->createTestAgency([
            'latitud'  => 13.69294123,
            'longitud' => -89.21819456,
        ]);

        $res = $this->withSession($this->supervisorSvSession)->get('agencias/editar/' . $id);
        $res->assertStatus(200);

        $body = $res->getBody();
        $this->assertStringContainsString('13.69294123', $body);
        $this->assertStringContainsString('-89.21819456', $body);
        $this->assertDoesNotMatchRegularExpression('/name=["\']latitud["\'][^>]*value=["\'](?:null|NaN)["\']/i', $body);
        $this->assertDoesNotMatchRegularExpression('/name=["\']longitud["\'][^>]*value=["\'](?:null|NaN)["\']/i', $body);
        $this->assertDoesNotMatchRegularExpression('/setView\s*\(\s*\[\s*(?:null|NaN|undefined|,)/i', $body);
    }

    /**
     * CA-03 / Test 6: Map click-to-set coordinates JS logic present in forms.
     */
    public function testCA03_MapClickToSetCoordinatesJsLogicPresentInForms(): void
    {
        $res = $this->withSession($this->supervisorSvSession)->get('agencias/crear');
        $res->assertStatus(200);

        $body = $res->getBody();
        $this->assertMatchesRegularExpression('/\.on\s*\(\s*["\']click["\']/i', $body);
        $this->assertMatchesRegularExpression('/L\.marker/i', $body);
        $this->assertMatchesRegularExpression('/latitud/i', $body);
        $this->assertMatchesRegularExpression('/longitud/i', $body);
    }

    /**
     * CA-03 / Test 7: Forms include cascading selects, brand checkboxes, and Guatemala container.
     */
    public function testCA03_FormsIncludeCascadingSelectsAndBrandCheckboxes(): void
    {
        $res = $this->withSession($this->supervisorSvSession)->get('agencias/crear');
        $res->assertStatus(200);

        $body = $res->getBody();
        $this->assertMatchesRegularExpression('/id=["\']id_departamento["\']/i', $body);
        $this->assertMatchesRegularExpression('/id=["\']id_municipio["\']/i', $body);
        $this->assertMatchesRegularExpression('/id=["\']id_distrito["\']/i', $body);
        $this->assertMatchesRegularExpression('/name=["\']marcas\[\]["\']/i', $body);
        $this->assertMatchesRegularExpression('/id=["\']requiere-distrito-container["\']/i', $body);
    }

    /**
     * CA-11 / Test 8: Operational map excludes inactive (soft-disabled) agencies by default.
     */
    public function testCA11_OperationalMapExcludesInactiveAgenciesByDefault(): void
    {
        $activeName   = 'Agencia Activa CA11 ' . uniqid();
        $inactiveName = 'Agencia Inactiva CA11 ' . uniqid();

        $this->createTestAgency(['nombre_agencia' => $activeName]);
        $idInactive = $this->createTestAgency(['nombre_agencia' => $inactiveName]);

        model(AgenciaModel::class)->softDisableWithAudit($idInactive, 1, 'Deshabilitada para test CA-11', 'EMP-SV-002');

        $res = $this->withSession($this->supervisorSvSession)->get('agencias/mapa');
        $res->assertStatus(200);

        $body = $res->getBody();
        $this->assertStringContainsString($activeName, $body);
        $this->assertStringNotContainsString($inactiveName, $body);
    }

    /**
     * CA-11 / Test 9: Operational map excludes agencies with invalid or (0, 0) coordinates.
     */
    public function testCA11_OperationalMapExcludesAgenciesWithInvalidOrZeroCoordinates(): void
    {
        $nullCoordName = 'Agencia Null Coords ' . uniqid();
        $zeroCoordName = 'Agencia Zero Coords ' . uniqid();

        // Create valid agencies first, then update coords directly on database table (avoiding model validation rejection)
        $idNull = $this->createTestAgency(['nombre_agencia' => $nullCoordName]);
        $this->db->table('Agencias')->where('Idagencia', $idNull)->update([
            'latitud'  => null,
            'longitud' => null,
        ]);

        $idZero = $this->createTestAgency(['nombre_agencia' => $zeroCoordName]);
        $this->db->table('Agencias')->where('Idagencia', $idZero)->update([
            'latitud'  => 0.0,
            'longitud' => 0.0,
        ]);

        $res = $this->withSession($this->supervisorSvSession)->get('agencias/mapa');
        $res->assertStatus(200);

        $body = $res->getBody();
        $this->assertStringNotContainsString($nullCoordName, $body);
        $this->assertStringNotContainsString($zeroCoordName, $body);
        $this->assertStringNotContainsStringIgnoringCase('NaN', $body);
    }

    /**
     * CA-12 / Test 10: Supervisor sees "Gestión de agencias" dropdown on home and agency views.
     */
    public function testCA12_SupervisorSeesGestionDeAgenciasDropdownOnHomeAndAgencyViews(): void
    {
        $routes = ['home', 'agencias', 'agencias/crear', 'agencias/mapa'];

        foreach ($routes as $route) {
            $res = $this->withSession($this->supervisorSvSession)->get($route);
            $res->assertStatus(200);

            $body = $res->getBody();
            $decodedBody = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $this->assertStringContainsStringIgnoringCase(
                'Gestión de agencias',
                $decodedBody,
                "Expected 'Gestión de agencias' dropdown on route: {$route}"
            );

            // Assert real links to agencias, agencias/crear, agencias/mapa
            foreach (['agencias', 'agencias/crear', 'agencias/mapa'] as $target) {
                $pattern = '~href=["\'](?:[^"\']*/)?' . preg_quote($target, '~') . '(?=[?#"\'])~i';
                $this->assertMatchesRegularExpression(
                    $pattern,
                    $body,
                    "Expected link to '{$target}' on route: {$route}"
                );
            }
        }
    }

    /**
     * CA-12 / Test 11: Non-supervisor roles (Admin, Asesor) do NOT see "Gestión de agencias" dropdown on home.
     */
    public function testCA12_NonSupervisorRolesDoNotSeeGestionDeAgenciasDropdownOnHome(): void
    {
        // Admin
        $adminRes = $this->withSession($this->adminSvSession)->get('home');
        $adminRes->assertStatus(200);
        $adminBody = html_entity_decode($adminRes->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringNotContainsStringIgnoringCase('Gestión de agencias', $adminBody);
        $this->assertSame(0, preg_match('~href=["\'](?:[^"\']*/)?agencias(?:/crear|/mapa)?(?=[?#"\'])~i', $adminRes->getBody()));

        // Asesor
        $asesorRes = $this->withSession($this->asesorSvSession)->get('home');
        $asesorRes->assertStatus(200);
        $asesorBody = html_entity_decode($asesorRes->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringNotContainsStringIgnoringCase('Gestión de agencias', $asesorBody);
        $this->assertSame(0, preg_match('~href=["\'](?:[^"\']*/)?agencias(?:/crear|/mapa)?(?=[?#"\'])~i', $asesorRes->getBody()));
    }

    /**
     * CA-12 / Test 12: Agency views display active user identity and country badge, omitting foreign countries.
     */
    public function testCA12_AgencyViewsDisplayActiveUserIdentityAndCountryBadge(): void
    {
        $routes = ['agencias', 'agencias/crear', 'agencias/mapa'];

        foreach ($routes as $route) {
            $res = $this->withSession($this->supervisorGtSession)->get($route);
            $res->assertStatus(200);

            $body = $res->getBody();
            $decodedBody = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            // Active supervisor identity
            $this->assertTrue(
                str_contains($decodedBody, 'Carlos Supervisor GT') || str_contains($decodedBody, 'Carlos Supervisor') || str_contains($decodedBody, 'supervisor_gt') || str_contains($decodedBody, 'Beatriz Supervisor'),
                "Expected active user identity in route: {$route}"
            );
            // Role
            $this->assertStringContainsString('Supervisor', $decodedBody);
            // Country
            $this->assertStringContainsString('Guatemala', $decodedBody);
            // No foreign country leak (T2-F16-04)
            $this->assertStringNotContainsStringIgnoringCase('El Salvador', $decodedBody);
            $this->assertStringNotContainsStringIgnoringCase('Honduras', $decodedBody);
        }
    }

    /**
     * Corporate Palette / Test 13: Corporate 5-color palette present across all agency views.
     */
    public function testCorporatePalettePresenceAcrossAllAgencyViews(): void
    {
        $routes = ['home', 'agencias', 'agencias/crear', 'agencias/mapa'];

        foreach ($routes as $route) {
            $res = $this->withSession($this->supervisorSvSession)->get($route);
            $res->assertStatus(200);

            $this->assertCorporatePalettePresent($res->getBody());
        }
    }

    /**
     * Non-Regression / Test 14: Preserves existing home.php ViewUITest assertions for Admin.
     */
    public function testPreservesExistingHomePhpViewUiTestAssertions(): void
    {
        $res = $this->withSession($this->adminSvSession)->get('home');
        $res->assertStatus(200);

        $body = $res->getBody();
        $decodedBody = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 1. Corporate palette
        $this->assertCorporatePalettePresent($body);

        // 2. Confirmation banner
        $this->assertMatchesRegularExpression('/¡?inicio de sesi[oó]n exitoso!?/i', $decodedBody);

        // 3. Active session status
        $this->assertMatchesRegularExpression('/estado de la sesi[oó]n:\s*activa/i', $decodedBody);

        // 4. User identity
        $this->assertStringContainsString('Carlos Admin', $decodedBody);
        $this->assertStringContainsString('admin_sv', $decodedBody);
        $this->assertStringContainsString('EMP-ADM-01', $decodedBody);

        // 5. Functional logout link
        $this->assertMatchesRegularExpression('/href=["\'][^"\']*logout[^"\']*["\']/i', $body);
        $this->assertMatchesRegularExpression('/cerrar sesi[oó]n/i', $decodedBody);

        // 6. Author attribution footer
        $this->assertMatchesRegularExpression('/(equipo de desarrollo|diseñad[oa]|desarrollad[oa])/i', $decodedBody);
        $this->assertStringContainsString('creditos-desarrollo', $body);
        $this->assertStringContainsString('attribution', $body);
    }
}
