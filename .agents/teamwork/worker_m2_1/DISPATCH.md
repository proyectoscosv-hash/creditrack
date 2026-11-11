# Task Assignment: Worker M2 (Auth Controllers, Filters, Routing & Tests)

Working directory: c:\xampp\htdocs\creditrack
State folder: c:\xampp\htdocs\creditrack\.agents\teamwork\worker_m2_1

## MANDATORY INTEGRITY WARNING
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

## ABSOLUTE PROHIBITION
ABSOLUTE PROHIBITION: DO NOT touch, read, or modify `.env` under any circumstances!
All database and framework configurations must operate without accessing `.env`.

## Write Ownership Boundaries
You exclusively own and may modify:
- `app/Controllers/AuthController.php`
- `app/Controllers/Home.php`
- `app/Filters/AuthFilter.php`
- `app/Config/Filters.php`
- `app/Config/Routes.php`
- `tests/unit/AuthControllerTest.php`
- `tests/unit/AuthFilterTest.php`
- Any additional test files inside `tests/unit/` needed for M2 verification.
DO NOT modify `app/Views/` (owned by M3).
DO NOT touch `.env` under any circumstances.

## Inputs to Read Before Starting
- `c:\xampp\htdocs\creditrack\.agents\teamwork\ORIGINAL_REQUEST.md`
- `c:\xampp\htdocs\creditrack\PROJECT.md`
- `c:\xampp\htdocs\creditrack\.agents\teamwork\sub_orch_m2\SCOPE.md`
- `c:\xampp\htdocs\creditrack\.agents\teamwork\explorer_m2_1\handoff.md` (Architecture, blueprints)
- `c:\xampp\htdocs\creditrack\.agents\teamwork\spec_miner_m2_2\handoff.md` (Specifications, exact strings)
- `c:\xampp\htdocs\creditrack\.agents\teamwork\explorer_m2_3\handoff.md` (Test harness, PHPUnit guidelines)
- `c:\xampp\htdocs\creditrack\app\Models\UserModel.php`
- `c:\xampp\htdocs\creditrack\app\Database\Seeds\AuthSeeder.php`

## Detailed Implementation Requirements

### 1. `app/Filters/AuthFilter.php`
- Implements `CodeIgniter\Filters\FilterInterface`.
- In `before(RequestInterface $request, $arguments = null)`:
  1. Check `$session->get('isLoggedIn')`: if not true, redirect to `site_url('login')` with flash error `'Debe iniciar sesión para acceder al sistema'`.
  2. Validate complete session payload: `rol` and `id_pais` must not be empty. If empty, redirect to `login` with flash error `'Sesión incompleta o inválida'`.
  3. Validate recognized system role: `in_array($userRole, ['Administrador', 'Supervisor', 'Asesor'], true)`. If invalid, return HTTP 403 response.
  4. Validate country territorial access boundary:
     Inspect query and post parameters for country targeting (`pais`, `id_pais`).
     If present and non-empty: verify against `$session->get('id_pais')` (numeric comparison) or `$session->get('pais_nombre')` (case-insensitive string comparison).
     If targeting a different country, return HTTP 403 response with `'Acceso denegado: Restricción de jurisdicción territorial por país.'`.
  5. Return `null` to permit request continuation.
- In `after(...)`: return `null`.

### 2. `app/Config/Filters.php`
- Register alias `'auth' => \App\Filters\AuthFilter::class` in `$aliases`.
- Protect protected routes in `$filters['auth']['before']`:
  `'home'`, `'home/*'`, `'home.php'`, `'home.php/*'`.

### 3. `app/Config/Routes.php`
- Define explicit routes (with auto-routing disabled):
  ```php
  $routes->get('/', 'AuthController::login');
  $routes->get('login', 'AuthController::login');
  $routes->post('login', 'AuthController::loginSubmit');
  $routes->match(['get', 'post'], 'logout', 'AuthController::logout');
  $routes->match(['get', 'post'], 'home.php', 'Home::index', ['filter' => 'auth']);
  $routes->match(['get', 'post'], 'home', 'Home::index', ['filter' => 'auth']);
  ```

### 4. `app/Controllers/AuthController.php`
- Extends `BaseController`.
- In `initController()`: initialize `$this->userModel = model(UserModel::class);`.
- `login()`:
  - If `session()->get('isLoggedIn')` is true, redirect to `site_url('home.php')`.
  - Check if `APPPATH . 'Views/login.php'` exists and has size > 0. If so, return `view('login')`.
  - Otherwise, return interim fallback HTML form with `action="site_url('login')"` containing `csrf_field()`, `<input name="usuario">`, `<input name="contrasena">`, and `<button type="submit">Iniciar Sesión</button>`.
- `loginSubmit()`:
  - Read input: `$usuario = trim((string) $this->request->getPost('usuario'));`, `$contrasena = (string) $this->request->getPost('contrasena');`.
  - If empty or whitespace-only, redirect to `site_url('login')` with flash error `'Por favor complete todos los campos requeridos.'` without touching database.
  - Query user: `$user = $this->userModel->findByUsernameWithCountry($usuario);`.
  - If `$user === null`, redirect to `site_url('login')` with flash error `'Credenciales incorrectas.'`.
  - Pre-locked check: if `(int)$user['bloqueado'] === 1 || (int)$user['intentos_fallidos'] >= 3`, return redirect immediately with flash error `'Acceso bloqueado tras 3 intentos fallidos consecutivos de inicio de sesión erróneos.'`. DO NOT call `password_verify`!
  - Verify password: `password_verify($contrasena, $user['Contrasena'])`.
  - If false:
    - `$newAttempts = $this->userModel->recordFailedAttempt($user['Codigo_Empleado'], (int)$user['intentos_fallidos']);`
    - If `$newAttempts >= 3`: flash error `'Acceso bloqueado tras 3 intentos fallidos consecutivos de inicio de sesión erróneos.'`.
    - Else: flash error `'Credenciales incorrectas. Intentos fallidos: ' . $newAttempts . ' de 3'` and flash `'remaining_attempts'` with `max(0, 3 - $newAttempts)`.
    - Redirect to `site_url('login')`.
  - If true:
    - `$this->userModel->resetFailedAttempts($user['Codigo_Empleado']);`
    - `session()->regenerate(true);` (Session Fixation Defense)
    - Store session payload:
      ```php
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
      ```
    - Redirect to `site_url('home.php')`.
- `logout()`:
  - Explicitly remove session keys:
    `session()->remove(['isLoggedIn', 'codigo_empleado', 'usuario', 'nombre', 'rol', 'id_pais', 'pais_nombre', 'login_time']);`
  - Call `session()->destroy();`.
  - Redirect to `site_url('login')` with flash message `'Sesión cerrada exitosamente.'`.

### 5. `app/Controllers/Home.php`
- Extends `BaseController`.
- `index()`:
  - Check `session()->get('isLoggedIn')`: if not true, redirect to `site_url('login')` with flash error `'Debe iniciar sesión para acceder al sistema.'`.
  - Assemble `$data`: `isLoggedIn`, `codigo_empleado`, `usuario`, `nombre`, `rol`, `id_pais`, `pais_nombre`, `login_time`.
  - If `APPPATH . 'Views/home.php'` exists and has size > 0, return `view('home', $data)`.
  - Otherwise, return interim HTML landing view displaying session confirmation, employee code, name, user, role, assigned country, access boundary statement, and logout link (`site_url('logout')`).

### 6. Automated Unit Tests
- Create `tests/unit/AuthControllerTest.php` and `tests/unit/AuthFilterTest.php` in `tests/unit/`.
- Ensure tests use `:memory:` SQLite, `AuthSeeder::class`, `FeatureTestTrait`, `DatabaseTestTrait`, and `FilterTestTrait`.
- Test ALL features 8-14 and all edge cases.

### 7. Verification Commands
Run the tests using the exact required command line:
```powershell
php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage tests/unit/AuthControllerTest.php
php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage tests/unit/AuthFilterTest.php
php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage tests/unit/UserModelTest.php
php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage
```
Ensure all tests pass with 0 failures, 0 errors, and 0 warnings.
Verify `.env` has NOT been accessed or modified:
```powershell
git status --porcelain
```

Write your completion report to `c:\xampp\htdocs\creditrack\.agents\teamwork\worker_m2_1\handoff.md` and message the orchestrator when finished.


## 2026-11-10T21:53:06Z
Read your task assignment in c:\xampp\htdocs\creditrack\.agents\teamwork\worker_m2_1\DISPATCH.md.
Also read:
- c:\xampp\htdocs\creditrack\.agents\teamwork\ORIGINAL_REQUEST.md
- c:\xampp\htdocs\creditrack\PROJECT.md
- c:\xampp\htdocs\creditrack\.agents\teamwork\sub_orch_m2\SCOPE.md
- c:\xampp\htdocs\creditrack\.agents\teamwork\explorer_m2_1\handoff.md
- c:\xampp\htdocs\creditrack\.agents\teamwork\spec_miner_m2_2\handoff.md
- c:\xampp\htdocs\creditrack\.agents\teamwork\explorer_m2_3\handoff.md

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

ABSOLUTE PROHIBITION: DO NOT touch, read, or modify .env under any circumstances!

Write ownership:
- app/Controllers/AuthController.php
- app/Controllers/Home.php
- app/Filters/AuthFilter.php
- app/Config/Filters.php
- app/Config/Routes.php
- tests/unit/AuthControllerTest.php
- tests/unit/AuthFilterTest.php
DO NOT modify app/Views/ or .env.

Run the test suite:
php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage
Verify git status --porcelain shows no .env changes.
Write your completion report to c:\xampp\htdocs\creditrack\.agents\teamwork\worker_m2_1\handoff.md and message me when finished.
