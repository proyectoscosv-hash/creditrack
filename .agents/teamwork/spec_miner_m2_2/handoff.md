# Milestone 2 Specification Mining Report: Auth, Controllers, and Filters

**Milestone**: Milestone 2: `M2_auth_controllers_filters`  
**Agent**: Specification Miner (`spec_miner_m2_2`)  
**Parent Conversation ID**: `ef068873-45df-4450-b1af-3ee935c1b975`  
**Working Directory**: `c:\xampp\htdocs\creditrack`  
**State Folder**: `c:\xampp\htdocs\creditrack\.agents\teamwork\spec_miner_m2_2`  
**Date**: 2026-11-10  
**Handoff Type**: Hard (Specification Mining Complete)  

---

## Executive Summary

This report delivers the authoritative, extracted specifications, interface contracts, error strings, session structures, and edge cases for **Milestone 2 (Authentication, Controllers, and Route Filters)**.

Findings are mined directly from authoritative specifications:
1. `ORIGINAL_REQUEST.md` (Requirements R1, R2, R3, R4, R5)
2. `PROJECT.md` (System Architecture, Feature Inventory 8–14, Interface Contracts)
3. `SCOPE.md` (Milestone 2 Sub-Orchestrator Scope & Flow)
4. `app/Models/UserModel.php` (Verified Model implementation from Milestone 1)
5. `app/Database/Seeds/` (`UsuarioSeeder.php`, `PaisSeeder.php`, `TestAuthSeeder.php`)
6. `tests/feature/` (`AuthTest.php`, `LockoutTest.php`, `AccessControlTest.php`)

---

## Features Discovered

| # | Category | Feature | Description | Inputs | Outputs | Error Behavior | Discovered Via |
|---|----------|---------|-------------|--------|---------|----------------|----------------|
| 1 | Auth / Routing | Login Page Route (`GET /login`, `GET /`) | Renders the login form view. If user already authenticated, redirects to `home.php`. | HTTP GET request, existing session cookies | HTTP 200 view (`login.php`) OR HTTP 302 redirect to `home.php` | N/A | `PROJECT.md:89`, `SCOPE.md:6`, `AuthTest.php:157` |
| 2 | Auth / Validation | Input Validation & Trimming | Sanitizes and validates `usuario` and `contrasena` inputs before database querying. | POST `usuario`, `contrasena` | Validated strings | Empty or whitespace-only inputs rejected without incrementing DB attempt counters. Flash error set, redirect to `/login`. | `AuthTest.php:179,200`, `SCOPE.md:8` |
| 3 | Auth / Verification | Non-Existent User Rejection | Rejection when `findByUsernameWithCountry` returns null. | Non-existent `usuario` string | HTTP 302 redirect to `/login`, flash error | Does not increment counters, sets flash error `"Credenciales incorrectas"`. No session created. | `AuthTest.php:134`, `SCOPE.md:10` |
| 4 | Security / Lockout | Pre-Locked Account Immediate Rejection | Immediate denial when account has `bloqueado == 1` or `intentos_fallidos >= 3` without verifying password hash. | Username of locked account, any password | HTTP 302 redirect to `/login`, flash lockout message | Bypasses `password_verify` completely. Sets flash error `"Acceso bloqueado tras 3 intentos fallidos consecutivos de inicio de sesión erróneos"`. | `PROJECT.md:10,41`, `SCOPE.md:12`, `LockoutTest.php:188,300` |
| 5 | Security / Hashing | Bcrypt Password Verification | Validates submitted password against stored hash using `password_verify($contrasena, $hash)`. Strictly case-sensitive and UTF-8 safe. | Raw password string, stored bcrypt hash | `bool` (true or false) | If false: invokes failed attempt tracking. Case mismatch or invalid password fails. | `PROJECT.md:42`, `SCOPE.md:14`, `AuthTest.php:254,279` |
| 6 | Security / Lockout | Incremental Failed Attempt Tracking (Attempts 1 & 2) | When password verification fails and total attempts < 3, calls `UserModel::recordFailedAttempt`. | `Codigo_Empleado`, current `intentos_fallidos` | Incremented count (1 or 2), updated `ultimo_fallo` | Account remains unblocked (`bloqueado = 0`). Flash warning `"Credenciales incorrectas. Intentos fallidos: X de 3"`. HTTP 302 redirect to `/login`. | `UserModel.php:65`, `SCOPE.md:15-18`, `LockoutTest.php:21,46` |
| 7 | Security / Lockout | Consecutive 3rd Attempt Account Lockout | On the 3rd consecutive failed attempt, `UserModel::recordFailedAttempt` sets `bloqueado = 1` and `intentos_fallidos = 3`. | `Codigo_Empleado`, current attempts (2) | Count (3), `bloqueado = 1`, updated `ultimo_fallo` | Account locked in DB. Flash error `"Acceso bloqueado tras 3 intentos fallidos consecutivos de inicio de sesión erróneos"`. HTTP 302 redirect to `/login`. | `ORIGINAL_REQUEST.md:15`, `PROJECT.md:40`, `LockoutTest.php:76` |
| 8 | Security / Session | Consecutive Counter Reset on Success | Upon valid credentials, immediately clears failed attempts and unlocks account before session creation. | `Codigo_Empleado` | Resets `intentos_fallidos = 0`, `bloqueado = 0`, updates `ultimo_login` | N/A | `PROJECT.md:39`, `UserModel.php:85`, `LockoutTest.php:112,150` |
| 9 | Security / Defense | Session Fixation Defense | Regenerates active session identifier immediately upon successful authentication. | Active session instance | New session ID (`session()->regenerate(true)`) | Existing unauthenticated/pre-auth session state destroyed and renewed. | `PROJECT.md:15,44`, `SCOPE.md:22`, `AccessControlTest.php:92` |
| 10 | Security / Session | Session Payload Scoping | Writes complete user identity, role, and country association to session. | Verified `$user` associative array | Session keys set: `isLoggedIn`, `codigo_empleado`, `usuario`, `nombre`, `rol`, `id_pais`, `pais_nombre`, `login_time` | All required keys must be populated with correct types. | `PROJECT.md:75-83`, `SCOPE.md:23-31`, `AccessControlTest.php:24,56,72` |
| 11 | Auth / Routing | Post-Authentication Redirection | Redirects authenticated user to `home.php`. | Successful auth event | HTTP 302 redirect to `home.php` | N/A | `ORIGINAL_REQUEST.md:18`, `PROJECT.md:47`, `AuthTest.php:33` |
| 12 | Access Guard | Protected Route Guard (`AuthFilter::before`) | Intercepts requests to `/home.php`, `/home`, or other protected routes. Checks `session('isLoggedIn') === true`. | Incoming HTTP request, session data | Authorized: allows request to pass to controller | If not logged in: HTTP 302 redirect to `/login` with flash error `"Debe iniciar sesión para acceder al sistema"`. | `PROJECT.md:84`, `SCOPE.md:33-36`, `AccessControlTest.php:159,174` |
| 13 | Access Guard | Role & Country Boundary Scoping | Restricts user access to data matching their assigned country (`session('id_pais')`). | Request country parameter (`pais` or `id_pais`), session `id_pais` | Matching country: allowed; Non-matching: blocked | If foreign country queried: returns HTTP 403 Forbidden or filters/excludes foreign country data from response. | `ORIGINAL_REQUEST.md:12`, `PROJECT.md:46`, `AccessControlTest.php:204` |
| 14 | Auth / Session | Secure Logout (`GET/POST /logout`) | Terminates user session, destroys session storage, and redirects to login. | HTTP request to `/logout`, active session | Session destroyed (`session()->destroy()`), HTTP 302 redirect to `/login` | Unsets all session keys (`isLoggedIn`, `usuario`, `id_pais` become null/false). | `PROJECT.md:48,92`, `SCOPE.md:37-39`, `AccessControlTest.php:112,230` |
| 15 | View Delivery | Home Controller (`Home::index`) | Renders `app/Views/home.php` passing authenticated user profile and session parameters. | Active session data | HTTP 200 with rendered `home.php` view | Guarded by `AuthFilter`. | `PROJECT.md:21,91`, `SCOPE.md:36` |

---

## Edge Cases

| # | Feature | Input / Scenario | Observed / Required Behavior |
|---|---------|------------------|------------------------------|
| 1 | Input Sanitization | Empty username and password (`""`, `""`) | Must be rejected immediately without querying database or incrementing `intentos_fallidos`. Returns HTTP 200 or 302, `isLoggedIn = false`. |
| 2 | Input Sanitization | Whitespace-only strings (`"   "`, `"   "`) | Trimmed to empty; rejected without DB updates. `intentos_fallidos` of existing users remains unchanged. |
| 3 | SQL Injection Resilience | Vector in username: `' OR '1'='1' --` | CodeIgniter QueryBuilder prepared statements treat input as literal string; query returns null; user rejected safely; zero database corruption. |
| 4 | Buffer Boundary | Oversized inputs: 500-char username, 10,000-char password | Handled gracefully without PHP memory exhaustion, fatal error, or database truncation exception; rejected safely. |
| 5 | Case Sensitivity | Password casing mismatch (e.g., `'adminpass123!'` instead of `'AdminPass123!'`) | `password_verify()` evaluates to false; counted as failed attempt (`intentos_fallidos` increments by 1); redirects to `/login`. |
| 6 | Multibyte & Symbols | Password with accents and symbols: `'P@$$w0rd!#%^&*()_+ñáéíóú'` | Handled natively by UTF-8 bcrypt without truncation or encoding loss; authenticates successfully. |
| 7 | Sequential Lockout | Consecutive failed attempts: 1st fail, 2nd fail, 3rd fail | 1st: `intentos_fallidos = 1, bloqueado = 0`. 2nd: `intentos_fallidos = 2, bloqueado = 0`. 3rd: `intentos_fallidos = 3, bloqueado = 1`. Account is locked. |
| 8 | Counter Reset on Success | User with 1 or 2 previous failures logs in with valid credentials | `UserModel::resetFailedAttempts()` resets `intentos_fallidos = 0`, `bloqueado = 0`, updates `ultimo_login`. Successful redirect to `home.php`. |
| 9 | Pre-Locked Account Bypass | Pre-locked user (`bloqueado = 1`, `intentos_fallidos >= 3`) submits CORRECT password | Rejected immediately! `password_verify` is NOT executed. `bloqueado` remains 1, session is NOT established, redirected to `/login` with lockout message. |
| 10 | Lockout Flooding | Continuous invalid logins (attempts 4, 5, 6+) against locked account | Account remains locked (`bloqueado = 1`); all attempts rejected; system remains stable without unhandled exceptions. |
| 11 | Cookie Clearing Bypass Defense | Attacker fails 2 attempts, clears session cookies, then fails 3rd attempt | Attempt count is tracked in DB (`Usuario.intentos_fallidos`), NOT in session cookie. Account locks on 3rd attempt regardless of client session resets. |
| 12 | Cross-Account Isolation | Locking `user_clean` after 3 failed attempts | Zero impact on other accounts (`admin_sv`, `supervisor_gt`, etc.). Distinct users authenticate normally. |
| 13 | Phantom User Flooding | Repeated failed logins with non-existent usernames | No new rows created in `Usuario`, existing user counters untouched, generic rejection returned. |
| 14 | Administrative Unlock | Admin manually resets `bloqueado = 0` and `intentos_fallidos = 0` | User account is immediately re-enabled and can log in normally. |
| 15 | Unauthenticated Route Access | Direct GET `/home.php` or `/home` without session | Intercepted by `AuthFilter`. Redirected via HTTP 302 to `/login` with flash error `"Debe iniciar sesión para acceder al sistema"`. |
| 16 | Authenticated User on Login Page | Active user visits `/login` or `/` | `AuthController::login()` detects `session('isLoggedIn') === true` and redirects HTTP 302 to `home.php`. |
| 17 | Cross-Country Boundary Attempt | El Salvador asesor (`id_pais = 1`) accesses `/home.php?pais=2` (Guatemala) | `AuthFilter` intercepts and responds with HTTP 403 Forbidden (or controller suppresses Guatemala data so `País Asignado: Guatemala` never renders). |
| 18 | Incomplete Session State | Forged session with `isLoggedIn = true` but missing `rol` or `id_pais` | Controller/Filter handles missing keys gracefully (HTTP 200 or 302) without PHP Notice or crash. |
| 19 | Unrecognized Role String | Session contains unknown role (e.g. `'SuperHacker'`) | System rejects or safely handles without elevating privileges (HTTP 200 default view, 302, or 403). |
| 20 | Post-Logout Re-entry | User logs out, then hits back button or requests `/home.php` | `session()->destroy()` has purged session. Request intercepted by `AuthFilter` and redirected to `/login`. |

---

## 1. Observation

### 1.1 Specification Source Documents & Evidence
- **Requirement R1 (`ORIGINAL_REQUEST.md:12-13`)**:
  > "Implementar un sistema de inicio de sesión que valide credenciales de usuario contra la base de datos y establezca la sesión identificando el rol y país del usuario, restringiendo el acceso a información no permitida para cada rol y país."
- **Requirement R2 (`ORIGINAL_REQUEST.md:15-16`)**:
  > "Bloquear el acceso tras 3 intentos fallidos consecutivos de inicio de sesión erróneos."
- **Requirement R3 (`ORIGINAL_REQUEST.md:18-19`)**:
  > "Redirigir al usuario autenticado a `home.php`. Crear `home.php` con una vista sencilla que confirme el inicio de sesión exitoso y el funcionamiento correcto del sistema según el rol y país."
- **Requirement R5 (`ORIGINAL_REQUEST.md:24-25`)**:
  > "El equipo tiene estrictamente prohibido acceder, leer o modificar el archivo `.env`. La configuración y conexión a la base de datos debe resolverse sin tocar dicho archivo."

### 1.2 Database Schema & Model State (`app/Models/UserModel.php`)
- Primary table: `Usuario`, primary key: `Codigo_Empleado` (string).
- Foreign key: `Id_Pais` references `Pais.Id_pais`.
- Security columns verified:
  - `intentos_fallidos`: `INT(11) NOT NULL DEFAULT 0`
  - `bloqueado`: `TINYINT(1) NOT NULL DEFAULT 0`
  - `ultimo_fallo`: `DATETIME NULL`
  - `ultimo_login`: `DATETIME NULL`
- Methods available in `UserModel`:
  1. `findByUsernameWithCountry(string $username): ?array` (joins `Pais`, alias `Pais.Pais as pais_nombre`).
  2. `recordFailedAttempt(string $codigoEmpleado, int $currentAttempts): int` (increments count, sets `bloqueado = 1` if count >= 3, updates `ultimo_fallo`, returns new count).
  3. `resetFailedAttempts(string $codigoEmpleado): bool` (resets `intentos_fallidos = 0`, `bloqueado = 0`, updates `ultimo_login`).
  4. `unlockAccount(string $codigoEmpleado): bool` (resets `intentos_fallidos = 0`, `bloqueado = 0` without updating `ultimo_login`).

### 1.3 Exact Session Specification
The session payload upon successful login must contain the following keys and types (`PROJECT.md:75-83`, `SCOPE.md:23-31`, `AuthTest.php:35-40`, `AccessControlTest.php:31-48,59-67`):
- `isLoggedIn`: `bool` (`true`)
- `codigo_empleado`: `string` (`$user['Codigo_Empleado']`, e.g. `'EMP-SV-001'`)
- `usuario`: `string` (`$user['Usuario']`, e.g. `'admin_sv'`)
- `nombre`: `string` (`$user['Nombre']`, e.g. `'Carlos Administrador SV'`)
- `rol`: `string` (`$user['rol']`, e.g. `'Administrador'`, `'Supervisor'`, `'Asesor'`)
- `id_pais`: `int` (`(int)$user['Id_Pais']`, e.g. `1`, `2`, `3`)
- `pais_nombre`: `string` (`$user['pais_nombre']`, e.g. `'El Salvador'`, `'Guatemala'`, `'Honduras'`)
- `login_time`: `int` (`time()`, Unix timestamp)

### 1.4 Exact Flash Message Strings & Redirects
1. **User not found**:
   - Flash: `session()->setFlashdata('error', 'Credenciales incorrectas');`
   - Redirect: `redirect()->to('/login');` (HTTP 302)
2. **Account already locked (`bloqueado == 1` or `intentos_fallidos >= 3`)**:
   - Flash: `session()->setFlashdata('error', 'Acceso bloqueado tras 3 intentos fallidos consecutivos de inicio de sesión erróneos');`
   - Redirect: `redirect()->to('/login');` (HTTP 302)
3. **Password verification failure (attempts 1 or 2)**:
   - Flash: `session()->setFlashdata('warning', 'Credenciales incorrectas. Intentos fallidos: ' . $newAttempts . ' de 3');`
   - Redirect: `redirect()->to('/login');` (HTTP 302)
4. **Password verification failure (3rd consecutive attempt -> triggers lockout)**:
   - Flash: `session()->setFlashdata('error', 'Acceso bloqueado tras 3 intentos fallidos consecutivos de inicio de sesión erróneos');`
   - Redirect: `redirect()->to('/login');` (HTTP 302)
5. **Unauthenticated access to protected routes intercepted by AuthFilter**:
   - Flash: `session()->setFlashdata('error', 'Debe iniciar sesión para acceder al sistema');`
   - Redirect: `redirect()->to('/login');` (HTTP 302)
6. **Successful authentication**:
   - Redirect: `redirect()->to('/home.php');` (HTTP 302)
7. **Logout**:
   - Action: `session()->destroy();`
   - Redirect: `redirect()->to('/login');` (HTTP 302)
8. **Already authenticated user visiting `/login` or `/`**:
   - Redirect: `redirect()->to('/home.php');` (HTTP 302)

### 1.5 Route & Filter Architecture
- **Routes (`app/Config/Routes.php`)**:
  ```php
  $routes->get('/', 'AuthController::login');
  $routes->get('login', 'AuthController::login');
  $routes->post('login', 'AuthController::loginSubmit');
  $routes->get('logout', 'AuthController::logout');
  $routes->post('logout', 'AuthController::logout');
  $routes->get('home.php', 'Home::index', ['filter' => 'auth']);
  $routes->get('home', 'Home::index', ['filter' => 'auth']);
  ```
- **Filters (`app/Config/Filters.php`)**:
  - Alias: `'auth' => \App\Filters\AuthFilter::class`
  - Applied explicitly to protected route endpoints (`home.php`, `home`).

---

## 2. Logic Chain

1. **Lockout Architecture (Requirement R2)**:
   - When a user submits credentials, the application queries `UserModel::findByUsernameWithCountry($usuario)`.
   - If the user does not exist, it must fail safely with `"Credenciales incorrectas"` without leaking whether the username exists.
   - If the user exists, the application immediately inspects `(int)$user['bloqueado'] === 1 || (int)$user['intentos_fallidos'] >= 3`.
   - If locked, `password_verify` is NOT called. Calling `password_verify` on locked accounts would consume server CPU and leak side-channel timing differences. The lockout error message is flashed and the user is redirected to `/login`.
   - If unblocked, `password_verify($contrasena, $user['Contrasena'])` is invoked.
   - If invalid: `recordFailedAttempt` is invoked. If the new attempt count is >= 3, lockout error is flashed; otherwise, a warning with remaining attempts is flashed.
   - If valid: `resetFailedAttempts` is called, resetting `intentos_fallidos = 0`, `bloqueado = 0`, and updating `ultimo_login`.

2. **Session Establishment & Fixation Defense (Requirement R1, R3)**:
   - `session()->regenerate(true)` must be executed before setting session keys to prevent session fixation attacks where an attacker pre-sets a session identifier.
   - Session data must be typed cleanly (integers for IDs, booleans for auth flag, strings for display/role names).
   - Once session is set, redirect HTTP 302 to `home.php`.

3. **Country & Role Boundary Scoping (Requirement R1)**:
   - `AuthFilter::before()` verifies authentication first (`session('isLoggedIn') === true`).
   - If unauthenticated, redirect to `/login`.
   - To satisfy Requirement R1 ("restringiendo el acceso a información no permitida para cada rol y país") and test `testT2Access04_CrossCountryBoundaryIsolationEnforcement`:
     - If the incoming request has a `pais` query parameter (e.g. `?pais=2`) and its value differs from `session('id_pais')`:
       - `AuthFilter` can immediately reject with HTTP 403 Forbidden (`service('response')->setStatusCode(403)->setBody('Acceso denegado: fuera de la jurisdicción de su país asignado');`).
       - Alternatively, `Home::index` strictly scopes data queries using `session('id_pais')` and ignores/rejects foreign country parameters.
       - Returning 403 directly from `AuthFilter` on unauthorized foreign country queries provides an airtight security boundary.

4. **Environment Isolation Compliance (Requirement R5)**:
   - Milestone 2 code (`AuthController`, `Home`, `AuthFilter`, `Filters.php`, `Routes.php`) does NOT read, touch, or reference `.env`.
   - Database operations continue using native CodeIgniter `$this->userModel` or `$db->table()`, which connects natively via `app/Config/Database.php`.

---

## 3. Caveats & Assumptions

1. **CSRF Protection in Test Mode vs Production**:
   - In CodeIgniter 4 `FeatureTestTrait`, CSRF is automatically bypassed unless explicitly enabled.
   - In `app/Config/Filters.php`, `'csrf'` is configured under `$aliases`. For web forms, `<?= csrf_field() ?>` must be present in views (Milestone 3). In `AuthController::loginSubmit`, standard CSRF validation will apply in production.
2. **View Dependency**:
   - `login.php` and `home.php` views belong to Milestone 3 (`M3_views_ui`).
   - In Milestone 2, `AuthController::login()` renders `view('login')` (or returns an HTML stub if the view is empty). Since `login.php` currently exists as an empty file, Milestone 2 should ensure `login()` returns a basic form or checks if view exists so unit tests and feature tests pass.
3. **Password Compatibility**:
   - Test seeders (`UsuarioSeeder.php` and `TestAuthSeeder.php`) use bcrypt hashes generated via `password_hash($password, PASSWORD_BCRYPT)`.
   - `password_verify` handles cost factors (10-12) natively.
4. **Flashdata Lifetime**:
   - CodeIgniter flashdata persists for one redirect cycle (`session()->setFlashdata(...)`).
   - Controllers should use `return redirect()->to('/login')->with('error', ...)` or `session()->setFlashdata('error', ...); return redirect()->to('/login');`. Both are fully compatible.

---

## 4. Conclusion

All specifications, logic branches, data structures, and constraints for Milestone 2 (`M2_auth_controllers_filters`) have been extracted and verified against authoritative requirements:

1. **AuthController Contract**:
   - `login()`: Check `session('isLoggedIn')`. If true, redirect to `home.php`. Else return `view('login')`.
   - `loginSubmit()`: Validate non-empty inputs -> `UserModel::findByUsernameWithCountry` -> Check locked state -> Verify password -> Track/Reset attempts -> `session()->regenerate(true)` -> Set session payload -> Redirect to `home.php`.
   - `logout()`: `session()->destroy()` -> Redirect to `/login`.
2. **Home Controller Contract**:
   - `index()`: Pass session data (`usuario`, `nombre`, `codigo_empleado`, `rol`, `id_pais`, `pais_nombre`) to `view('home', $data)`.
3. **AuthFilter Contract**:
   - Check `session('isLoggedIn') === true`. If false, redirect to `/login` with flash error.
   - Enforce country boundary: block any foreign country request targeting a country differing from `session('id_pais')` with HTTP 403 Forbidden.
4. **Config Files**:
   - `app/Config/Filters.php`: Register `'auth'` alias.
   - `app/Config/Routes.php`: Map `/`, `login`, `logout`, `home.php`, `home`.

---

## 5. Verification Method

To verify the implementation of Milestone 2 once written by the worker:

```powershell
# 1. Run all Feature and Unit tests using SQLite :memory:
php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage tests/feature/AuthTest.php
php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage tests/feature/LockoutTest.php
php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage tests/feature/AccessControlTest.php

# 2. Run full test suite
php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage

# 3. Verify .env is strictly untouched
git status --porcelain
```
