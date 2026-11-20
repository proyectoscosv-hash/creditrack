<?php

namespace Tests\Feature;

use CodeIgniter\HTTP\Response;
use Config\Services;
use Tests\Support\AuthTestCase;

/**
 * Feature tests for Milestone M2 (auth-supervisor-guards):
 * - Supervisor 403 denial on /usuarios*, /roles*, /permisos* (incl. API, AJAX, methods, case variants)
 * - Unauthenticated (302 / 401 JSON) and Administrador/Asesor (404, not 403) behavior on user-admin routes
 * - SupervisorFilter route integration via withRoutes() (Web 302/403/200, API 401/403/200, country isolation)
 * - home.php / home UI safety & regression checks for Supervisor
 */
final class SupervisorAccessControlTest extends AuthTestCase
{
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

    public function testSupervisorIsBlockedWith403AcrossUserRoleAndPermissionRoutes(): void
    {
        // Authenticate genuinely via POST /login as Supervisor (El Salvador)
        $loginRes = $this->post('/login', [
            'usuario'    => 'supervisor_sv',
            'contrasena' => 'SuperSv123!',
        ]);
        $loginRes->assertStatus(302);
        $loginRes->assertSessionHas('rol', 'Supervisor');

        $supervisorSession = [
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-SUP-01',
            'usuario'         => 'supervisor_sv',
            'nombre'          => 'Elena Rostova',
            'rol'             => 'Supervisor',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
            'login_time'      => time(),
        ];

        $webPaths = [
            '/usuarios',
            '/usuarios/',
            '/usuarios/crear',
            '/usuarios/editar/1',
            '/usuarios/deshabilitar/1',
            '/roles',
            '/roles/1',
            '/permisos',
            '/permisos/asignar',
            '/USUARIOS',
            '/Usuarios/Crear',
            '/Roles/',
            '/PERMISOS/1',
        ];

        foreach ($webPaths as $path) {
            $resGet = $this->withSession($supervisorSession)->get($path);
            $resGet->assertStatus(403);
        }

        foreach (['/usuarios/crear', '/roles/guardar', '/permisos/asignar'] as $postPath) {
            $resPost = $this->withSession($supervisorSession)->post($postPath, ['nombre' => 'test']);
            $resPost->assertStatus(403);
        }

        $resPut = $this->withSession($supervisorSession)->put('/usuarios/1', ['rol' => 'Administrador']);
        $resPut->assertStatus(403);

        $resDelete = $this->withSession($supervisorSession)->delete('/usuarios/1');
        $resDelete->assertStatus(403);

        // API & AJAX JSON requests must return 403 JSON
        $apiPaths = [
            '/api/usuarios',
            '/api/usuarios/1',
            '/api/roles',
            '/api/permisos/asignar',
            '/API/Roles/1',
        ];
        foreach ($apiPaths as $apiPath) {
            $resApi = $this->withSession($supervisorSession)->get($apiPath);
            $resApi->assertStatus(403);
            $decoded = json_decode($resApi->getJSON(), true);
            $this->assertSame('error', $decoded['status'] ?? null);
            $this->assertSame('forbidden', $decoded['error'] ?? null);
        }

        $resAjax = $this->withSession($supervisorSession)
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->get('/usuarios');
        $resAjax->assertStatus(403);
        $ajaxJson = json_decode($resAjax->getJSON(), true);
        $this->assertSame('forbidden', $ajaxJson['error'] ?? null);
        $this->withHeaders([]);
    }

    public function testUnauthenticatedAndNonSupervisorBehaviorOnUserAdminRoutes(): void
    {
        // Unauthenticated web -> 302 /login
        foreach (['/usuarios', '/roles', '/permisos', '/Usuarios/Crear'] as $webPath) {
            $res = $this->get($webPath);
            $res->assertStatus(302);
            $this->assertStringContainsString('login', (string) $res->response()->getHeaderLine('Location'));
        }

        // Unauthenticated API / AJAX -> 401 JSON
        foreach (['/api/usuarios', '/api/roles', '/api/permisos'] as $apiPath) {
            $res = $this->get($apiPath);
            $res->assertStatus(401);
            $decoded = json_decode($res->getJSON(), true);
            $this->assertSame('unauthenticated', $decoded['error'] ?? null);
        }

        $resAjax = $this->withHeaders(['Accept' => 'application/json'])->get('/usuarios');
        $resAjax->assertStatus(401);
        $this->withHeaders([]);

        // Administrador & Asesor are NOT blocked by UserAdminGuardFilter (receive 404 while unimplemented)
        $adminSession = [
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ADM-01',
            'usuario'         => 'admin_sv',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ];
        $asesorSession = [
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ASE-01',
            'usuario'         => 'asesor_sv',
            'rol'             => 'Asesor',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ];

        foreach ([$adminSession, $asesorSession] as $sess) {
            foreach (['/usuarios', '/roles', '/permisos'] as $webPath) {
                $res = $this->withSession($sess)->get($webPath);
                $res->assertStatus(404);
            }
            foreach (['/api/usuarios', '/api/roles', '/api/permisos'] as $apiPath) {
                $res = $this->withSession($sess)->get($apiPath);
                $res->assertStatus(404);
                $decoded = json_decode($res->getJSON(), true);
                $this->assertSame('not_found', $decoded['error'] ?? null);
            }
        }
    }

    public function testSupervisorFilterEndToEndRouteIntegration(): void
    {
        $testRoutes = [
            ['GET', 'test-m2/agencias', static fn () => 'ok-web', ['filter' => 'supervisor']],
            ['POST', 'test-m2/agencias/guardar', static fn () => 'ok-post', ['filter' => 'supervisor']],
            ['GET', 'api/test-m2/agencias', static fn () => json_encode(['status' => 'ok']), ['filter' => 'supervisor']],
            ['POST', 'api/test-m2/agencias', static fn () => json_encode(['status' => 'ok']), ['filter' => 'supervisor']],
        ];

        // 1. Unauthenticated web -> 302 /login; API -> 401 JSON
        $resWebUnauth = $this->withRoutes($testRoutes)->get('/test-m2/agencias');
        $resWebUnauth->assertStatus(302);
        $this->assertStringContainsString('login', (string) $resWebUnauth->response()->getHeaderLine('Location'));

        $resApiUnauth = $this->withRoutes($testRoutes)->get('/api/test-m2/agencias');
        $resApiUnauth->assertStatus(401);
        $this->assertSame('unauthenticated', json_decode($resApiUnauth->getJSON(), true)['error'] ?? null);

        // 2. Administrador & Asesor -> 403
        foreach (['Administrador', 'Asesor'] as $otherRole) {
            $sess = [
                'isLoggedIn'  => true,
                'rol'         => $otherRole,
                'id_pais'     => 1,
                'pais_nombre' => 'El Salvador',
            ];
            $this->withRoutes($testRoutes)->withSession($sess)->get('/test-m2/agencias')->assertStatus(403);
            $this->withRoutes($testRoutes)->withSession($sess)->get('/api/test-m2/agencias')->assertStatus(403);
        }

        // 3. Valid Supervisor (including minimal session without pais_nombre) -> 200 on own-country
        $minimalSupervisor = [
            'isLoggedIn' => true,
            'rol'        => 'Supervisor',
            'id_pais'    => 1,
        ];
        $this->withRoutes($testRoutes)->withSession($minimalSupervisor)->get('/test-m2/agencias')->assertStatus(200);
        $this->withRoutes($testRoutes)->withSession($minimalSupervisor)->get('/test-m2/agencias?id_pais=1')->assertStatus(200);
        $this->withRoutes($testRoutes)->withSession($minimalSupervisor)->get('/test-m2/agencias?pais=El+Salvador')->assertStatus(200);
        $this->withRoutes($testRoutes)->withSession($minimalSupervisor)->post('/test-m2/agencias/guardar', ['id_pais' => '1'])->assertStatus(200);
        $this->withRoutes($testRoutes)
            ->withSession($minimalSupervisor)
            ->withBodyFormat('json')
            ->post('/api/test-m2/agencias', ['id_pais' => 1])
            ->assertStatus(200);
        $this->withBodyFormat('')->withBody('');

        // 4. Cross-country GET / POST / Pollution / JSON -> 403
        $this->withRoutes($testRoutes)->withSession($minimalSupervisor)->get('/test-m2/agencias?id_pais=2')->assertStatus(403);
        $this->withRoutes($testRoutes)->withSession($minimalSupervisor)->get('/test-m2/agencias?pais=Guatemala')->assertStatus(403);
        $this->withRoutes($testRoutes)->withSession($minimalSupervisor)->get('/test-m2/agencias?pais=El+Salvador&id_pais=2')->assertStatus(403);
        $this->withRoutes($testRoutes)->withSession($minimalSupervisor)->post('/test-m2/agencias/guardar', ['id_pais' => '2'])->assertStatus(403);
        $this->withRoutes($testRoutes)
            ->withSession($minimalSupervisor)
            ->withBodyFormat('json')
            ->post('/api/test-m2/agencias', ['id_pais' => 2])
            ->assertStatus(403);
        $this->withBodyFormat('')->withBody('');

        // 5. Tampered sessions -> 403
        $tamperedSessions = [
            ['isLoggedIn' => true, 'rol' => 'Supervisor'],
            ['isLoggedIn' => true, 'rol' => 'Supervisor', 'id_pais' => 0],
            ['isLoggedIn' => true, 'rol' => 'supervisor', 'id_pais' => 1],
            ['isLoggedIn' => true, 'rol' => 'Supervisor ', 'id_pais' => 1],
            ['isLoggedIn' => true, 'rol' => ' Supervisor', 'id_pais' => 1],
            ['isLoggedIn' => true, 'rol' => 'InvalidRole', 'id_pais' => 1],
        ];
        foreach ($tamperedSessions as $badSession) {
            $this->withRoutes($testRoutes)->withSession($badSession)->get('/test-m2/agencias')->assertStatus(403);
            $this->withRoutes($testRoutes)->withSession($badSession)->get('/api/test-m2/agencias')->assertStatus(403);
        }
    }

    public function testHomeUiSafetyAndCountryIsolationForSupervisor(): void
    {
        $svSession = [
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-SUP-01',
            'usuario'         => 'supervisor_sv',
            'nombre'          => 'Elena Rostova',
            'rol'             => 'Supervisor',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
            'login_time'      => time(),
        ];

        $gtSession = [
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-SUP-02',
            'usuario'         => 'supervisor_gt',
            'nombre'          => 'Beatriz Supervisor',
            'rol'             => 'Supervisor',
            'id_pais'         => 2,
            'pais_nombre'     => 'Guatemala',
            'login_time'      => time(),
        ];

        $resSv = $this->withSession($svSession)->get('/home.php');
        $resSv->assertStatus(200);
        $svBody = (string) $resSv->getBody();
        $this->assertCorporatePalettePresent($svBody);
        $this->assertStringContainsString('El Salvador', $svBody);
        $this->assertStringNotContainsString('/usuarios', $svBody);
        $this->assertStringNotContainsString('/roles', $svBody);
        $this->assertStringNotContainsString('/permisos', $svBody);

        $resGt = $this->withSession($gtSession)->get('/home');
        $resGt->assertStatus(200);
        $gtBody = (string) $resGt->getBody();
        $this->assertCorporatePalettePresent($gtBody);
        $this->assertStringContainsString('Guatemala', $gtBody);
        $this->assertStringNotContainsString('El Salvador', $gtBody);
        $this->assertStringNotContainsString('Honduras', $gtBody);
        $this->assertStringNotContainsString('/usuarios', $gtBody);
        $this->assertStringNotContainsString('/roles', $gtBody);
        $this->assertStringNotContainsString('/permisos', $gtBody);
    }

    public function testHtmlContentTypeIsRestoredAfterApiJsonResponse(): void
    {
        $supervisorSession = [
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-SUP-01',
            'usuario'         => 'supervisor_sv',
            'rol'             => 'Supervisor',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ];
        $adminSession = [
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ADM-01',
            'usuario'         => 'admin_sv',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ];
        $asesorSession = [
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ASE-01',
            'usuario'         => 'asesor_sv',
            'rol'             => 'Asesor',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ];

        $resSupApi = $this->withSession($supervisorSession)->get('/api/usuarios');
        $resSupApi->assertStatus(403);
        $this->assertStringContainsString('application/json', $resSupApi->response()->getHeaderLine('Content-Type'));

        $resSupWeb = $this->withSession($supervisorSession)->get('/usuarios');
        $resSupWeb->assertStatus(403);
        $resSupWeb->assertHeader('Content-Type', 'text/html; charset=UTF-8');

        $resAdminApi = $this->withSession($adminSession)->get('/api/usuarios');
        $resAdminApi->assertStatus(404);
        $this->assertStringContainsString('application/json', $resAdminApi->response()->getHeaderLine('Content-Type'));

        $resAdminWeb = $this->withSession($adminSession)->get('/usuarios');
        $resAdminWeb->assertStatus(404);
        $resAdminWeb->assertHeader('Content-Type', 'text/html; charset=UTF-8');

        $testRoutes = [
            ['GET', 'test-m2/agencias', static fn () => 'ok-web', ['filter' => 'supervisor']],
            ['GET', 'api/test-m2/agencias', static fn () => json_encode(['status' => 'ok']), ['filter' => 'supervisor']],
        ];

        $resAsesorApi = $this->withRoutes($testRoutes)->withSession($asesorSession)->get('/api/test-m2/agencias');
        $resAsesorApi->assertStatus(403);
        $this->assertStringContainsString('application/json', $resAsesorApi->response()->getHeaderLine('Content-Type'));

        $resAsesorWeb = $this->withRoutes($testRoutes)->withSession($asesorSession)->get('/test-m2/agencias');
        $resAsesorWeb->assertStatus(403);
        $resAsesorWeb->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function testSupervisorFilterPathNormalizationAndNestedCountryHardening(): void
    {
        $testRoutes = [
            ['GET', 'test-m2/agencias', static fn () => 'ok-web', ['filter' => 'supervisor']],
            ['GET', 'api/test-m2/agencias', static fn () => json_encode(['status' => 'ok']), ['filter' => 'supervisor']],
            ['GET', 'index.php/api/test-m2/agencias', static fn () => json_encode(['status' => 'ok']), ['filter' => 'supervisor']],
            ['POST', 'api/test-m2/agencias', static fn () => json_encode(['status' => 'ok']), ['filter' => 'supervisor']],
        ];

        $resUnauthDoubleSlash = $this->withRoutes($testRoutes)->get('/index.php//api/test-m2/agencias');
        $resUnauthDoubleSlash->assertStatus(401);
        $this->assertSame('unauthenticated', json_decode($resUnauthDoubleSlash->getJSON(), true)['error'] ?? null);

        $resUnauthSingleSlash = $this->withRoutes($testRoutes)->get('/index.php/api/test-m2/agencias');
        $resUnauthSingleSlash->assertStatus(401);
        $this->assertSame('unauthenticated', json_decode($resUnauthSingleSlash->getJSON(), true)['error'] ?? null);

        $adminSession = [
            'isLoggedIn'  => true,
            'rol'         => 'Administrador',
            'id_pais'     => 1,
            'pais_nombre' => 'El Salvador',
        ];
        $resAdminDoubleSlash = $this->withRoutes($testRoutes)->withSession($adminSession)->get('/index.php//api/test-m2/agencias');
        $resAdminDoubleSlash->assertStatus(403);
        $this->assertSame('forbidden', json_decode($resAdminDoubleSlash->getJSON(), true)['error'] ?? null);

        $resAdminSingleSlash = $this->withRoutes($testRoutes)->withSession($adminSession)->get('/index.php/api/test-m2/agencias');
        $resAdminSingleSlash->assertStatus(403);
        $this->assertSame('forbidden', json_decode($resAdminSingleSlash->getJSON(), true)['error'] ?? null);

        $svSupervisor = [
            'isLoggedIn'  => true,
            'rol'         => 'Supervisor',
            'id_pais'     => 1,
            'pais_nombre' => 'El Salvador',
        ];

        $this->withRoutes($testRoutes)->withSession($svSupervisor)->get('/index.php//api/test-m2/agencias')->assertStatus(200);
        $this->withRoutes($testRoutes)->withSession($svSupervisor)->get('/index.php/api/test-m2/agencias')->assertStatus(200);

        $this->withRoutes($testRoutes)->withSession($svSupervisor)->get('/test-m2/agencias?Id_Pais=2')->assertStatus(403);
        $this->withRoutes($testRoutes)->withSession($svSupervisor)->get('/test-m2/agencias?ID_PAIS=2')->assertStatus(403);
        $this->withRoutes($testRoutes)->withSession($svSupervisor)->get('/test-m2/agencias?PAIS=Guatemala')->assertStatus(403);
        $this->withRoutes($testRoutes)->withSession($svSupervisor)->get('/test-m2/agencias?ID_PAIS=1')->assertStatus(200);

        $this->withRoutes($testRoutes)
            ->withSession($svSupervisor)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->withBody('[{"id_pais":2}]')
            ->post('/api/test-m2/agencias')
            ->assertStatus(403);
        $this->withHeaders([])->withBody('');

        $this->withRoutes($testRoutes)
            ->withSession($svSupervisor)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->withBody('{"agencia":{"id_pais":2}}')
            ->post('/api/test-m2/agencias')
            ->assertStatus(403);
        $this->withHeaders([])->withBody('');

        $this->withRoutes($testRoutes)
            ->withSession($svSupervisor)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->withBody('{"agencia":{"Id_Pais":2}}')
            ->post('/api/test-m2/agencias')
            ->assertStatus(403);
        $this->withHeaders([])->withBody('');

        $this->withRoutes($testRoutes)
            ->withSession($svSupervisor)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->withBody('{"agencia":{"id_pais":1}}')
            ->post('/api/test-m2/agencias')
            ->assertStatus(200);
        $this->withHeaders([])->withBody('');
    }
}
