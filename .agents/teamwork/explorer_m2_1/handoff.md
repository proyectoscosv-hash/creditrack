# Milestone 2 Architectural Investigation & Recommendation Report

**Agent**: Explorer M2.1 (`explorer_m2_1`)  
**Mission**: Codebase Architecture, CI4 Patterns, Controller, Filter, and Routing Investigation for Milestone 2 (`M2_auth_controllers_filters`)  
**Parent Conversation ID**: `ef068873-45df-4450-b1af-3ee935c1b975`  
**Working Directory**: `c:\xampp\htdocs\creditrack`  
**State Folder**: `c:\xampp\htdocs\creditrack\.agents\teamwork\explorer_m2_1`  
**Date**: 2026-11-10  
**Handoff Type**: Hard (Investigation Complete)  

---

## 1. Observation

### 1.1 CodeIgniter 4 Environment & Framework Configuration
1. **Framework Version & PHP Requirements**:
   - `system/CodeIgniter.php:56`: `public const CI_VERSION = '4.7.6';`
   - `composer.json:13`: `"php": "^8.2"`, `"psr/log": "^3.0"`, `"laminas/laminas-escaper": "^2.18"`
   - `app/Config/Routing.php:97`: `public bool $autoRoute = false;` — Auto-routing is disabled; all routes must be explicitly declared in `app/Config/Routes.php`.
2. **BaseController Structure**:
   - `app/Controllers/BaseController.php:23`: `abstract class BaseController extends Controller`
   - `app/Controllers/BaseController.php:37-48`: `public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)` calls `parent::initController(...)`. Controllers extending `BaseController` access `$this->request` and `$this->response`.
3. **Session Service & Helpers**:
   - `system/Common.php:1066`: `function session(?string $val = null)` provides global access to `service('session')`. Calling `session()` returns the `CodeIgniter\Session\Session` instance; calling `session('key')` returns `$_SESSION['key']`.
   - `system/Session/Session.php:237`: `public function regenerate(bool $destroy = false)` generates a new session ID for session fixation defense.
   - `system/Session/Session.php:265-272`: `public function destroy()` contains `if (ENVIRONMENT === 'testing') { return; }`. Under testing mode, native `session_destroy()` is bypassed; therefore, explicit clearing via `$session->remove(...)` is necessary before `destroy()` to guarantee session erasure in tests.
   - `system/HTTP/RedirectResponse.php:33`: `public function to(string $uri, ?int $code = null, string $method = 'auto')` automatically wraps relative paths with `site_url($uri)`.
   - `system/HTTP/RedirectResponse.php:130`: `public function with(string $key, $message)` sets flashdata in session.
4. **Filter Interface & Engine**:
   - `system/Filters/FilterInterface.php:38`: `public function before(RequestInterface $request, $arguments = null);`
   - `system/Filters/FilterInterface.php:50`: `public function after(RequestInterface $request, ResponseInterface $response, $arguments = null);`
   - `system/Filters/Filters.php:249-252`: Returning an instance of `ResponseInterface` from `before()` immediately short-circuits execution and sends the response back to the client.
   - `app/Config/Filters.php:27-37`: `$aliases` array holds alias mappings. Alias `'auth' => \App\Filters\AuthFilter::class` must be registered here.
   - `app/Config/Filters.php:73-83`: Global CSRF filter is currently commented out in `$globals['before']`.
5. **Existing Target Files & Test Suite**:
   - `app/Models/UserModel.php`: Contains `findByUsernameWithCountry(string $username): ?array`, `recordFailedAttempt(string $codigoEmpleado, int $currentAttempts): int`, and `resetFailedAttempts(string $codigoEmpleado): bool`. Tested and verified 100% pass rate in Milestone 1.
   - `app/Controllers/Home.php`: Currently returns `view('welcome_message')`.
   - `app/Config/Routes.php:6`: Currently defines only `$routes->get('/', 'Home::index');`.
   - `app/Views/login.php`: Exists as a 0-byte file (Milestone 3 ownership).
   - `app/Views/home.php`: Does not exist yet (Milestone 3 ownership).
   - `tests/feature/AuthTest.php`: 12 automated feature test cases verifying happy paths, empty submissions, whitespace handling, SQL injection resilience, buffer boundaries, case sensitivity, and multibyte strings.
   - `tests/feature/LockoutTest.php`: 12 automated feature test cases verifying 1st attempt (=1), 2nd attempt (=2), 3rd attempt lockout (=3, bloqueado=1), counter reset on valid login, locked account rejection on 4th attempt, and multi-session persistence.
   - `tests/feature/AccessControlTest.php`: 12 automated feature test cases verifying session role scoping (`Administrador`, `Supervisor`, `Asesor`), country ID scoping (`id_pais`, `pais_nombre`), session fixation defense, secure logout, post-logout guards, cross-country boundary isolation (`?pais=2` on `id_pais=1` returning 200 or 403), and incomplete session rejection.
   - `tests/_support/Database/Seeds/TestAuthSeeder.php`: Seeds pre-defined users (`admin_sv`, `supervisor_gt`, `asesor_hn`, `user_warning`, `locked_user`, `user_clean`, `special_pwd`).

---

## 2. Logic Chain

1. **Controller Inheritance & Initialization**:
   - Observation: `BaseController` provides dependency injection of `$request` and `$response` via `initController()`.
   - Deduction: `AuthController` and `Home` must extend `BaseController`. `AuthController` can initialize `$this->userModel = model(UserModel::class);` within `initController()` or inline, avoiding deprecated dynamic properties under PHP 8.2.
2. **Session Contract & Fixation Defense**:
   - Observation: Requirement R1 and `PROJECT.md` line 75 define the required session keys: `isLoggedIn` (bool), `codigo_empleado` (string), `usuario` (string), `nombre` (string), `rol` (string), `id_pais` (int), `pais_nombre` (string), `login_time` (int).
   - Deduction: On successful password validation in `AuthController::loginSubmit()`:
     a) `UserModel::resetFailedAttempts($codigoEmpleado)` resets `intentos_fallidos = 0`, `bloqueado = 0`, and stamps `ultimo_login`.
     b) `session()->regenerate(true)` generates a fresh session ID and destroys the old session ID, mitigating session fixation attacks.
     c) Session payload is written via `session()->set([...])` with explicit type casts (especially `(int) $user['Id_Pais']` to ensure strict integer comparison in tests).
     d) The controller returns `redirect()->to(site_url('home.php'))`.
3. **Lockout Logic Sequencing (Requirement R2)**:
   - Observation: Requirement R2, `PROJECT.md` Feature 7, and `LockoutTest::testT1Lock06` require that locked accounts (`bloqueado == 1` or `intentos_fallidos >= 3`) be rejected immediately without verifying password hashes.
   - Observation: `AuthTest::testT2Auth01` and `testT2Auth02` require that empty or whitespace-only inputs do not alter database attempt counters.
   - Deduction: In `AuthController::loginSubmit()`:
     a) Trim and validate input: if `usuario === ''` or `contrasena === ''`, redirect to `login` with flash error; do NOT touch the database.
     b) Fetch user via `findByUsernameWithCountry($usuario)`. If `null`, redirect to `login` with flash error ("Credenciales incorrectas").
     c) Check lockout: if `(int)$user['bloqueado'] === 1 || (int)$user['intentos_fallidos'] >= 3`, return redirect immediately with lockout message. Do NOT call `password_verify()`.
     d) If not locked, call `password_verify($contrasena, $user['Contrasena'])`.
     e) If false, call `recordFailedAttempt()`. If new count >= 3, flash lockout message; else flash remaining attempts (`3 - newAttempts`). Redirect to `login`.
4. **Session Termination & Test Isolation**:
   - Observation: In `system/Session/Session.php:267`, `session_destroy()` is disabled when `ENVIRONMENT === 'testing'`.
   - Observation: `AccessControlTest::testT1Access05_SecureLogout` checks `$this->assertNull(session('usuario'))` and `$this->assertNull(session('id_pais'))`.
   - Deduction: In `AuthController::logout()`, we must explicitly call `$session->remove(['isLoggedIn', 'codigo_empleado', 'usuario', 'nombre', 'rol', 'id_pais', 'pais_nombre', 'login_time']);` before calling `$session->destroy()`. This guarantees session erasure across all runtime environments.
5. **Route Protection & Boundary Scoping Filter (Requirement R1, R3)**:
   - Observation: `AccessControlTest` tests unauthenticated access to `/home.php` and `/home` (expects 302 redirect to `login`), missing session fields (expects 302 or 200), unknown roles (expects 403, 302, or 200), and cross-country access e.g. `?pais=2` (expects 403 or 200 without leaking foreign country data).
   - Deduction: `AuthFilter::before()` must:
     a) Check `$session->get('isLoggedIn')`. If false, return `redirect()->to(site_url('login'))->with('error', 'Debe iniciar sesión para acceder al sistema.');`.
     b) Validate session completeness: if `empty($session->get('rol')) || empty($session->get('id_pais'))`, return `redirect()->to(site_url('login'))->with('error', 'Sesión incompleta o inválida.');`.
     c) Validate role against allowed roles (`['Administrador', 'Supervisor', 'Asesor']`). If invalid, return HTTP 403 response.
     d) Inspect query or post parameters for country targeting (`pais` or `id_pais`). If present and different from `$session->get('id_pais')` / `$session->get('pais_nombre')`, return HTTP 403 Forbidden.
6. **Route Definitions (`app/Config/Routes.php`)**:
   - Deduction: Routes must map:
     - `GET /` -> `AuthController::login`
     - `GET login` -> `AuthController::login`
     - `POST login` -> `AuthController::loginSubmit`
     - `GET|POST logout` -> `AuthController::logout`
     - `GET|POST home.php` -> `Home::index` with `['filter' => 'auth']`
     - `GET|POST home` -> `Home::index` with `['filter' => 'auth']`
7. **View Fallback Resilience for Milestone 2**:
   - Observation: `app/Views/login.php` is 0 bytes and `app/Views/home.php` does not exist yet (both belong to Milestone 3). However, `AuthTest::testT1Auth06` requests `GET /login` and asserts the existence of `<form`, `name="usuario"`, `name="contrasena"`, and `type="submit"|<button`.
   - Deduction: Both `AuthController::login()` and `Home::index()` should check if the view file exists and has size > 0. If not, they should return an interim fallback HTML string that satisfies the test criteria without stepping on Milestone 3's view implementation.

---

## 3. Caveats

1. **Absolute Prohibition on `.env` (Requirement R5)**:
   - Under no circumstances may `.env` be created, read, or modified. Milestone 1 successfully established direct SQLite configuration for tests and MariaDB configuration for runtime in `app/Config/Database.php`.
2. **View Boundary Discipline**:
   - Source files under `app/Views/` (`login.php` and `home.php`) are owned by Milestone 3. Milestone 2 Worker must NOT implement full CSS styling or custom HTML in `app/Views/`; instead, controllers should render `view('login')` and `view('home', $data)` when non-empty, using inline controller fallbacks during Milestone 2.
3. **SQLite3 CLI Extension on Windows XAMPP**:
   - Running PHPUnit from CLI on Windows XAMPP requires passing `-d extension=sqlite3` (e.g., `php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage`).

---

## 4. Conclusion & Specific Architectural Recommendations for Worker

The Worker agent should implement the following exact code structures:

### Deliverable 1: `app/Filters/AuthFilter.php`
```php
<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    /**
     * Allowed system roles
     */
    protected const ALLOWED_ROLES = [
        'Administrador',
        'Supervisor',
        'Asesor',
    ];

    /**
     * Inspect incoming request for authentication, session completeness,
     * role validation, and country boundary restrictions.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        // 1. Check active session
        if (! $session->get('isLoggedIn')) {
            return redirect()->to(site_url('login'))->with('error', 'Debe iniciar sesión para acceder al sistema.');
        }

        // 2. Validate complete session payload (T2-ACCESS-03)
        $userRole    = $session->get('rol');
        $userCountry = $session->get('id_pais');

        if (empty($userRole) || empty($userCountry)) {
            return redirect()->to(site_url('login'))->with('error', 'Sesión incompleta o inválida.');
        }

        // 3. Validate recognized system role (T2-ACCESS-06)
        if (! in_array($userRole, self::ALLOWED_ROLES, true)) {
            $response = service('response');
            return $response->setStatusCode(403)->setBody('Acceso denegado: Rol no autorizado.');
        }

        // 4. Validate country territorial access boundary (T2-ACCESS-04)
        $requestedCountry = $request->getGet('pais')
            ?? $request->getGet('id_pais')
            ?? $request->getPost('pais')
            ?? $request->getPost('id_pais');

        if ($requestedCountry !== null && $requestedCountry !== '') {
            $userCountryId   = (int) $session->get('id_pais');
            $userCountryName = (string) $session->get('pais_nombre');

            $isPermitted = is_numeric($requestedCountry)
                ? ((int) $requestedCountry === $userCountryId)
                : (strcasecmp((string) $requestedCountry, $userCountryName) === 0);

            if (! $isPermitted) {
                $response = service('response');
                return $response->setStatusCode(403)->setBody('Acceso denegado: Restricción de jurisdicción territorial por país.');
            }
        }

        return null;
    }

    /**
     * Post-processing hook (no changes required)
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
```

### Deliverable 2: `app/Config/Filters.php`
Register the `'auth'` alias under `public array $aliases`:
```php
        'auth'          => \App\Filters\AuthFilter::class,
```
Configure `$filters` for URI pattern protection:
```php
    public array $filters = [
        'auth' => [
            'before' => [
                'home',
                'home/*',
                'home.php',
                'home.php/*',
            ],
        ],
    ];
```

### Deliverable 3: `app/Config/Routes.php`
Replace the single route with:
```php
<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'AuthController::login');
$routes->get('login', 'AuthController::login');
$routes->post('login', 'AuthController::loginSubmit');
$routes->match(['get', 'post'], 'logout', 'AuthController::logout');
$routes->match(['get', 'post'], 'home.php', 'Home::index', ['filter' => 'auth']);
$routes->match(['get', 'post'], 'home', 'Home::index', ['filter' => 'auth']);
```

### Deliverable 4: `app/Controllers/AuthController.php`
```php
<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class AuthController extends BaseController
{
    protected UserModel $userModel;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->userModel = model(UserModel::class);
    }

    /**
     * Render login page or redirect if already authenticated
     */
    public function login()
    {
        if (session()->get('isLoggedIn')) {
            return redirect()->to(site_url('home.php'));
        }

        $viewPath = APPPATH . 'Views/login.php';
        if (is_file($viewPath) && filesize($viewPath) > 0) {
            return view('login');
        }

        // Interim fallback form to satisfy test suite before M3 view deployment
        return '<!DOCTYPE html>'
            . '<html lang="es">'
            . '<head><meta charset="UTF-8"><title>CrediTrack - Iniciar Sesión</title></head>'
            . '<body>'
            . '<form method="post" action="' . site_url('login') . '">'
            . csrf_field()
            . '<label for="usuario">Usuario:</label>'
            . '<input type="text" id="usuario" name="usuario" value="' . esc(old('usuario', '')) . '" required />'
            . '<label for="contrasena">Contraseña:</label>'
            . '<input type="password" id="contrasena" name="contrasena" required />'
            . '<button type="submit">Iniciar Sesión</button>'
            . '</form>'
            . '</body>'
            . '</html>';
    }

    /**
     * Handle login form submission, credential verification, and lockout logic
     */
    public function loginSubmit()
    {
        $usuario = trim((string) $this->request->getPost('usuario'));
        $contrasena = (string) $this->request->getPost('contrasena');

        // 1. Empty or whitespace-only inputs rejection without altering DB counters
        if ($usuario === '' || trim($contrasena) === '') {
            return redirect()->to(site_url('login'))->withInput()->with('error', 'Por favor complete todos los campos requeridos.');
        }

        // 2. Fetch user with joined country
        $user = $this->userModel->findByUsernameWithCountry($usuario);

        // 3. Non-existent username rejection
        if ($user === null) {
            return redirect()->to(site_url('login'))->withInput()->with('error', 'Credenciales incorrectas.');
        }

        // 4. Pre-locked account rejection BEFORE password check (Requirement R2, Feature 7)
        $isLocked = ((int) $user['bloqueado'] === 1 || (int) $user['intentos_fallidos'] >= 3);
        if ($isLocked) {
            return redirect()->to(site_url('login'))->withInput()->with('error', 'Acceso bloqueado tras 3 intentos fallidos consecutivos de inicio de sesión erróneos.');
        }

        // 5. Password verification
        if (! password_verify($contrasena, $user['Contrasena'])) {
            $newAttempts = $this->userModel->recordFailedAttempt($user['Codigo_Empleado'], (int) $user['intentos_fallidos']);
            if ($newAttempts >= 3) {
                return redirect()->to(site_url('login'))->withInput()->with('error', 'Acceso bloqueado tras 3 intentos fallidos consecutivos de inicio de sesión erróneos.');
            }

            $remaining = max(0, 3 - $newAttempts);
            return redirect()->to(site_url('login'))
                ->withInput()
                ->with('error', "Credenciales incorrectas. Intentos restantes: {$remaining}")
                ->with('remaining_attempts', $remaining);
        }

        // 6. Valid credentials: reset counter, stamp ultimo_login, regenerate session ID
        $this->userModel->resetFailedAttempts($user['Codigo_Empleado']);
        session()->regenerate(true);

        // 7. Establish complete session payload
        session()->set([
            'isLoggedIn'      => true,
            'codigo_empleado' => (string) $user['Codigo_Empleado'],
            'usuario'         => (string) $user['Usuario'],
            'nombre'          => (string) $user['Nombre'],
            'rol'             => (string) $user['rol'],
            'id_pais'         => (int) $user['Id_Pais'],
            'pais_nombre'     => (string) $user['pais_nombre'],
            'login_time'      => time(),
        ]);

        // 8. Redirect to home.php (Requirement R3)
        return redirect()->to(site_url('home.php'));
    }

    /**
     * Terminate user session and redirect to login
     */
    public function logout()
    {
        $session = session();
        $session->remove([
            'isLoggedIn',
            'codigo_empleado',
            'usuario',
            'nombre',
            'rol',
            'id_pais',
            'pais_nombre',
            'login_time',
        ]);
        $session->destroy();

        return redirect()->to(site_url('login'))->with('message', 'Sesión cerrada exitosamente.');
    }
}
```

### Deliverable 5: `app/Controllers/Home.php`
```php
<?php

namespace App\Controllers;

class Home extends BaseController
{
    /**
     * Render home landing page confirming session state, role, and country
     */
    public function index()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(site_url('login'))->with('error', 'Debe iniciar sesión para acceder al sistema.');
        }

        $data = [
            'isLoggedIn'      => (bool) session()->get('isLoggedIn'),
            'codigo_empleado' => (string) session()->get('codigo_empleado'),
            'usuario'         => (string) session()->get('usuario'),
            'nombre'          => (string) session()->get('nombre'),
            'rol'             => (string) session()->get('rol'),
            'id_pais'         => (int) session()->get('id_pais'),
            'pais_nombre'     => (string) session()->get('pais_nombre'),
            'login_time'      => (int) session()->get('login_time'),
        ];

        $viewPath = APPPATH . 'Views/home.php';
        if (is_file($viewPath) && filesize($viewPath) > 0) {
            return view('home', $data);
        }

        // Interim fallback view confirming session status before M3 view deployment
        return '<!DOCTYPE html>'
            . '<html lang="es">'
            . '<head><meta charset="UTF-8"><title>CrediTrack - Inicio</title></head>'
            . '<body>'
            . '<h1>Confirmación de Inicio de Sesión Exitoso</h1>'
            . '<p>Estado de la sesión: Activa</p>'
            . '<p>Código Empleado: ' . esc($data['codigo_empleado']) . '</p>'
            . '<p>Usuario: ' . esc($data['usuario']) . '</p>'
            . '<p>Nombre: ' . esc($data['nombre']) . '</p>'
            . '<p>Rol: ' . esc($data['rol']) . '</p>'
            . '<p>País Asignado: ' . esc($data['pais_nombre']) . ' (ID: ' . esc((string) $data['id_pais']) . ')</p>'
            . '<p>Límite de Acceso: Acceso exclusivo a operaciones de ' . esc($data['pais_nombre']) . '.</p>'
            . '<p><a href="' . site_url('logout') . '">Cerrar Sesión</a></p>'
            . '</body>'
            . '</html>';
    }
}
```

---

## 5. Verification Method

Once Worker implements the above components, the implementation can be independently verified via:

```powershell
# 1. Run Feature Auth Tests
php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage tests/feature/AuthTest.php

# 2. Run Feature Lockout Tests
php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage tests/feature/LockoutTest.php

# 3. Run Feature Access Control Tests
php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage tests/feature/AccessControlTest.php

# 4. Run Unit UserModel Tests
php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage tests/unit/UserModelTest.php

# 5. Run Entire Application Test Suite
php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage

# 6. Verify strict .env isolation (no staged or modified .env)
git status --porcelain
```

**Invalidation Conditions**:
- If any test in `AuthTest`, `LockoutTest`, or `AccessControlTest` fails with a 404, check that `Routes.php` has all 6 route entries registered.
- If unauthenticated access to `home.php` does not redirect to `login`, verify that `Filters.php` has alias `'auth'` mapped to `\App\Filters\AuthFilter::class`.
- If logout tests fail on session assertion, verify that `session()->remove([...])` is called before `session()->destroy()`.
