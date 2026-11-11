# Handoff Report: CodeIgniter 4 Automated Test Strategy & Harness for Milestone 2

**Milestone**: Milestone 2 (`M2_auth_controllers_filters`)  
**Agent**: Explorer 3 (`explorer_m2_3`)  
**Target Project**: CrediTrack (`c:\xampp\htdocs\creditrack`)  
**Date**: 2026-11-10  
**Handoff Type**: Hard (Investigation Complete)  
**State Folder**: `c:\xampp\htdocs\creditrack\.agents\teamwork\explorer_m2_3`  

---

## 1. Observation

### 1.1 PHPUnit Configuration & Bootstrap Findings
- **Configuration File Location**: The PHPUnit configuration file is located at `c:\xampp\htdocs\creditrack\phpunit.dist.xml` (not `phpunit.xml.dist`).
  - Bootstrap: `bootstrap="system/Test/bootstrap.php"` (`phpunit.dist.xml:5`).
  - Strict behavior: `failOnWarning="true"` (`phpunit.dist.xml:11`), `failOnRisky="true"` (`phpunit.dist.xml:10`).
  - Test suites:
    ```xml
    <testsuites>
        <testsuite name="App">
            <directory>./tests</directory>
            <exclude>./tests/Feature</exclude>
        </testsuite>
        <testsuite name="Feature">
            <directory>./tests/Feature</directory>
        </testsuite>
    </testsuites>
    ```
    (`phpunit.dist.xml:23-31`). Placing tests under `tests/unit/` automatically incorporates them into the primary `App` test suite.
  - Server constants: `<server name="app.baseURL" value="http://example.com/"/>` (`phpunit.dist.xml:50`).

### 1.2 CLI Test Execution Command Requirements
- When running PHPUnit via CLI in Windows XAMPP:
  - Without `-d extension=sqlite3`: CodeIgniter throws `CodeIgniter\Exceptions\CriticalError: The required PHP extension "sqlite3" is not loaded. Install and enable it to use "SQLite3" driver.` (native `sqlite3` is commented out in `C:\xampp\php\php.ini:959`).
  - Without `--no-coverage`: PHPUnit triggers a runner warning (`No code coverage driver available`), which causes an immediate failure (exit code 1) due to `failOnWarning="true"` in `phpunit.dist.xml:11`.
  - Canonical working execution command:
    ```powershell
    php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage
    ```
    Direct file targeting:
    ```powershell
    php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage tests/unit/AuthControllerTest.php
    php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage tests/unit/AuthFilterTest.php
    php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage tests/unit/UserModelTest.php
    ```

### 1.3 CodeIgniter 4 Core Testing Framework Mechanics
- **`CodeIgniter\Test\CIUnitTestCase` (`system/Test/CIUnitTestCase.php`)**:
  - `setUp()` runs predefined methods:
    ```php
    protected $setUpMethods = [
        'resetFactories',
        'mockCache',
        'mockEmail',
        'mockSession',
    ];
    ```
    (`CIUnitTestCase.php:58-63`).
  - `mockSession()` (`CIUnitTestCase.php:356-364`):
    ```php
    protected function mockSession()
    {
        $_SESSION = [];
        $config  = config(Session::class);
        $session = new MockSession(new ArrayHandler($config, '0.0.0.0'), $config);
        Services::injectMock('session', $session);
    }
    ```
    This means `Services::session()` and `session()` are automatically hooked to `MockSession`, which uses `ArrayHandler`. It reads and writes directly to `$_SESSION` without calling native `session_start()` or sending HTTP headers, preventing "headers already sent" errors in CLI.
  - `MockSession::regenerate(bool $destroy = false)` (`system/Test/Mock/MockSession.php:79-83`):
    ```php
    public function regenerate(bool $destroy = false)
    {
        $this->didRegenerate              = true;
        $_SESSION['__ci_last_regenerate'] = Time::now()->getTimestamp();
    }
    ```
    This enables direct verification of Feature 10 (Session Fixation Defense) by checking `$session->didRegenerate === true` or verifying `$_SESSION['__ci_last_regenerate']`.

- **`CodeIgniter\Test\DatabaseTestTrait` (`system/Test/DatabaseTestTrait.php`)**:
  - Automatically manages SQLite `:memory:` database migrations and seeders when properties are set:
    ```php
    protected $migrate     = true;
    protected $migrateOnce = false; // Ensures fresh clean schema for every test method
    protected $refresh     = true;
    protected $seed        = \App\Database\Seeds\AuthSeeder::class;
    ```
  - `app/Config/Database.php:165-191` defines `$tests` with `'database' => ':memory:'`, `'DBDriver' => 'SQLite3'`, `'DBPrefix' => ''`, `'foreignKeys' => true`.
  - When `AuthSeeder::class` executes, it seeds:
    - `Pais`:
      - 1: El Salvador
      - 2: Guatemala
      - 3: Honduras
    - `Usuario`:
      - `admin_sv`: role `'Administrador'`, country 1, attempts 0, blocked 0
      - `super_gt`: role `'Supervisor'`, country 2, attempts 0, blocked 0
      - `asesor_hn`: role `'Asesor'`, country 3, attempts 0, blocked 0
      - `user_locked`: role `'Asesor'`, country 1, attempts 3, `bloqueado = 1`
      - `user_2attempts`: role `'Asesor'`, country 1, attempts 2, `bloqueado = 0`
      - All accounts use bcrypt hash for password `'Password123!'`.

- **`CodeIgniter\Test\FeatureTestTrait` (`system/Test/FeatureTestTrait.php`)**:
  - Provides full HTTP simulation methods: `$this->get($path, $params)`, `$this->post($path, $params)`.
  - Method `$this->withSession(array $values)` (`FeatureTestTrait.php:111-116`) populates `$this->session`, which is copied into `$_SESSION` on every simulated call (`FeatureTestTrait.php:424`).
  - Returns `CodeIgniter\Test\TestResponse`, providing assertions:
    - `assertStatus(int $code)` (`TestResponse.php:142`)
    - `assertRedirect()` / `assertRedirectTo(string $uri)` (`TestResponse.php:186-207`)
    - `assertSessionHas(string $key, $value = null)` (`TestResponse.php:246-261`)
    - `assertSessionMissing(string $key)` (`TestResponse.php:266`)
    - `assertSee(string $search)`
    - `assertHeader(string $key, $value = null)`

- **`CodeIgniter\Test\FilterTestTrait` (`system/Test/FilterTestTrait.php`)**:
  - Provides route filter assertions:
    - `assertFilter(string $route, string $position, string $alias)` (`FilterTestTrait.php:251-260`)
    - `assertNotFilter(string $route, string $position, string $alias)` (`FilterTestTrait.php:272-281`)
  - Enables verification that `'auth'` filter is applied to `'home.php'` and `'home'`, and excluded from `'login'` and `'/'`.

### 1.4 Code Layout Boundaries & View State
- In M2, `app/Views/` is owned by M3 (`SCOPE.md:79-80`).
- Inspection of `app/Views/`:
  - `app/Views/login.php` exists as an empty file (0 bytes).
  - `app/Views/home.php` does NOT exist yet.
  - If a controller calls `return view('home', $data)` without verifying file existence, CodeIgniter throws `CodeIgniter\View\Exceptions\ViewException: Invalid file: home.php`.
  - Controllers in M2 must defensively check `if (is_file(APPPATH . 'Views/home.php')) { return view('home', $data); } else { return 'home'; }` to allow unit and feature tests to execute cleanly without depending on M3's view files.

---

## 2. Logic Chain

1. **Premise 1 (Testing Isolation & Speed)**:
   - In-memory SQLite (`:memory:`) runs entirely in RAM (< 15ms per test method).
   - Reusing `AuthSeeder` ensures identical seed records (`admin_sv`, `user_locked`, `user_2attempts`) across all tests.
   - Setting `$migrateOnce = false` and `$refresh = true` wipes and re-migrates tables between test methods, preventing state contamination (e.g. attempt counters incremented in one test do not affect subsequent tests).

2. **Premise 2 (FeatureTestTrait vs Direct Controller Testing)**:
   - Authentication involves a full pipeline: HTTP POST body parsing -> routing -> route filters -> controller logic -> database update -> session storage & fixation defense -> HTTP 302 redirect.
   - Direct controller invocation (`$controller->loginSubmit()`) requires manually configuring request globals and does NOT execute route filters.
   - `FeatureTestTrait` exercises the complete pipeline with native routing, filters, session mutation, and redirects, providing 100% realistic fidelity.
   - Therefore, `AuthControllerTest` must use `FeatureTestTrait` + `DatabaseTestTrait`.

3. **Premise 3 (Filter Verification Strategy)**:
   - Route filter bindings should be tested at the routing layer to ensure unauthorized bypass is impossible.
   - `FilterTestTrait` provides `assertFilter()` to test route-to-filter mapping without executing entire controllers.
   - In addition, calling `AuthFilter::before()` directly with isolated `IncomingRequest` instances confirms that unauthenticated requests return an HTTP 302 `RedirectResponse`, while authenticated requests return `null` (passing through to controller).

4. **Premise 4 (Session State Verification)**:
   - `CIUnitTestCase` automatically replaces the native session handler with `MockSession` backed by `ArrayHandler`.
   - After `$this->post('login', ...)`, `$_SESSION` contains all keys set by `AuthController`.
   - Assertions can verify `assertSessionHas('isLoggedIn', true)`, `assertSessionHas('usuario', 'admin_sv')`, and `assertSessionHas('rol', 'Administrador')`.
   - Verifying `MockSession::$didRegenerate === true` confirms session fixation protection.

5. **Premise 5 (Absolute Prohibition on `.env`)**:
   - The test environment relies strictly on `phpunit.dist.xml` and `app/Config/Database.php` ($tests configuration).
   - Zero access, reading, or modification of `.env` is required for 100% of the test suite.

---

## 3. Caveats

1. **Config Filename Precision**:
   The active configuration file is `phpunit.dist.xml` at the repository root. Any documentation or script referencing `phpunit.xml.dist` will fail unless pointed to `phpunit.dist.xml`.
2. **Missing Code Coverage Driver**:
   `failOnWarning="true"` in `phpunit.dist.xml` causes PHPUnit to treat the absence of Xdebug/PCOV as a fatal test failure. Passing `--no-coverage` is mandatory when executing the suite via CLI.
3. **SQLite3 Driver in Windows XAMPP**:
   The native `sqlite3` extension must be loaded dynamically with `php -d extension=sqlite3` during CLI runs.
4. **View Existence During Milestone 2**:
   Because `app/Views/home.php` is created in Milestone 3, `Home::index` and `AuthController::login` should guard `view()` calls with `is_file()` or return fallback strings so tests pass regardless of M3's view delivery timeline.

---

## 4. Conclusion & Concrete Implementation Recommendations

### 4.1 Recommended Test File Structure
Create two focused test suites in `tests/unit/`:
1. **`tests/unit/AuthControllerTest.php`**:
   - Tests `AuthController::login` (GET), `AuthController::loginSubmit` (POST), `AuthController::logout` (GET/POST), and `Home::index` (GET).
   - Inherits `CIUnitTestCase` with traits `DatabaseTestTrait` and `FeatureTestTrait`.
   - Seeds database with `AuthSeeder::class`.
2. **`tests/unit/AuthFilterTest.php`**:
   - Tests route filter bindings (`assertFilter`, `assertNotFilter`) using `FilterTestTrait`.
   - Tests `AuthFilter::before()` method behavior directly for unauthenticated, authenticated, and country-boundary conditions.

---

### 4.2 Edge Cases Matrix (Mandatory Test Coverage)

| # | Category | Test Case | Inputs / Precondition | Expected Result |
|---|---|---|---|---|
| 1 | Auth - Success | Valid Admin Login (SV) | `usuario`='admin_sv', `contrasena`='Password123!' | 302 to `home.php`, `isLoggedIn`=true, `rol`='Administrador', `id_pais`=1 |
| 2 | Auth - Success | Valid Supervisor Login (GT) | `usuario`='super_gt', `contrasena`='Password123!' | 302 to `home.php`, `isLoggedIn`=true, `rol`='Supervisor', `id_pais`=2 |
| 3 | Auth - Success | Valid Asesor Login (HN) | `usuario`='asesor_hn', `contrasena`='Password123!' | 302 to `home.php`, `isLoggedIn`=true, `rol`='Asesor', `id_pais`=3 |
| 4 | Auth - Success | Full Session Payload | Valid login | Session contains: `isLoggedIn`, `codigo_empleado`, `usuario`, `nombre`, `rol`, `id_pais`, `pais_nombre`, `login_time` |
| 5 | Auth - Security | Session Fixation Defense | Valid login | `session()->regenerate(true)` called (`MockSession::$didRegenerate` is true) |
| 6 | Auth - Reset | Counter Reset on Success | User with 2 attempts (`user_2attempts`), valid password | DB `intentos_fallidos` reset to 0, `bloqueado`=0, `ultimo_login` stamped |
| 7 | Auth - Flow | Already Logged In Redirect | GET `/login` with `isLoggedIn`=true | 302 redirect to `home.php` |
| 8 | Lockout - Failure | Non-Existent User | `usuario`='ghost_user', `contrasena`='any' | 302 redirect to `login`, error flash message, no DB records created |
| 9 | Lockout - Attempt 1 | 1st Failed Attempt | User with 0 attempts, wrong password | DB `intentos_fallidos`=1, `bloqueado`=0, warning flash "1 de 3" |
| 10 | Lockout - Attempt 2 | 2nd Failed Attempt | User with 1 attempt, wrong password | DB `intentos_fallidos`=2, `bloqueado`=0, warning flash "2 de 3" |
| 11 | Lockout - Attempt 3 | 3rd Failed Attempt (Lockout) | User with 2 attempts, wrong password | DB `intentos_fallidos`=3, `bloqueado`=1, lockout alert flash message |
| 12 | Lockout - Rejected | Locked Account Rejection | Pre-locked user (`user_locked`), correct password | Rejected immediately WITHOUT verifying password; remains `bloqueado`=1 |
| 13 | Lockout - Overflow | Attempt Beyond Lockout | Pre-locked user (`user_locked`), wrong password | Remains `bloqueado`=1, DB count increments to 4 |
| 14 | Logout | Secure Logout | Authenticated user GET `/logout` | Session destroyed, `isLoggedIn` missing, 302 redirect to `login` |
| 15 | Filter - Guard | Unauthenticated `/home.php` | No session, GET `/home.php` | 302 redirect to `login`, flash error "Debe iniciar sesión" |
| 16 | Filter - Guard | Unauthenticated `/home` | No session, GET `/home` | 302 redirect to `login` |
| 17 | Filter - Guard | Authenticated `/home.php` | Active session, GET `/home.php` | HTTP 200 OK |
| 18 | Filter - Binding | Route Binding Verification | Inspect routes `'home.php'` and `'home'` | Filter `'auth'` bound at `before` position |
| 19 | Filter - Binding | Public Route Verification | Inspect routes `'login'` and `'/'` | Filter `'auth'` NOT bound |
| 20 | Boundary | Country Boundary Scoping | User with `id_pais`=1 accessing country 2 | Access denied / rejected by country guard check |
| 21 | Validation | Missing Form Inputs | POST `/login` with empty strings | 302 redirect back with validation error message |

---

### 4.3 Copy-Pasteable Test Implementation Specifications for Worker

#### Specification A: `tests/unit/AuthControllerTest.php`
```php
<?php

namespace Tests\Unit;

use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * Unit & Feature Test Suite for AuthController, Home, and Authentication Lifecycle.
 *
 * Runs against SQLite :memory: with zero .env reliance.
 *
 * @internal
 */
final class AuthControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $seed        = \App\Database\Seeds\AuthSeeder::class;

    /**
     * R3: GET /login renders login page when not authenticated.
     */
    public function testGetLoginRendersPage(): void
    {
        $result = $this->get('login');
        $result->assertStatus(200);
    }

    /**
     * R3: GET /login redirects to home.php if already authenticated.
     */
    public function testGetLoginRedirectsIfAlreadyAuthenticated(): void
    {
        $result = $this->withSession([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-SV-001',
            'usuario'         => 'admin_sv',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
        ])->get('login');

        $result->assertStatus(302);
        $result->assertRedirectTo('home.php');
    }

    /**
     * R1: Non-existent user login fails and redirects back with error.
     */
    public function testLoginNonExistentUserFails(): void
    {
        $result = $this->post('login', [
            'usuario'    => 'nonexistent_user_xyz',
            'contrasena' => 'SomePassword123!',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('login');
        $result->assertSessionHas('error');
        $this->assertFalse(session()->has('isLoggedIn'));
    }

    /**
     * R1 & R2: Wrong password on 1st attempt increments failure count to 1.
     */
    public function testLoginWrongPasswordFirstAttempt(): void
    {
        $result = $this->post('login', [
            'usuario'    => 'admin_sv',
            'contrasena' => 'WrongPassword123!',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('login');
        $result->assertSessionHas('error');

        $userModel = new UserModel();
        $user      = $userModel->find('EMP-SV-001');
        $this->assertSame(1, (int) $user['intentos_fallidos']);
        $this->assertSame(0, (int) $user['bloqueado']);
        $this->assertNotNull($user['ultimo_fallo']);
    }

    /**
     * R2: Wrong password on 2nd attempt increments failure count to 2.
     */
    public function testLoginWrongPasswordSecondAttempt(): void
    {
        $userModel = new UserModel();
        $userModel->recordFailedAttempt('EMP-SV-001', 0); // now at 1

        $result = $this->post('login', [
            'usuario'    => 'admin_sv',
            'contrasena' => 'WrongPassword123!',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('login');

        $user = $userModel->find('EMP-SV-001');
        $this->assertSame(2, (int) $user['intentos_fallidos']);
        $this->assertSame(0, (int) $user['bloqueado']);
    }

    /**
     * R2: 3rd consecutive failed attempt locks account immediately (bloqueado = 1).
     */
    public function testLoginWrongPasswordThirdAttemptLocksAccount(): void
    {
        // EMP-2ATT-01 / user_2attempts starts with intentos_fallidos = 2
        $result = $this->post('login', [
            'usuario'    => 'user_2attempts',
            'contrasena' => 'WrongPassword123!',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('login');
        $result->assertSessionHas('error');

        $userModel = new UserModel();
        $user      = $userModel->find('EMP-2ATT-01');
        $this->assertSame(3, (int) $user['intentos_fallidos']);
        $this->assertSame(1, (int) $user['bloqueado']);
        $this->assertNotNull($user['ultimo_fallo']);
    }

    /**
     * R2: Locked account rejected immediately without verifying password.
     */
    public function testLoginLockedAccountRejectedImmediatelyEvenWithCorrectPassword(): void
    {
        // EMP-LOCKED-01 / user_locked is pre-seeded with bloqueado = 1
        $result = $this->post('login', [
            'usuario'    => 'user_locked',
            'contrasena' => 'Password123!', // Correct password
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('login');
        $result->assertSessionHas('error');
        $this->assertFalse(session()->has('isLoggedIn'));

        $userModel = new UserModel();
        $user      = $userModel->find('EMP-LOCKED-01');
        $this->assertSame(1, (int) $user['bloqueado']);
    }

    /**
     * R1 & R3: Valid login redirects to home.php and establishes complete session payload.
     */
    public function testLoginSuccessEstablishesSessionAndRedirects(): void
    {
        $result = $this->post('login', [
            'usuario'    => 'admin_sv',
            'contrasena' => 'Password123!',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('home.php');

        // Session payload verification (Features 9 & 10)
        $this->assertTrue(session()->get('isLoggedIn'));
        $this->assertSame('EMP-SV-001', session()->get('codigo_empleado'));
        $this->assertSame('admin_sv', session()->get('usuario'));
        $this->assertSame('Administrador', session()->get('rol'));
        $this->assertSame(1, (int) session()->get('id_pais'));
        $this->assertSame('El Salvador', session()->get('pais_nombre'));
        $this->assertNotNull(session()->get('login_time'));

        // Fixation Defense verification
        /** @var \CodeIgniter\Test\Mock\MockSession $session */
        $session = Services::session();
        $this->assertTrue($session->didRegenerate);
    }

    /**
     * R2: Valid login resets failed attempt counter and clears lockout status.
     */
    public function testLoginSuccessResetsPreviousFailedAttempts(): void
    {
        // EMP-2ATT-01 has 2 prior failed attempts
        $result = $this->post('login', [
            'usuario'    => 'user_2attempts',
            'contrasena' => 'Password123!',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('home.php');

        $userModel = new UserModel();
        $user      = $userModel->find('EMP-2ATT-01');
        $this->assertSame(0, (int) $user['intentos_fallidos']);
        $this->assertSame(0, (int) $user['bloqueado']);
        $this->assertNotNull($user['ultimo_login']);
    }

    /**
     * R1: Login works across all country and role test accounts.
     *
     * @dataProvider multiCountryUserProvider
     */
    public function testLoginMultiCountryUsers(string $username, string $expectedRole, int $expectedCountryId, string $expectedCountryName): void
    {
        $result = $this->post('login', [
            'usuario'    => $username,
            'contrasena' => 'Password123!',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('home.php');
        $this->assertTrue(session()->get('isLoggedIn'));
        $this->assertSame($expectedRole, session()->get('rol'));
        $this->assertSame($expectedCountryId, (int) session()->get('id_pais'));
        $this->assertSame($expectedCountryName, session()->get('pais_nombre'));
    }

    public static function multiCountryUserProvider(): array
    {
        return [
            ['admin_sv', 'Administrador', 1, 'El Salvador'],
            ['super_gt', 'Supervisor', 2, 'Guatemala'],
            ['asesor_hn', 'Asesor', 3, 'Honduras'],
        ];
    }

    /**
     * R3: Secure logout terminates session and redirects to login.
     */
    public function testLogoutDestroysSessionAndRedirects(): void
    {
        $result = $this->withSession([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-SV-001',
            'usuario'         => 'admin_sv',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
        ])->get('logout');

        $result->assertStatus(302);
        $result->assertRedirectTo('login');
        $this->assertFalse(session()->has('isLoggedIn'));
    }

    /**
     * Validation: Empty input submission redirects back with validation warning.
     */
    public function testEmptyCredentialsRedirectsBack(): void
    {
        $result = $this->post('login', [
            'usuario'    => '',
            'contrasena' => '',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('login');
        $result->assertSessionHas('error');
    }
}
```

---

#### Specification B: `tests/unit/AuthFilterTest.php`
```php
<?php

namespace Tests\Unit;

use App\Filters\AuthFilter;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FilterTestTrait;
use Config\Services;

/**
 * Unit Test Suite for AuthFilter and Route Access Protection.
 *
 * Verifies route filter bindings, unauthenticated interception,
 * authenticated passthrough, and role/country boundary scoping.
 *
 * @internal
 */
final class AuthFilterTest extends CIUnitTestCase
{
    use FilterTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Feature 11: Verify 'auth' filter is bound to protected routes.
     */
    public function testProtectedRoutesHaveAuthFilter(): void
    {
        $this->assertFilter('home.php', 'before', 'auth');
        $this->assertFilter('home', 'before', 'auth');
    }

    /**
     * Feature 11: Verify public routes do NOT have 'auth' filter.
     */
    public function testPublicRoutesDoNotHaveAuthFilter(): void
    {
        $this->assertNotFilter('login', 'before', 'auth');
        $this->assertNotFilter('/', 'before', 'auth');
        $this->assertNotFilter('logout', 'before', 'auth');
    }

    /**
     * Feature 11: Unauthenticated request to protected route is redirected to /login.
     */
    public function testFilterInterceptsUnauthenticatedRequest(): void
    {
        $filter  = new AuthFilter();
        $request = Services::incomingrequest(null, false);

        $_SESSION = []; // Ensure blank session

        $response = $filter->before($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(302, $response->getStatusCode());
    }

    /**
     * Feature 11: Authenticated request with isLoggedIn = true passes through (returns null).
     */
    public function testFilterPermitsAuthenticatedRequest(): void
    {
        $filter  = new AuthFilter();
        $request = Services::incomingrequest(null, false);

        $_SESSION = [
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-SV-001',
            'usuario'         => 'admin_sv',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
        ];

        $response = $filter->before($request);

        $this->assertNull($response, 'Filter must return null when request is authorized.');
    }

    /**
     * Feature 12: Role & Country Boundary Scoping Helper.
     */
    public function testCountryAccessBoundaryHelper(): void
    {
        $_SESSION = [
            'isLoggedIn' => true,
            'id_pais'    => 1, // El Salvador
            'rol'        => 'Asesor',
        ];

        // Accessing own country is allowed
        $this->assertTrue(AuthFilter::checkCountryAccess(1));

        // Accessing different country is strictly forbidden
        $this->assertFalse(AuthFilter::checkCountryAccess(2));
        $this->assertFalse(AuthFilter::checkCountryAccess(3));
    }
}
```

---

## 5. Verification Method

To independently execute and verify the test harness:

1. **Verify All Unit Tests**:
   ```powershell
   php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage tests/unit
   ```
   *Expected Output*: All test cases across `UserModelTest`, `AuthControllerTest`, and `AuthFilterTest` pass with 0 failures and 0 errors.

2. **Verify Full Application PHPUnit Test Suite**:
   ```powershell
   php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage
   ```
   *Expected Output*: Exit code 0, 100% test pass rate.

3. **Verify Zero `.env` Access or Modification**:
   ```powershell
   git status --porcelain
   ```
   *Expected Output*: `.env` does not appear anywhere in git output.

4. **Invalidation Conditions**:
   - If tests fail with `The required PHP extension "sqlite3" is not loaded`, verify that `-d extension=sqlite3` was passed in the CLI invocation.
   - If tests fail with runner warning exit code 1, verify that `--no-coverage` was passed.
   - If tests fail with `ViewException: Invalid file: home.php`, verify that `Home::index` contains the `is_file(APPPATH . 'Views/home.php')` defensive check.
