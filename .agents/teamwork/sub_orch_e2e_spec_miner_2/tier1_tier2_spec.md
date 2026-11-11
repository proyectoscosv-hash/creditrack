# E2E Test Specification: Tier 1 (Feature Coverage) & Tier 2 (Boundary & Corner Cases)
**Project**: CrediTrack Authentication & Access Control System  
**Track**: E2E Testing Track  
**Author**: Test Requirements Spec Miner (`sub_orch_e2e_spec_miner_2`)  
**Target File**: `c:\xampp\htdocs\creditrack\.agents\teamwork\sub_orch_e2e_spec_miner_2\tier1_tier2_spec.md`  
**Status**: Authoritative Technical Specification  
**Date**: 2026-11-10  

---

## 1. Executive Summary & Overview

This document specifies the complete, authoritative Tier 1 (Feature Coverage) and Tier 2 (Boundary and Corner Cases) test requirements for the CrediTrack Authentication and Access Control System. It operationalizes requirements **R1** through **R5** from `ORIGINAL_REQUEST.md` and aligns strictly with the system contracts defined in `PROJECT.md` and `SCOPE.md`.

To guarantee robust coverage, this specification mandates:
- **Tier 1 (Feature Coverage)**: Direct, happy-path and foundational negative functional verification across all 5 core requirement areas (Minimum 5 test cases per area; 31 test cases specified).
- **Tier 2 (Boundary & Corner Cases)**: Rigorous edge case testing, including zero/blank inputs, buffer limits, injection resistance, multi-user isolation, session fixation defense, DB counter persistence across cookie deletions, cross-country privilege boundaries, responsive rendering, and corporate palette compliance (Minimum 5 test cases per area; 30 test cases specified).
- **Total Test Cases Specified**: **61 Test Cases** (31 Tier 1 + 30 Tier 2).

All tests are designed as opaque-box feature tests using CodeIgniter 4's PHPUnit integration (`CIUnitTestCase`, `FeatureTestTrait`, `DatabaseTestTrait`) running against an isolated in-memory SQLite database (`:memory:`), guaranteeing **100% independence from `.env` (R5)**.

---

## 2. Test Execution Architecture & Environmental Isolation

### 2.1 Framework & Traits
- **Runner**: PHPUnit 10/11 invoked via `vendor/bin/phpunit` or `php vendor/phpunit/phpunit/phpunit`.
- **Base Test Case**: `CodeIgniter\Test\CIUnitTestCase`.
- **HTTP Simulation**: `CodeIgniter\Test\FeatureTestTrait` (provides `$this->get()`, `$this->post()`, `$this->withSession()`, `$this->withHeaders()`, `$result->assertStatus()`, `$result->assertRedirectTo()`, `$result->assertSee()`).
- **Database Simulation**: `CodeIgniter\Test\DatabaseTestTrait` (provides `$this->seeInDatabase()`, `$this->dontSeeInDatabase()`, `$this->grabFromDatabase()`, `$this->seed()`).

### 2.2 In-Memory SQLite Database Isolation (R5 Compliance)
- **Database Group**: `$tests` in `app/Config/Database.php`.
- **Database Driver**: `SQLite3` with `'database' => ':memory:'`.
- **Strict Constraint**: Under no circumstances shall `.env` be referenced, read, loaded, or required.
- **Prefix Handling**: Configured with prefix `db_` per CI4 test standard, or accessed without prefix if tables are created directly in test initialization.

### 2.3 Test Suite File Layout
```
tests/
├── _support/
│   └── Database/
│       └── Seeds/
│           └── TestAuthSeeder.php          # DDL and test seed fixtures
└── Feature/
    ├── AuthTest.php                        # R1 Authentication & Login tests
    ├── AccessControlTest.php               # R1 Role & Country boundary tests
    ├── LockoutTest.php                     # R2 Consecutive counter & lockout tests
    └── ViewUITest.php                      # R3 Landing/Redirect & R4 Palette/Attribution tests
```

---

## 3. Database Schema & Test Fixture Seeding (`TestAuthSeeder`)

### 3.1 SQLite DDL (`:memory:`)
```sql
CREATE TABLE IF NOT EXISTS Pais (
    Id_pais INTEGER PRIMARY KEY AUTOINCREMENT,
    Pais TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS Usuario (
    Codigo_Empleado TEXT PRIMARY KEY,
    Nombre TEXT NOT NULL,
    Usuario TEXT NOT NULL UNIQUE,
    Contrasena TEXT NOT NULL,
    Id_Pais INTEGER NOT NULL,
    telefono TEXT,
    rol TEXT NOT NULL,
    intentos_fallidos INTEGER NOT NULL DEFAULT 0,
    bloqueado INTEGER NOT NULL DEFAULT 0,
    ultimo_fallo TEXT NULL,
    ultimo_login TEXT NULL,
    creado_en TEXT NULL,
    actualizado_en TEXT NULL,
    FOREIGN KEY (Id_Pais) REFERENCES Pais(Id_pais)
);
```

### 3.2 Standard Test Seed Dataset
All test passwords use standard bcrypt hashes generated via `password_hash($password, PASSWORD_BCRYPT)`:

| User Key / Alias | `Codigo_Empleado` | `Nombre` | `Usuario` | Plain Password | `rol` | `Id_Pais` (`Pais`) | `intentos_fallidos` | `bloqueado` | Purpose |
|---|---|---|---|---|---|---|---|---|---|
| `admin_sv` | `EMP-ADM-01` | Carlos Admin | `admin_sv` | `AdminPass123!` | `Administrador` | 1 (El Salvador) | 0 | 0 | Tier 1 Admin login & session |
| `super_gt` | `EMP-SUP-01` | Beatriz Supervisor | `super_gt` | `SuperPass123!` | `Supervisor` | 2 (Guatemala) | 0 | 0 | Tier 1 Supervisor login & country 2 |
| `asesor_hn` | `EMP-ASE-01` | Alejandro Asesor | `asesor_hn` | `AsesorPass123!` | `Asesor` | 3 (Honduras) | 0 | 0 | Tier 1 Asesor login & country 3 |
| `user_warning` | `EMP-WRN-01` | Wendy Advertencia | `user_warning` | `WarnPass123!` | `Asesor` | 1 (El Salvador) | 2 | 0 | 2 prior failed attempts (reset test) |
| `locked_user` | `EMP-LCK-01` | Lorenzo Bloqueado | `locked_user` | `LockedPass123!`| `Asesor` | 1 (El Salvador) | 3 | 1 | Pre-locked account (4th attempt test)|
| `special_pwd` | `EMP-SPC-01` | Silvia Especial | `special_pwd` | `P@$$w0rd!#%^&*()_+ñáéíóú` | `Asesor` | 1 (El Salvador) | 0 | 0 | Special/multibyte chars |
| `xss_user` | `EMP-XSS-01` | `Juan <script>alert(1)</script>` | `xss_user` | `XssPass123!` | `Asesor` | 1 (El Salvador) | 0 | 0 | XSS escaping assertion |
| `user_clean` | `EMP-CLN-01` | Clara Limpia | `user_clean` | `CleanPass123!` | `Supervisor` | 1 (El Salvador) | 0 | 0 | Clean state lockout testing |

---

## 4. Public API & Routing Contracts

| Method | Route | Controller / Action | Auth Required | Expected Output / Behavior |
|---|---|---|---|---|
| `GET` | `/login` | `AuthController::login` | No (redirects if logged in) | Renders `app/Views/login.php` (HTTP 200) |
| `POST` | `/login` | `AuthController::loginSubmit` | No | Validates credentials; 302 to `home.php` or 302 to `/login` with error |
| `GET` | `/home.php` | `Home::index` | Yes (`AuthFilter`) | Renders `app/Views/home.php` (HTTP 200) or 302 to `/login` |
| `GET` | `/home` | `Home::index` | Yes (`AuthFilter`) | Route alias for `home.php` |
| `GET` / `POST` | `/logout` | `AuthController::logout` | No | Destroys session; 302 redirect to `/login` |

---

## 5. Tier 1 Test Specification: Feature Coverage

Tier 1 verifies nominal baseline functional features (minimum 5 test cases per core area).

### 5.1 Area 1: Authentication & Login (R1)
**Test File**: `tests/Feature/AuthTest.php`

#### `T1-AUTH-01`: Valid Administrator Login
- **Description**: Authenticate with valid credentials for an active user with role `Administrador`.
- **Inputs**:
  - Method: `POST`
  - URL: `/login`
  - Body: `['usuario' => 'admin_sv', 'contrasena' => 'AdminPass123!']`
- **Expected Assertions**:
  1. HTTP Status: `302 Found`
  2. Redirect URL: matches `.*home\.php$` (or redirect to `/home.php`)
  3. Session Variables:
     - `session('isLoggedIn') === true`
     - `session('usuario') === 'admin_sv'`
     - `session('codigo_empleado') === 'EMP-ADM-01'`
     - `session('rol') === 'Administrador'`
     - `session('id_pais') === 1`
  4. Database Assertions:
     - `Usuario` table: `Usuario='admin_sv'` has `intentos_fallidos = 0`, `bloqueado = 0`, `ultimo_login` is not null.

#### `T1-AUTH-02`: Valid Supervisor Login
- **Description**: Authenticate with valid credentials for an active user with role `Supervisor`.
- **Inputs**:
  - Method: `POST`
  - URL: `/login`
  - Body: `['usuario' => 'super_gt', 'contrasena' => 'SuperPass123!']`
- **Expected Assertions**:
  1. HTTP Status: `302 Found`
  2. Redirect URL: matches `.*home\.php$`
  3. Session Variables:
     - `session('isLoggedIn') === true`
     - `session('rol') === 'Supervisor'`
     - `session('id_pais') === 2`
     - `session('pais_nombre') === 'Guatemala'`

#### `T1-AUTH-03`: Valid Asesor Login
- **Description**: Authenticate with valid credentials for an active user with role `Asesor`.
- **Inputs**:
  - Method: `POST`
  - URL: `/login`
  - Body: `['usuario' => 'asesor_hn', 'contrasena' => 'AsesorPass123!']`
- **Expected Assertions**:
  1. HTTP Status: `302 Found`
  2. Redirect URL: matches `.*home\.php$`
  3. Session Variables:
     - `session('isLoggedIn') === true`
     - `session('rol') === 'Asesor'`
     - `session('id_pais') === 3`
     - `session('pais_nombre') === 'Honduras'`

#### `T1-AUTH-04`: Invalid Password Rejection (Single Failure)
- **Description**: Attempt login with an existing valid username but an incorrect password.
- **Inputs**:
  - Method: `POST`
  - URL: `/login`
  - Body: `['usuario' => 'admin_sv', 'contrasena' => 'WrongPassword!']`
- **Expected Assertions**:
  1. HTTP Status: `302 Found`
  2. Redirect URL: matches `.*login$`
  3. Session State: `session('isLoggedIn')` is null or false.
  4. Flash Data / Error Message: Contains "Credenciales incorrectas" or warning notice indicating remaining attempts.
  5. Database Assertions: `Usuario` table: `Usuario='admin_sv'` has `intentos_fallidos = 1`, `bloqueado = 0`.

#### `T1-AUTH-05`: Non-Existent Username Rejection
- **Description**: Attempt login with a username that does not exist in the database.
- **Inputs**:
  - Method: `POST`
  - URL: `/login`
  - Body: `['usuario' => 'usuario_fantasma', 'contrasena' => 'Cualquiera123!']`
- **Expected Assertions**:
  1. HTTP Status: `302 Found`
  2. Redirect URL: matches `.*login$`
  3. Session State: No session created (`session('isLoggedIn')` is null/false).
  4. Flash Data / Error Message: Generic "Credenciales incorrectas" (prevents username enumeration).
  5. Database Assertions: No existing records modified in `Usuario` table.

#### `T1-AUTH-06`: Login Form View Rendering
- **Description**: Retrieve the login page via HTTP GET.
- **Inputs**:
  - Method: `GET`
  - URL: `/login`
- **Expected Assertions**:
  1. HTTP Status: `200 OK`
  2. View Elements:
     - Contains `<form action="...login..." method="post">`
     - Contains `<input type="text" name="usuario"` or equivalent
     - Contains `<input type="password" name="contrasena"` or equivalent
     - Contains submit button (`<button type="submit"` or `<input type="submit"`)

---

### 5.2 Area 2: Session Role & Country Scoping & Access Boundaries (R1)
**Test File**: `tests/Feature/AccessControlTest.php`

#### `T1-ACCESS-01`: Session Role Scoping on Login
- **Description**: Verify that the exact role string from the database is placed in the session upon successful login.
- **Inputs**:
  - Method: `POST` `/login` with credentials for `admin_sv` (`Administrador`), `super_gt` (`Supervisor`), and `asesor_hn` (`Asesor`).
- **Expected Assertions**:
  - Session key `rol` equals exactly `'Administrador'`, `'Supervisor'`, and `'Asesor'` respectively.
  - Session key `codigo_empleado` matches respective `Codigo_Empleado`.

#### `T1-ACCESS-02`: Country Association & Country ID Scoping
- **Description**: Verify user assigned to El Salvador (`Id_Pais = 1`) has accurate country ID and country name populated in session.
- **Inputs**:
  - Method: `POST` `/login` with credentials for `admin_sv`.
- **Expected Assertions**:
  - `session('id_pais') === 1`
  - `session('pais_nombre') === 'El Salvador'`

#### `T1-ACCESS-03`: Multi-Country Scoping (Guatemala and Honduras)
- **Description**: Verify country separation across different user logins.
- **Inputs**:
  - Login sequence for `super_gt` (Country 2) and `asesor_hn` (Country 3).
- **Expected Assertions**:
  - User 1 session: `id_pais === 2`, `pais_nombre === 'Guatemala'`.
  - User 2 session: `id_pais === 3`, `pais_nombre === 'Honduras'`.

#### `T1-ACCESS-04`: Session Fixation Defense (`session()->regenerate(true)`)
- **Description**: Verify that pre-authentication session ID is destroyed and replaced with a new cryptographic session ID upon successful login.
- **Inputs**:
  - Start session with dummy ID/cookie, submit valid login `POST /login`.
- **Expected Assertions**:
  - Active session ID differs from initial session ID (`session_id() !== $oldSessionId`).
  - Session retains newly authenticated credentials.

#### `T1-ACCESS-05`: Secure Logout (`GET /logout` or `POST /logout`)
- **Description**: Verify logout action invalidates all session data and redirects to login.
- **Inputs**:
  - Active session with `isLoggedIn = true`, invoke `GET /logout` or `POST /logout`.
- **Expected Assertions**:
  1. HTTP Status: `302 Found`
  2. Redirect URL: matches `.*login$`
  3. Session State: `session('isLoggedIn')` is null or false, `session('usuario')` is null, `session('id_pais')` is null.

#### `T1-ACCESS-06`: Authenticated User Visiting `/login` Redirected to `home.php`
- **Description**: An already authenticated user accessing `/login` should be automatically redirected to `home.php`.
- **Inputs**:
  - `GET /login` with session `['isLoggedIn' => true, 'usuario' => 'admin_sv', 'rol' => 'Administrador', 'id_pais' => 1]`.
- **Expected Assertions**:
  1. HTTP Status: `302 Found`
  2. Redirect URL: matches `.*home\.php$`

---

### 5.3 Area 3: 3-Failed Attempt Lockout & Consecutive Counter (R2)
**Test File**: `tests/Feature/LockoutTest.php`

#### `T1-LOCK-01`: First Failed Attempt Increments Counter to 1
- **Description**: Single incorrect password submission on clean user.
- **Inputs**:
  - POST `/login` with `usuario = 'user_clean'`, `contrasena = 'BadPass1'`.
- **Expected Assertions**:
  1. HTTP Status: `302 Found` to `/login`
  2. DB Assertion: `seeInDatabase('Usuario', ['Usuario' => 'user_clean', 'intentos_fallidos' => 1, 'bloqueado' => 0])`
  3. Response/Flash Message: Warning indicates remaining attempts (2 intentos restantes).

#### `T1-LOCK-02`: Second Consecutive Failed Attempt Increments Counter to 2
- **Description**: Second consecutive incorrect password submission on same user.
- **Inputs**:
  - POST `/login` with `usuario = 'user_clean'`, `contrasena = 'BadPass2'`.
- **Expected Assertions**:
  1. HTTP Status: `302 Found` to `/login`
  2. DB Assertion: `seeInDatabase('Usuario', ['Usuario' => 'user_clean', 'intentos_fallidos' => 2, 'bloqueado' => 0])`
  3. Response/Flash Message: Warning indicates remaining attempts (1 intento restante).

#### `T1-LOCK-03`: Third Consecutive Failed Attempt Triggers Account Lockout
- **Description**: Third consecutive incorrect password submission triggers immediate lockout.
- **Inputs**:
  - POST `/login` with `usuario = 'user_clean'`, `contrasena = 'BadPass3'`.
- **Expected Assertions**:
  1. HTTP Status: `302 Found` to `/login`
  2. DB Assertion: `seeInDatabase('Usuario', ['Usuario' => 'user_clean', 'intentos_fallidos' => 3, 'bloqueado' => 1])`
  3. Response/Flash Message: Contains explicit lockout message: "Acceso bloqueado tras 3 intentos fallidos consecutivos".

#### `T1-LOCK-04`: Counter Reset on Successful Login after 1 Failure
- **Description**: User fails once (`intentos_fallidos = 1`), then immediately logs in with correct password.
- **Inputs**:
  - Attempt 1: POST `/login` with wrong password (`intentos_fallidos` becomes 1).
  - Attempt 2: POST `/login` with valid password `'CleanPass123!'`.
- **Expected Assertions**:
  1. HTTP Status: `302 Found` to `home.php`
  2. DB Assertion: `seeInDatabase('Usuario', ['Usuario' => 'user_clean', 'intentos_fallidos' => 0, 'bloqueado' => 0])`
  3. Session: `session('isLoggedIn') === true`.

#### `T1-LOCK-05`: Counter Reset on Successful Login after 2 Failures
- **Description**: User with 2 previous failed attempts (`user_warning`, seeded with `intentos_fallidos = 2`) enters valid password.
- **Inputs**:
  - POST `/login` with `usuario = 'user_warning'`, `contrasena = 'WarnPass123!'`.
- **Expected Assertions**:
  1. HTTP Status: `302 Found` to `home.php`
  2. DB Assertion: `seeInDatabase('Usuario', ['Usuario' => 'user_warning', 'intentos_fallidos' => 0, 'bloqueado' => 0])`
  3. Session: `session('isLoggedIn') === true`.

#### `T1-LOCK-06`: Locked Account Immediate Rejection on 4th Attempt with Valid Password
- **Description**: User whose account is locked (`locked_user`, seeded with `intentos_fallidos = 3, bloqueado = 1`) attempts login using the CORRECT password.
- **Inputs**:
  - POST `/login` with `usuario = 'locked_user'`, `contrasena = 'LockedPass123!'` (exact correct password).
- **Expected Assertions**:
  1. HTTP Status: `302 Found` to `/login`
  2. Session: `session('isLoggedIn')` is null/false (NO login allowed).
  3. DB Assertion: `seeInDatabase('Usuario', ['Usuario' => 'locked_user', 'bloqueado' => 1])`
  4. Response/Flash Message: Lockout notice: "Acceso bloqueado tras 3 intentos fallidos consecutivos. Comuníquese con el administrador."

---

### 5.4 Area 4: Redirection to home.php & Landing View Confirmation (R3)
**Test File**: `tests/Feature/ViewUITest.php`

#### `T1-REDIR-01`: HTTP 302 Redirection to `home.php` Post-Authentication
- **Description**: Successful authentication triggers an automatic HTTP 302 redirect directly to `home.php`.
- **Inputs**:
  - POST `/login` with valid credentials.
- **Expected Assertions**:
  1. HTTP Status: `302 Found`
  2. Header: `Location` contains `home.php`
  3. `$result->assertRedirectTo('home.php')` or `$result->assertRedirect()` matches `home.php`.

#### `T1-REDIR-02`: Successful Authentication Confirmation Banner on `home.php`
- **Description**: Authenticated user accessing `home.php` sees a prominent success confirmation banner.
- **Inputs**:
  - GET `/home.php` with active session.
- **Expected Assertions**:
  1. HTTP Status: `200 OK`
  2. Text Assertion: Contains "¡Inicio de sesión exitoso!" or "Bienvenido al sistema Creditrack".

#### `T1-REDIR-03`: Active Session Health Status Badge on `home.php`
- **Description**: `home.php` displays an explicit session status badge confirming an active session.
- **Inputs**:
  - GET `/home.php` with active session.
- **Expected Assertions**:
  1. HTTP Status: `200 OK`
  2. Text Assertion: Contains "Estado de la sesión: Activa" (or "Sesión Activa").

#### `T1-REDIR-04`: User Identity Rendering on `home.php`
- **Description**: `home.php` renders employee code, full user name, and username.
- **Inputs**:
  - GET `/home.php` with session for `Carlos Admin` (`EMP-ADM-01`, `admin_sv`).
- **Expected Assertions**:
  1. HTTP Status: `200 OK`
  2. View Text Assertions:
     - Contains `'EMP-ADM-01'`
     - Contains `'Carlos Admin'`
     - Contains `'admin_sv'`

#### `T1-REDIR-05`: Assigned Role and Country Confirmation Badges
- **Description**: `home.php` explicitly displays role and country badges.
- **Inputs**:
  - GET `/home.php` with session for `Beatriz Supervisor` (`Supervisor`, `Guatemala`).
- **Expected Assertions**:
  1. HTTP Status: `200 OK`
  2. View Text Assertions:
     - Contains "Rol Asignado: Supervisor" (or "Supervisor")
     - Contains "País Asignado: Guatemala" (or "Guatemala")

#### `T1-REDIR-06`: Access Boundary Declaration Statement on `home.php`
- **Description**: `home.php` renders the explicit geographical/operational access boundary statement.
- **Inputs**:
  - GET `/home.php` with session for `Alejandro Asesor` (`Asesor`, `Honduras`).
- **Expected Assertions**:
  1. HTTP Status: `200 OK`
  2. View Text Assertion: Contains boundary statement: "Acceso restringido exclusivamente a información y operaciones de Honduras con privilegios de Asesor" (or equivalent country/role boundary text).

---

### 5.5 Area 5: Visual Corporate Palette & Manual Author Attribution (R4)
**Test File**: `tests/Feature/ViewUITest.php`

#### `T1-UI-01`: Corporate Color #BF0426 (Crimson Red) Presence in Views
- **Description**: Verify exact hex color `#BF0426` is implemented in stylesheets or inline styles of `login.php` and `home.php`.
- **Inputs**:
  - GET `/login` and GET `/home.php`.
- **Expected Assertions**:
  - Rendered response body contains `#BF0426` (case-insensitive regex: `/#BF0426/i`).

#### `T1-UI-02`: Corporate Color #0511F2 (Electric Royal Blue) Presence in Views
- **Description**: Verify exact hex color `#0511F2` is implemented in stylesheets or inline styles of `login.php` and `home.php`.
- **Inputs**:
  - GET `/login` and GET `/home.php`.
- **Expected Assertions**:
  - Rendered response body contains `#0511F2` (case-insensitive regex: `/#0511F2/i`).

#### `T1-UI-03`: Corporate Color #023373 (Navy Dark Blue) Presence in Views
- **Description**: Verify exact hex color `#023373` is implemented in stylesheets or inline styles of `login.php` and `home.php`.
- **Inputs**:
  - GET `/login` and GET `/home.php`.
- **Expected Assertions**:
  - Rendered response body contains `#023373` (case-insensitive regex: `/#023373/i`).

#### `T1-UI-04`: Corporate Color #A61717 (Dark Brick Red) Presence in Views
- **Description**: Verify exact hex color `#A61717` is implemented in stylesheets or inline styles of `login.php` and `home.php`.
- **Inputs**:
  - GET `/login` and GET `/home.php`.
- **Expected Assertions**:
  - Rendered response body contains `#A61717` (case-insensitive regex: `/#A61717/i`).

#### `T1-UI-05`: Corporate Color #8D253C (Wine / Claret) Presence in Views
- **Description**: Verify exact hex color `#8D253C` is implemented in stylesheets or inline styles of `login.php` and `home.php`.
- **Inputs**:
  - GET `/login` and GET `/home.php`.
- **Expected Assertions**:
  - Rendered response body contains `#8D253C` (case-insensitive regex: `/#8D253C/i`).

#### `T1-UI-06`: Manual Author Attribution Markup on Login View (`login.php`)
- **Description**: Inspect HTML of `/login` for static manual attribution mentioning the system designers.
- **Inputs**:
  - GET `/login`.
- **Expected Assertions**:
  1. HTTP Status: `200 OK`
  2. View Text Assertion: Contains "Diseñado" or "Desarrollado por" and references the system designers/authors (e.g. "Equipo de Desarrollo Creditrack").

#### `T1-UI-07`: Manual Author Attribution Markup on Landing View (`home.php`)
- **Description**: Inspect HTML of `/home.php` for static manual author attribution.
- **Inputs**:
  - GET `/home.php` with active session.
- **Expected Assertions**:
  1. HTTP Status: `200 OK`
  2. View Text Assertion: Contains "Diseñado" or "Desarrollado por" in footer or dedicated attribution container.

---

## 6. Tier 2 Test Specification: Boundary & Corner Cases

Tier 2 probes boundary conditions, malformed inputs, multi-browser resilience, and access restriction guarantees (minimum 5 test cases per core area).

### 6.1 Area 1: Authentication & Login Boundaries (R1)
**Test File**: `tests/Feature/AuthTest.php`

#### `T2-AUTH-01`: Empty Form Submission (Both Fields Empty)
- **Description**: POST to `/login` with empty strings for both `usuario` and `contrasena`.
- **Inputs**:
  - POST `/login` with `['usuario' => '', 'contrasena' => '']`.
- **Expected Assertions**:
  1. HTTP Status: `302 Found` (redirect back to `/login`) or `200 OK` with validation errors.
  2. Error Message: Contains "El usuario es obligatorio" and "La contraseña es obligatoria" (or similar validation notice).
  3. DB Assertion: Counter `intentos_fallidos` is NOT incremented for any user in database.

#### `T2-AUTH-02`: Whitespace-Only Inputs
- **Description**: POST to `/login` with whitespace characters (`"   "`).
- **Inputs**:
  - POST `/login` with `['usuario' => '   ', 'contrasena' => '   ']`.
- **Expected Assertions**:
  1. Treated as empty inputs following trim.
  2. Rejection without database query or counter increment.
  3. No session created.

#### `T2-AUTH-03`: SQL Injection in Username Field
- **Description**: Submit classic SQL injection payloads in `usuario` field (e.g., `' OR '1'='1' --`, `' UNION SELECT 1, 'admin' --`).
- **Inputs**:
  - POST `/login` with `usuario = "' OR '1'='1' --"`, `contrasena = 'any'`.
- **Expected Assertions**:
  1. HTTP Status: `302 Found` to `/login`
  2. Prepared statements ensure input is treated strictly as a literal string.
  3. Authentication fails cleanly with generic error. No database syntax exception or bypass.

#### `T2-AUTH-04`: Extremely Long Input Strings (Buffer / Length Boundary)
- **Description**: Submit input string exceeding field boundaries (e.g. 500 characters for username, 10,000 characters for password).
- **Inputs**:
  - POST `/login` with `usuario = str_repeat('A', 500)`, `contrasena = str_repeat('P', 10000)`.
- **Expected Assertions**:
  1. Validated and rejected gracefully without unhandled database truncation exception, PHP fatal error, or memory exhaustion.
  2. Generic auth error or validation error returned.

#### `T2-AUTH-05`: Case Sensitivity Verification for Username and Password
- **Description**: Passwords must be strictly case-sensitive. Verify password with incorrect case fails.
- **Inputs**:
  - POST `/login` with `usuario = 'admin_sv'`, `contrasena = 'adminpass123!'` (all lowercase, true password has uppercase `A` and `P`).
- **Expected Assertions**:
  1. Authentication rejected.
  2. Counter `intentos_fallidos` incremented to 1 for `admin_sv`.
  3. Session is NOT created.

#### `T2-AUTH-06`: Special & Multibyte Characters in Password
- **Description**: Authenticate user with complex password containing accents, symbols, and multibyte characters (`P@$$w0rd!#%^&*()_+ñáéíóú`).
- **Inputs**:
  - POST `/login` with `usuario = 'special_pwd'`, `contrasena = 'P@$$w0rd!#%^&*()_+ñáéíóú'`.
- **Expected Assertions**:
  1. HTTP Status: `302 Found` to `home.php`.
  2. Successfully verified by `password_verify` without encoding corruption.
  3. Session established.

---

### 6.2 Area 2: Session Role & Country Boundaries (R1)
**Test File**: `tests/Feature/AccessControlTest.php`

#### `T2-ACCESS-01`: Unauthenticated Access to `home.php` Intercepted
- **Description**: Direct GET request to `/home.php` without an active session.
- **Inputs**:
  - GET `/home.php` with no session cookies or empty session.
- **Expected Assertions**:
  1. Intercepted by `AuthFilter`.
  2. HTTP Status: `302 Found`
  3. Redirect URL: matches `.*login$`
  4. Flash message: "Debe iniciar sesión para acceder" or equivalent alert.

#### `T2-ACCESS-02`: Unauthenticated Access to Route Alias `/home` Intercepted
- **Description**: Direct GET request to route alias `/home` without active session.
- **Inputs**:
  - GET `/home` with empty session.
- **Expected Assertions**:
  1. Intercepted by `AuthFilter`.
  2. HTTP Status: `302 Found` to `/login`.

#### `T2-ACCESS-03`: Incomplete Session Payload Handling
- **Description**: Request protected page with partially forged session (`isLoggedIn = true`, but missing `rol` or `id_pais`).
- **Inputs**:
  - GET `/home.php` with session `['isLoggedIn' => true, 'usuario' => 'admin_sv']` (missing `id_pais` and `rol`).
- **Expected Assertions**:
  1. `AuthFilter` or `Home::index` validates session completeness.
  2. Request is denied and redirected to `/login`, or gracefully re-queries profile. No unhandled `Undefined array key` exception.

#### `T2-ACCESS-04`: Cross-Country Boundary Isolation Enforcement
- **Description**: Verify user from Country 1 cannot access data or views parameterized for Country 2.
- **Inputs**:
  - Session for user from El Salvador (`id_pais = 1`).
  - Request resource explicitly targeting Country 2 (e.g. GET `/home.php?pais=2` or operational subroute `/agencias?pais=2`).
- **Expected Assertions**:
  1. HTTP Status: `403 Forbidden` or view renders strictly data for `id_pais = 1`.
  2. Foreign country information is strictly withheld.

#### `T2-ACCESS-05`: Post-Logout Session Invalidation Guard
- **Description**: User authenticates, receives valid session, visits `/logout`, then replays the previous session cookie.
- **Inputs**:
  1. Login `admin_sv` -> capture session ID.
  2. Invoke `/logout`.
  3. GET `/home.php` sending the logged-out session ID.
- **Expected Assertions**:
  1. HTTP Status: `302 Found` to `/login`.
  2. Access denied; replayed session cookie is dead.

#### `T2-ACCESS-06`: Corrupted / Unknown Role Defense
- **Description**: User session contains unrecognized role (e.g. `rol = 'SuperHacker'`).
- **Inputs**:
  - GET `/home.php` with session `['isLoggedIn' => true, 'rol' => 'SuperHacker', 'id_pais' => 1]`.
- **Expected Assertions**:
  1. Controller handles unrecognized role safely (falls back to minimal privilege or redirects to error/login).
  2. No administrative controls or unauthorized actions rendered.

---

### 6.3 Area 3: 3-Failed Attempt Lockout & Consecutive Counter Boundaries (R2)
**Test File**: `tests/Feature/LockoutTest.php`

#### `T2-LOCK-01`: Multi-Session / Cookie Clearing Resilience (Database Persistence)
- **Description**: Attacker fails 2 attempts in Browser A, clears cookies / opens incognito window, and fails attempt 3.
- **Inputs**:
  1. Session 1: POST `/login` with `usuario = 'user_clean'`, bad password (Attempt 1).
  2. Session 1: POST `/login` with `usuario = 'user_clean'`, bad password (Attempt 2).
  3. Session 2 (fresh session / no cookies): POST `/login` with `usuario = 'user_clean'`, bad password (Attempt 3).
- **Expected Assertions**:
  1. The 3rd attempt in Session 2 locks the account (`bloqueado = 1`, `intentos_fallidos = 3`).
  2. Account is locked because state is persisted in SQLite `Usuario` table, not client cookies.

#### `T2-LOCK-02`: Lockout Isolation Between Different User Accounts
- **Description**: Locking `user_a` after 3 failed attempts does not impact legitimate user `user_b`.
- **Inputs**:
  1. Submit 3 failed attempts on `user_clean` -> `user_clean` locked (`bloqueado = 1`).
  2. Submit valid login for `admin_sv` (`AdminPass123!`).
- **Expected Assertions**:
  1. `admin_sv` logs in successfully (HTTP 302 to `home.php`).
  2. `admin_sv` remains active and unlocked (`bloqueado = 0`).
  3. Only `user_clean` is locked.

#### `T2-LOCK-03`: Failed Attempts on Non-Existent Users Do Not Leak State or Crash
- **Description**: Repeated failed login attempts with non-existent usernames.
- **Inputs**:
  - Submit 5 failed attempts with `usuario = 'phantom_target'`, `contrasena = 'any'`.
- **Expected Assertions**:
  1. System returns generic error consistently.
  2. No database corruption or SQL exceptions.
  3. Existing database users remain unaltered.

#### `T2-LOCK-04`: Pre-Locked Account Bypass of `password_verify`
- **Description**: When an account is locked (`bloqueado = 1`), authentication must be aborted before evaluating password hash.
- **Inputs**:
  - POST `/login` for `locked_user` with valid and invalid passwords.
- **Expected Assertions**:
  1. Both valid and invalid passwords yield identical immediate rejection with lockout message.
  2. Database `intentos_fallidos` remains 3, `bloqueado` remains 1.

#### `T2-LOCK-05`: Lockout Stability Under Continued Failed Attempts
- **Description**: Attacker continues submitting wrong passwords against an already locked account.
- **Inputs**:
  - Submit 5 additional failed attempts against `locked_user`.
- **Expected Assertions**:
  1. Account remains locked (`bloqueado = 1`).
  2. System continuously returns lockout notice.
  3. No counter overflow or database errors.

#### `T2-LOCK-06`: Administrative Unlock Re-Enables Authentication
- **Description**: Administrative action resets `bloqueado = 0` and `intentos_fallidos = 0` for a previously locked account.
- **Inputs**:
  1. Programmatically or via model call: update `locked_user` to `bloqueado = 0, intentos_fallidos = 0`.
  2. Submit POST `/login` with `usuario = 'locked_user'`, `contrasena = 'LockedPass123!'`.
- **Expected Assertions**:
  1. HTTP Status: `302 Found` to `home.php`.
  2. User successfully authenticates.

---

### 6.4 Area 4: Redirection & Landing View Boundaries (R3)
**Test File**: `tests/Feature/ViewUITest.php`

#### `T2-REDIR-01`: Direct Route Parity (`/home.php` vs `/home`)
- **Description**: Both `/home.php` and `/home` routes must render identically with active session.
- **Inputs**:
  - Active session, request GET `/home.php` then GET `/home`.
- **Expected Assertions**:
  1. Both return HTTP 200 OK.
  2. Both contain identical session confirmation and user profile badges.

#### `T2-REDIR-02`: HTML Escaping / XSS Prevention in User Profile Data
- **Description**: Seeded user with XSS vector in `Nombre` (`Juan <script>alert(1)</script>`).
- **Inputs**:
  - Authenticate as `xss_user`, GET `/home.php`.
- **Expected Assertions**:
  1. HTTP Status: `200 OK`
  2. Output markup renders escaped `&lt;script&gt;alert(1)&lt;/script&gt;` or stripped.
  3. Raw `<script>alert(1)</script>` tag is NOT rendered in DOM.

#### `T2-REDIR-03`: Direct Access to `home.php` with Expired Session
- **Description**: Request `home.php` with a session timestamp older than `expiration` (7200 seconds).
- **Inputs**:
  - GET `/home.php` with session `['isLoggedIn' => true, 'login_time' => time() - 8000]`.
- **Expected Assertions**:
  1. Intercepted by filter or session expiration logic.
  2. HTTP Status: `302 Found` to `/login`.

#### `T2-REDIR-04`: Logout Action Functional Link Presence
- **Description**: Ensure `home.php` provides a visible, actionable link or form to log out.
- **Inputs**:
  - GET `/home.php` with active session.
- **Expected Assertions**:
  1. View contains `<a href="...logout..."` or `<form action="...logout..."`.
  2. Label text contains "Cerrar Sesión" or "Salir".

#### `T2-REDIR-05`: Circular Redirection Loop Prevention
- **Description**: Ensure an unauthenticated request to `home.php` does not trigger a redirect loop.
- **Inputs**:
  - Follow redirect from GET `/home.php` -> 302 to `/login` -> GET `/login`.
- **Expected Assertions**:
  1. Final HTTP Status is `200 OK`.
  2. Exactly one redirect occurs; no infinite redirect chain.

#### `T2-REDIR-06`: Missing Session Metadata Fallback Gracefulness
- **Description**: Session with `isLoggedIn = true` but missing non-critical display fields (`pais_nombre` or `telefono`).
- **Inputs**:
  - GET `/home.php` with `['isLoggedIn' => true, 'usuario' => 'admin_sv', 'rol' => 'Administrador', 'id_pais' => 1, 'pais_nombre' => null]`.
- **Expected Assertions**:
  1. View does not throw fatal PHP Notice/Error.
  2. Displays sensible fallback or resolves country name from database model.

---

### 6.5 Area 5: Visual Corporate Palette & Manual Attribution Boundaries (R4)
**Test File**: `tests/Feature/ViewUITest.php`

#### `T2-UI-01`: Complete 5-Color Corporate Palette Assertion
- **Description**: Verify that ALL five corporate colors are declared in the view stylesheet / style blocks across `login.php` and `home.php`.
- **Inputs**:
  - Render `login.php` and `home.php`.
- **Expected Assertions**:
  - Regex checks for all 5 colors in markup / embedded CSS:
    1. `/#BF0426/i` (Crimson Primary)
    2. `/#0511F2/i` (Electric Blue)
    3. `/#023373/i` (Navy Blue)
    4. `/#A61717/i` (Dark Brick Red)
    5. `/#8D253C/i` (Wine / Claret)
  - Assert that count of matched corporate palette colors == 5.

#### `T2-UI-02`: Author Attribution Semantic Visibility
- **Description**: Verify author attribution container is semantically visible and not hidden by CSS.
- **Inputs**:
  - Inspect rendered HTML of `login.php` and `home.php`.
- **Expected Assertions**:
  1. Attribution element does not have `display: none` or `visibility: hidden`.
  2. Attribution resides in a dedicated container (`footer` or class containing `attribution` / `autor` / `creditos`).

#### `T2-UI-03`: Static Manual Attribution Verification (No Dynamic Injection)
- **Description**: Verify attribution is statically embedded in the HTML markup rather than loaded via untrusted external JavaScript or async fetch.
- **Inputs**:
  - Inspect raw HTML source.
- **Expected Assertions**:
  1. Attribution text is present in the initial server response body.
  2. Does not rely on client-side JS DOM manipulation.

#### `T2-UI-04`: Flash Error Message Visual Styling Compliance
- **Description**: When flash error message is rendered on `/login`, verify it uses the corporate red/brick palette colors (`#BF0426` or `#A61717`).
- **Inputs**:
  - GET `/login` with flash error set (`'error' => 'Credenciales incorrectas'`).
- **Expected Assertions**:
  1. Error banner is visible in HTML.
  2. Error banner styling incorporates `#BF0426` or `#A61717`.

#### `T2-UI-05`: Viewport Meta & Mobile Responsiveness Markup
- **Description**: Verify that the login and home views include responsive viewport metadata.
- **Inputs**:
  - GET `/login` and GET `/home.php`.
- **Expected Assertions**:
  - Contains `<meta name="viewport" content="width=device-width, initial-scale=1">` (or equivalent responsive meta tag).

#### `T2-UI-06`: Visual Color Contrast and Accessible Hierarchy
- **Description**: Check that text styling applied over dark corporate backgrounds (`#023373`, `#8D253C`, `#BF0426`) uses high-contrast light text (`#ffffff` or `#f8f9fa`).
- **Inputs**:
  - Inspect CSS styles of headers, buttons, and alert elements.
- **Expected Assertions**:
  - Dark corporate backgrounds are paired with light/white text colors for visual readability.

---

## 7. Specification Miner Tables

### 7.1 Features Discovered
| # | Category | Feature | Description | Inputs | Outputs | Error Behavior | Discovered Via |
|---|----------|---------|-------------|--------|---------|----------------|----------------|
| 1 | Auth (R1) | User Credential Validation | Authenticates user against `Usuario` table using `password_verify` | `usuario`, `contrasena` | Session creation, HTTP 302 to `home.php` | 302 to `/login`, flash error "Credenciales incorrectas" | `ORIGINAL_REQUEST.md` § R1, `PROJECT.md` |
| 2 | Auth (R1) | Role Identification & Scoping | Populates `session('rol')` from database user record | Authenticated DB user record | `session('rol')` set | Blocks session initialization if role missing | `ORIGINAL_REQUEST.md` § R1 |
| 3 | Auth (R1) | Country Association & Scoping | Populates `session('id_pais')` and `session('pais_nombre')` | `Id_Pais` from `Usuario` joined with `Pais` | `session('id_pais')`, `session('pais_nombre')` set | Fails auth if country relation invalid | `ORIGINAL_REQUEST.md` § R1, `Base_Datos.sql` |
| 4 | Security (R1) | Session Fixation Defense | Invokes `session()->regenerate(true)` upon successful login | Existing pre-auth session | New cryptographic session ID | Invalidates hijacked tokens | CI4 Framework Architecture (`Session.php`) |
| 5 | Security (R1) | Route Guard (`AuthFilter`) | Blocks unauthenticated requests from accessing protected routes | HTTP request without active session | 302 redirect to `/login` | Denies access with unauthorized flash notice | `PROJECT.md` § Interface Contracts |
| 6 | Security (R2) | Consecutive Failure Counter | Increments `Usuario.intentos_fallidos` for each bad password | Username and wrong password | Updated `intentos_fallidos` in DB | Displays remaining attempts warning | `ORIGINAL_REQUEST.md` § R2 |
| 7 | Security (R2) | Counter Reset on Success | Resets `intentos_fallidos = 0` on successful authentication before 3rd attempt | Valid credentials following 1-2 failures | `intentos_fallidos = 0`, `bloqueado = 0` | None | `ORIGINAL_REQUEST.md` § R2 |
| 8 | Security (R2) | 3-Failed Attempt Lockout | Sets `bloqueado = 1` on 3rd consecutive bad attempt | 3rd consecutive failed login | Account marked locked in DB | Displays lockout alert message | `ORIGINAL_REQUEST.md` § R2 |
| 9 | Security (R2) | Locked Account Rejection | Rejects all attempts on locked account without verifying password | Any login attempt on `bloqueado = 1` | Immediate rejection, 302 to `/login` | Access blocked alert regardless of password | `ORIGINAL_REQUEST.md` § R2, `PROJECT.md` |
| 10 | Routing (R3) | Post-Auth Redirection | Redirects authenticated user via HTTP 302 to `home.php` | Valid login POST | HTTP 302 `Location: ...home.php` | Fallback to default route if missing | `ORIGINAL_REQUEST.md` § R3 |
| 11 | View (R3) | Session Health Status Display | Displays active session status confirmation on `home.php` | Active session | Visual badge "Estado de la sesión: Activa" | Redirected to login if session missing | `ORIGINAL_REQUEST.md` § R3 |
| 12 | View (R3) | Role & Country Confirmation | Confirms assigned role and country on `home.php` | `session('rol')`, `session('pais_nombre')` | Prominent confirmation badges | Fallback display if missing | `ORIGINAL_REQUEST.md` § R3 |
| 13 | View (R3) | Access Boundary Statement | Confirms restricted data boundary on `home.php` | `session('rol')`, `session('pais_nombre')` | Rendered boundary declaration statement | None | `ORIGINAL_REQUEST.md` § R1, R3 |
| 14 | View (R3) | Secure Logout | Terminates session and redirects to `/login` | Request to `/logout` | Session destroyed, HTTP 302 to `/login` | None | `PROJECT.md` |
| 15 | UI (R4) | Corporate 5-Color Palette | Styles UI elements using exact 5-color corporate hex codes | CSS / HTML view markup | Rendered views with #BF0426, #0511F2, #023373, #A61717, #8D253C | View validation fails if colors missing | `ORIGINAL_REQUEST.md` § R4 |
| 16 | UI (R4) | Manual Author Attribution | Displays visible static credit mentioning system designers | Static HTML in `login.php` & `home.php` | Rendered footer/banner with designer credit | None | `ORIGINAL_REQUEST.md` § R4 |
| 17 | Config (R5) | Zero `.env` Access | Application & test suite run with native config without touching `.env` | CI4 Config / SQLite `:memory:` | Fully functioning app and tests | Integrity failure if `.env` accessed | `ORIGINAL_REQUEST.md` § R5 |

### 7.2 Edge Cases
| # | Feature | Input / Scenario | Observed / Expected Behavior |
|---|---------|------------------|------------------------------|
| 1 | Lockout (R2) | 2 failures then 1 valid login | Attempt 1 fails (`count=1`); attempt 2 fails (`count=2`); attempt 3 succeeds -> counter reset to 0, session established, redirect to `home.php`. No lockout. |
| 2 | Lockout (R2) | 3 consecutive failures on same user | Attempt 1 fails (`count=1`); attempt 2 fails (`count=2`); attempt 3 fails (`count=3`) -> `bloqueado` set to 1. User receives "Acceso bloqueado tras 3 intentos fallidos consecutivos". |
| 3 | Lockout (R2) | 4th attempt on locked account with correct password | Account already locked (`bloqueado = 1`). User enters correct password -> Immediately denied without checking password. Account remains locked. |
| 4 | Lockout (R2) | Attacker clears cookies / switches browsers between attempts | Attacker fails 2 attempts in Browser A, clears cookies/opens Browser B, fails attempt 3 -> Counter stored in SQLite DB reaches 3. Account locked. Cookie clearing cannot bypass DB lockout. |
| 5 | Lockout (R2) | Brute-force flood on locked account (5+ extra attempts) | Continued incorrect attempts on locked account continue to return lockout notice; DB maintains `bloqueado = 1`, no crash or counter overflow. |
| 6 | Lockout (R2) | Lockout isolation across different users | Attacker locks User A (3 bad attempts). User B enters correct password -> User B logs in successfully. Lockout is strictly user-isolated. |
| 7 | Auth (R1) | Non-existent username submitted | Submitting unknown user returns generic "Credenciales incorrectas" error (preventing user enumeration) without throwing DB exceptions. |
| 8 | Auth (R1) | Blank and whitespace-only credentials | Submitting empty or spaces-only inputs is rejected by validation before DB query; does not increment failed attempt counter. |
| 9 | Auth (R1) | SQL Injection payload in username (`' OR '1'='1' --`) | CI4 Query Builder / prepared statements escape input; treated as literal string; login fails cleanly without SQL error. |
| 10 | Auth (R1) | Extremely long input strings (500-10,000 chars) | Input validation catches excessive lengths gracefully; no memory exhaustion or DB truncation fatal errors. |
| 11 | Auth (R1) | Multibyte & special characters in password | Multibyte password (`P@$$w0rd!#%^&*()_+ñáéíóú`) verified correctly by bcrypt without charset distortion. |
| 12 | Access (R1) | Cross-country data access attempt | User assigned to Country 1 attempts to query or access Country 2 resources -> Blocked by access filter with 403 Forbidden or data filtered. |
| 13 | Access (R3) | Unauthenticated user requests `home.php` | Filter intercepts request, issues HTTP 302 redirecting to `/login` with unauthorized flash alert. |
| 14 | Access (R1) | Authenticated user requests `/login` | User with active session visits login form -> Redirected to `home.php` to avoid redundant login. |
| 15 | View (R3) | Route parity between `/home.php` and `/home` | Both endpoints resolve to same landing view with HTTP 200 and identical confirmation content. |
| 16 | View (R3) | XSS payload in user's full name | User name containing `<script>alert(1)</script>` is safely escaped (`&lt;script&gt;`) in view; no raw execution. |
| 17 | UI (R4) | Palette verification across both views | Both `login.php` and `home.php` contain all 5 required hex codes (`#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C`). |
| 18 | UI (R4) | Author attribution visibility | Attribution footer is statically present in markup and not hidden via CSS (`display: none`). |

---

## 8. Cross-Reference: Acceptance Criteria to Test Cases

| Acceptance Criteria (from `ORIGINAL_REQUEST.md`) | Covered By Tier 1 Tests | Covered By Tier 2 Tests |
|---|---|---|
| **AC-1**: Credentials validated against database | `T1-AUTH-01`, `T1-AUTH-02`, `T1-AUTH-03` | `T2-AUTH-05`, `T2-AUTH-06` |
| **AC-2**: Rejection of incorrect credentials with generic error | `T1-AUTH-04`, `T1-AUTH-05` | `T2-AUTH-01`, `T2-AUTH-02`, `T2-AUTH-03` |
| **AC-3**: Session stores & validates role and country | `T1-ACCESS-01`, `T1-ACCESS-02`, `T1-ACCESS-03` | `T2-ACCESS-03`, `T2-ACCESS-04`, `T2-ACCESS-06` |
| **AC-4**: Lockout after 3 consecutive failed attempts | `T1-LOCK-01`, `T1-LOCK-02`, `T1-LOCK-03` | `T2-LOCK-01`, `T2-LOCK-02`, `T2-LOCK-05` |
| **AC-5**: Reset of counter upon valid login before 3rd attempt | `T1-LOCK-04`, `T1-LOCK-05` | `T2-LOCK-06` |
| **AC-6**: 4th attempt on locked account rejected even with valid password | `T1-LOCK-06` | `T2-LOCK-04` |
| **AC-7**: Successful login automatically redirects to `home.php` | `T1-REDIR-01` | `T2-REDIR-01`, `T2-REDIR-05` |
| **AC-8**: `home.php` displays session status, role, country, and boundaries | `T1-REDIR-02`, `T1-REDIR-03`, `T1-REDIR-04`, `T1-REDIR-05`, `T1-REDIR-06` | `T2-REDIR-02`, `T2-REDIR-04`, `T2-REDIR-06` |
| **AC-9**: Unauthenticated access to `home.php` is blocked | `T1-ACCESS-05` | `T2-ACCESS-01`, `T2-ACCESS-02`, `T2-ACCESS-05` |
| **AC-10**: UI implements corporate palette (#BF0426, #0511F2, #023373, #A61717, #8D253C) | `T1-UI-01`, `T1-UI-02`, `T1-UI-03`, `T1-UI-04`, `T1-UI-05` | `T2-UI-01`, `T2-UI-04`, `T2-UI-06` |
| **AC-11**: UI includes manual author attribution visibly in HTML | `T1-UI-06`, `T1-UI-07` | `T2-UI-02`, `T2-UI-03` |
| **AC-12**: Strict `.env` isolation (R5) | Architectural Isolation (All Tests) | Architectural Isolation (All Tests) |

---

## 9. Test Implementation Code Blueprint for Test Writers

Below are concrete implementation patterns for the test writer agents.

### 9.1 Database Test Trait & Seeder Setup Example
```php
<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

abstract class BaseFeatureTestCase extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = true;
    protected $seed    = 'Tests\Support\Database\Seeds\TestAuthSeeder';

    protected function setUp(): void
    {
        parent::setUp();
        // Setup SQLite in-memory DDL and seed records
    }
}
```

### 9.2 Feature Test Pattern: 3-Attempt Lockout
```php
public function testThreeConsecutiveFailedAttemptsLocksAccount(): void
{
    // Attempt 1
    $res1 = $this->post('/login', ['usuario' => 'user_clean', 'contrasena' => 'wrong1']);
    $res1->assertRedirect();
    $this->seeInDatabase('Usuario', ['Usuario' => 'user_clean', 'intentos_fallidos' => 1, 'bloqueado' => 0]);

    // Attempt 2
    $res2 = $this->post('/login', ['usuario' => 'user_clean', 'contrasena' => 'wrong2']);
    $res2->assertRedirect();
    $this->seeInDatabase('Usuario', ['Usuario' => 'user_clean', 'intentos_fallidos' => 2, 'bloqueado' => 0]);

    // Attempt 3
    $res3 = $this->post('/login', ['usuario' => 'user_clean', 'contrasena' => 'wrong3']);
    $res3->assertRedirect();
    $this->seeInDatabase('Usuario', ['Usuario' => 'user_clean', 'intentos_fallidos' => 3, 'bloqueado' => 1]);

    // Attempt 4 with VALID password
    $res4 = $this->post('/login', ['usuario' => 'user_clean', 'contrasena' => 'CleanPass123!']);
    $res4->assertRedirect();
    $res4->assertSessionMissing('isLoggedIn');
    $this->seeInDatabase('Usuario', ['Usuario' => 'user_clean', 'bloqueado' => 1]);
}
```

### 9.3 View & Palette Verification Pattern
```php
public function testCorporatePaletteColorsPresentInViews(): void
{
    $response = $this->get('/login');
    $response->assertStatus(200);

    $body = $response->getBody();
    $palette = ['#BF0426', '#0511F2', '#023373', '#A61717', '#8D253C'];

    foreach ($palette as $color) {
        $this->assertMatchesRegularExpression('/' . preg_quote($color, '/') . '/i', $body, "Color {$color} must be present in login view.");
    }
}
```

---
*Specification completed by Test Requirements Spec Miner (`sub_orch_e2e_spec_miner_2`).*
