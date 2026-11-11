# Specification Mining & Contract Analysis Report (Feature Test Suite)

**Author**: `spec_miner_rev3`  
**State Folder**: `c:\xampp\htdocs\creditrack\.agents\teamwork\spec_miner_rev3`  
**Target Date**: 2026-11-11  
**Scope**: 78 feature tests across `tests/Feature/` (5 classes) and `tests/_support/AuthTestCase.php`  

---

## 1. Observation

Direct examination of the authoritative test suite and project specification revealed the following verbatim facts:

1. **Test Suite Inventory (78 Tests Across 5 Classes)**:
   - `tests/Feature/AuthTest.php` (lines 1–309): 12 tests (6 Tier 1, 6 Tier 2).
   - `tests/Feature/AccessControlTest.php` (lines 1–277): 12 tests (6 Tier 1, 6 Tier 2).
   - `tests/Feature/LockoutTest.php` (lines 1–421): 12 tests (6 Tier 1, 6 Tier 2).
   - `tests/Feature/ViewUITest.php` (lines 1–649): 25 tests (13 Tier 1, 12 Tier 2).
   - `tests/Feature/WorkflowIntegrationTest.php` (lines 1–585): 17 tests (12 Tier 3, 5 Tier 4).
   - Base test case: `tests/_support/AuthTestCase.php` (lines 1–47) configures `$DBGroup = 'tests'`, `$migrate = false`, `$seedOnce = false`, `$seed = TestAuthSeeder::class`, and defines corporate `const PALETTE = ['#BF0426', '#0511F2', '#023373', '#A61717', '#8D253C']` with `assertCorporatePalettePresent(string $html)`.

2. **Database & Environment Architecture**:
   - `app/Config/Database.php` (lines 165–203): Defines the `$tests` connection group as transient in-memory SQLite3 (`'database' => ':memory:'`, `'DBDriver' => 'SQLite3'`). In constructor line 200–202: `if (ENVIRONMENT === 'testing') { $this->defaultGroup = 'tests'; }`.
   - `phpunit.dist.xml` (lines 13, 27–28): Sets `defaultTestSuite="Feature"` and excludes `./tests/unit/HealthTest.php` and `./tests/Feature` from the `App` suite. No reference or parsing of `.env` exists in `phpunit.dist.xml` or any test file.
   - `tests/_support/Database/Seeds/TestAuthSeeder.php` (lines 259–335): Creates `Pais` and `Usuario` tables in-memory via `$this->forge->createTable(..., true)` without running migrations or reading `.env`.
   - Requirement R5 (`ORIGINAL_REQUEST.md` lines 24–25, 41): Zero access, reading, or modification of `.env`.

3. **Key Fixture Dataset**:
   - `Pais`: `1 => 'El Salvador'`, `2 => 'Guatemala'`, `3 => 'Honduras'`.
   - `Usuario`: 12 seeded users: `admin_sv` (El Salvador Admin), `admin_user` (El Salvador Admin alias), `supervisor_sv` (El Salvador Supervisor), `supervisor_gt` (Guatemala Supervisor), `asesor_sv` (El Salvador Asesor), `asesor_gt` (Guatemala Asesor), `asesor_hn` (Honduras Asesor), `user_warning` (El Salvador Asesor, pre-set 2 failed attempts), `locked_user` (El Salvador Asesor, pre-set 3 attempts, `bloqueado = 1`), `special_pwd` (special and accented multibyte UTF-8 password), `user_clean` (clean account for sequential lockout), `xss_user` (XSS vector in `Nombre`).

---

## 2. Logic Chain & Analysis of Assigned Questions

### Question 1: Contracts and Assertions on Target Components

#### 1. Contracts Placed on `login.php` (View)
- **HTTP 200 & DOM Structure**:
  - `AuthTest::testT1Auth06_LoginFormViewRendering`: Renders `<form>`, `<input name="usuario">` (`/name=[\'"]usuario[\'"]/i`), `<input name="contrasena">` (`/name=[\'"]contrasena[\'"]/i`), and submit button (`/type=[\'"]submit[\'"]|<button/i`).
- **Corporate 5-Color Palette (Requirement R4)**:
  - `ViewUITest::testT1Ui01` through `testT1Ui05` and `ViewUITest::testT2Ui01`: Case-insensitive presence of ALL 5 colors simultaneously: `#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C`.
- **Flash Error & Lockout Styling**:
  - `ViewUITest::testT2Ui04`: Flash error alert container on login must incorporate corporate red/brick colors (`#BF0426` or `#A61717`).
  - `ViewUITest::testT2Ui06`: Visual contrast hierarchy pairing dark backgrounds (`#023373`, `#8D253C`, `#BF0426`, `#A61717`) with light text (`#fff`, `white`).
- **Mobile Responsiveness**:
  - `ViewUITest::testT2Ui05`: Viewport meta tag matching `/<meta[^>]+name=["\']viewport["\'][^>]+content=["\'][^"\']*width=device-width/i` must be present.
- **Manual Author Attribution**:
  - `ViewUITest::testT1Ui06`, `testT2Ui02`, `testT2Ui03`, `WorkflowIntegrationTest::testT3Pair05`: Raw HTML must match `/(diseñad[oa]|desarrollad[oa]|diseñadores|equipo de desarrollo|autores)/i`.
  - Must NOT be hidden with inline CSS `display:none` or `visibility:hidden`.
  - Must reside in a semantic container (`/(footer|<div[^>]*(attribution|autor|creditos|designer)[^>]*>|diseñad|desarrollad)/i`).

#### 2. Contracts Placed on `home.php` (View)
- **Session Confirmation Elements (Requirement R3)**:
  - `ViewUITest::testT1Redir02`: Prominent confirmation banner matching `/(¡?inicio de sesi[oó]n exitoso!?|bienvenido|sesi[oó]n iniciada)/i`.
  - `ViewUITest::testT1Redir03`: Active session health status badge matching `/(estado de la sesi[oó]n:\s*activa|sesi[oó]n\s*activa)/i`.
- **Identity & Role/Country Display**:
  - `ViewUITest::testT1Redir04`: Explicitly renders `codigo_empleado` (e.g. `EMP-ADM-01`), full `nombre`, and `usuario`.
  - `ViewUITest::testT1Redir05`: Displays role badge matching `/(rol|rol asignado):?\s*supervisor/i` and country badge matching `/(pa[ií]s|pa[ií]s asignado):?\s*guatemala/i`.
  - `ViewUITest::testT1Redir06`: Displays access boundary declaration statement matching `/(acceso restringido.*honduras|l[ií]mite territorial.*honduras|operaciones de honduras)/i`.
- **Actionable Logout Element**:
  - `ViewUITest::testT2Redir04`: Actionable link or form `/(href="[^"]*logout[^"]*"|action="[^"]*logout[^"]*")/i` with label `/(cerrar sesi[oó]n|salir|logout)/i`.
- **Corporate Branding & Attribution**:
  - `ViewUITest::testT1Ui01-05`, `testT2Ui01`: All 5 corporate hex colors simultaneously present.
  - `ViewUITest::testT1Ui07`, `testT2Ui02`: Visible manual author attribution markup present.
- **Security & Escaping**:
  - `ViewUITest::testT2Redir02`: Profile data containing script tags (e.g. `Juan <script>alert(1)</script>`) must be HTML-escaped (`&lt;script&gt;alert(1)&lt;/script&gt;`) and raw `<script>` prohibited.
  - `ViewUITest::testT2Redir06`: Graceful fallback if `pais_nombre` is null (no fatal errors).
- **Strict Cross-Country Segregation**:
  - `AccessControlTest::testT2Access04`, `WorkflowIntegrationTest::testT3Pair07`, `testT4Scen02`: Foreign country names (e.g. `El Salvador` for Guatemala user, or `Guatemala`/`Honduras` for El Salvador user) must NOT appear anywhere in the rendered HTML.

#### 3. Contracts Placed on `AuthController.php`
- **`login()` (GET `/login`, GET `/`)**:
  - If `session('isLoggedIn') === true`, redirects HTTP 302 to `home.php` (`AccessControlTest::testT1Access06`).
  - Otherwise renders `login.php` with HTTP 200.
- **`loginSubmit()` (POST `/login`)**:
  - Input Validation: Empty or whitespace-only inputs return 200 or 302 without touching DB failure counters (`AuthTest::testT2Auth01`, `testT2Auth02`, `WorkflowIntegrationTest::testT3Pair12`).
  - Buffer & SQLi Resilience: Extreme string length (10,000 chars) and SQL injection strings (`' OR '1'='1' --`) must be handled safely via parameterized queries without exceptions (`AuthTest::testT2Auth03`, `testT2Auth04`, `WorkflowIntegrationTest::testT3Pair10`).
  - Non-Existent Users: Rejects with HTTP 302 back to login, sets no session, touches no counters (`AuthTest::testT1Auth05`, `LockoutTest::testT2Lock03`, `WorkflowIntegrationTest::testT3Pair08`).
  - **Atomic Pre-Lock Check**: If `bloqueado == 1` or `intentos_fallidos >= 3`, rejects immediately BEFORE password verification. Submitting valid password to pre-locked account must NOT authenticate and must NOT clear lock (`LockoutTest::testT1Lock06`, `testT2Lock04`, `testT2Lock05`, `WorkflowIntegrationTest::testT3Pair03`).
  - Password Verification: Strict case-sensitivity (`testT2Auth05`), supports UTF-8 multibyte and symbols (`testT2Auth06`).
  - Failed Attempt Handling:
    - Increments `intentos_fallidos` (1, 2, 3) in DB (`LockoutTest::testT1Lock01-03`).
    - Stamped `ultimo_fallo` timestamp on failure (`LockoutTest::testT1Lock01-03`, `testT2Lock01`).
    - On 3rd failure: sets `bloqueado = 1` in DB (`LockoutTest::testT1Lock03`, `WorkflowIntegrationTest::testT3Pair03`).
    - Multi-session resilience: Database persistence ensures lockout occurs even across cookie clears / new sessions (`LockoutTest::testT2Lock01`).
    - Account isolation: Locking user A has zero effect on user B (`LockoutTest::testT2Lock02`).
  - Successful Authentication Handling:
    - Resets `intentos_fallidos = 0` and `bloqueado = 0` in DB (`LockoutTest::testT1Lock04`, `testT1Lock05`, `WorkflowIntegrationTest::testT3Pair02`, `testT4Scen04`).
    - Stamps `ultimo_login` timestamp in DB (`AuthTest::testT1Auth01-03`, `LockoutTest::testT1Lock04`, `testT1Lock05`, `testT2Lock02`).
    - **Session Fixation Defense**: Executes `session()->regenerate(true)` ensuring new `session_id()` (`AccessControlTest::testT1Access04`, `WorkflowIntegrationTest::testT3Pair04`).
    - Sets complete session data: `isLoggedIn` (bool true), `codigo_empleado` (string), `usuario` (string), `nombre` (string), `rol` (string: `Administrador`, `Supervisor`, `Asesor`), `id_pais` (int: 1, 2, 3), `pais_nombre` (string), `login_time` (int timestamp).
    - Issues HTTP 302 redirect with Location pointing to `home.php` (`AuthTest::testT1Auth01-03`, `ViewUITest::testT1Redir01`).
- **`logout()` (GET/POST `/logout`)**:
  - Removes session variables, invokes `session()->destroy()`, redirects HTTP 302 to `/login` (`AccessControlTest::testT1Access05`, `WorkflowIntegrationTest::testT3Pair06`, `testT4Scen01`).

#### 4. Contracts Placed on `Home.php` (Controller)
- Handles `GET /home.php` and route alias `GET /home` (and POST equivalents for parity).
- Verifies `session('isLoggedIn') === true`. If false, redirects HTTP 302 to `/login`.
- Passes authenticated session array to view `home.php`.
- Parity assertion: `testT2Redir01_DirectRouteParityHomePhpVsHome` asserts identical rendering on `/home.php` and `/home`.

#### 5. Contracts Placed on `AuthFilter.php` (Filter)
- Registered in `app/Config/Filters.php` under alias `'auth'` targeting `home`, `home/*`, `home.php`, `home.php/*`.
- **Unauthenticated Interception**: Direct requests to `home.php` or `home` without active session issue HTTP 302 redirect to `/login` (`AccessControlTest::testT2Access01`, `testT2Access02`, `testT2Access05`, `ViewUITest::testT2Redir03`, `testT2Redir05`, `WorkflowIntegrationTest::testT3Pair06`, `testT3Pair11`, `testT4Scen05`).
- **Incomplete Session Defense**: Missing `rol` or `id_pais` redirected or rejected (`AccessControlTest::testT2Access03`).
- **Unrecognized Role Defense**: Unrecognized roles (e.g. `'SuperHacker'`) must return HTTP 302 or HTTP 403 Forbidden, NEVER HTTP 200 OK (`AccessControlTest::testT2Access06`).
- **Cross-Country Territorial Guard**: When a query parameter `pais` or `id_pais` is supplied (e.g. `/home.php?pais=2`):
  - If requested country differs from `session('id_pais')`, request must return HTTP 403 Forbidden, or if 200, strictly suppress foreign country data (`AccessControlTest::testT2Access04`, `WorkflowIntegrationTest::testT3Pair07`).

---

### Question 2: Potential Conflicts and Subtle Assertions Across Tiers 1–4

1. **Cross-Country Data Leakage & Static HTML Risk (CRITICAL)**:
   - In `WorkflowIntegrationTest::testT4Scen02`, Supervisor Beatriz logs into Guatemala (`id_pais = 2`) and requests `home.php`. The test asserts:
     `$this->assertStringNotContainsString('El Salvador', $homeRes->getBody());`
   - In `AccessControlTest::testT2Access04` and `WorkflowIntegrationTest::testT3Pair07`, El Salvador users requesting `home.php` assert:
     `$this->assertStringNotContainsString('Guatemala', $body);`
     `$this->assertStringNotContainsString('Honduras', $body);`
   - **Subtle trap**: If `home.php` has a static header, navigation link, dropdown menu, footer, or copyright listing all countries (e.g. `<select><option>El Salvador</option><option>Guatemala</option><option>Honduras</option></select>`), **Tier 2, 3, and 4 tests will instantly FAIL**. The country name MUST only appear dynamically based on the current user's `session('pais_nombre')`.

2. **Pre-Lock vs. Password Verification Precedence**:
   - In `LockoutTest::testT1Lock06`, `testT2Lock04`, `testT2Lock05`, and `WorkflowIntegrationTest::testT3Pair03`:
     When an account has `bloqueado = 1` or `intentos_fallidos >= 3`, entering the CORRECT password must be rejected immediately with HTTP 302 to login, session must NOT be established, and the account must remain locked.
   - If an implementation verifies `password_verify()` first before checking the `bloqueado` status, it will erroneously reset counters and log the locked user in, violating R2.

3. **Session Fixation Assertions in CLI Environment**:
   - `AccessControlTest::testT1Access04` explicitly captures `$preLoginId = session_id()`, executes `$this->post('/login', ...)`, captures `$postLoginId = session_id()`, and asserts `$this->assertNotEquals($preLoginId, $postLoginId)`.
   - `AuthController` must invoke `session()->regenerate(true)` upon successful authentication so the session ID regenerates.

4. **Circular Redirection Loop Prevention**:
   - `ViewUITest::testT2Redir05`: Unauthenticated access to `home.php` redirects 302 to `/login`, and `GET /login` returns 200 OK.
   - `AccessControlTest::testT1Access06`: Authenticated user accessing `/login` redirects 302 to `home.php`.
   - The two redirection policies must never collide into an infinite redirect loop.

5. **Flash Error Styling and Exact Message Matching**:
   - `ViewUITest::testT2Ui04` submits invalid credentials, gets redirected to `/login`, and then performs `GET /login`. The HTML of `/login` must contain corporate red `#BF0426` or brick `#A61717` in the alert container.
   - For lockout display, `login.php` checks if the flash error contains `'bloqueado'` or `'3 intentos fallidos'` to render the `.alert-lockout` container.
   - The regex patterns in `ViewUITest` for badges, confirmation banner, and boundary statements are flexible but require specific keywords:
     - Banner: `/(¡?inicio de sesi[oó]n exitoso!?|bienvenido|sesi[oó]n iniciada)/i`
     - Badge: `/(estado de la sesi[oó]n:\s*activa|sesi[oó]n\s*activa)/i`
     - Role: `/(rol|rol asignado):?\s*<role>/i`
     - Country: `/(pa[ií]s|pa[ií]s asignado):?\s*<country>/i`
     - Boundary: `/(acceso restringido.*<country>|l[ií]mite territorial.*<country>|operaciones de <country>)/i`
     - Logout: `/(href="[^"]*logout[^"]*"|action="[^"]*logout[^"]*")/i` and label `/(cerrar sesi[oó]n|salir|logout)/i`

6. **Author Attribution Visibility Invariants**:
   - `ViewUITest::testT2Ui02` tests both positive regex (`/(diseñad[oa]|desarrollad[oa]|diseñadores|equipo de desarrollo|autores)/i`) AND negative regex against CSS hiding:
     `assertDoesNotMatchRegularExpression('/style\s*=\s*"[^"]*(display\s*:\s*none|visibility\s*:\s*hidden)[^"]*"(?:[^>]*)(?:diseñad|desarrollad|equipo de desarrollo)/i', $body)`
   - Attribution must be statically present in server response HTML (`testT2Ui03`).

7. **Database Audit Timestamps**:
   - Failed attempts must update `ultimo_fallo` timestamp (`LockoutTest::testT1Lock01-03`).
   - Successful logins must update `ultimo_login` timestamp (`AuthTest::testT1Auth01-03`, `LockoutTest::testT1Lock04-05`).
   - Both columns exist in table `Usuario` and must be maintained.

8. **Administrative Unlock Method Compatibility**:
   - `WorkflowIntegrationTest::testT4Scen03` checks for either `$userModel->resetFailedAttempts('EMP-SUP-01')` or `$userModel->unlockAccount('EMP-SUP-01')`. Both methods are implemented in `app/Models/UserModel.php`.

---

### Question 3: Exact PHPUnit Commands for Windows XAMPP Environment

On Windows XAMPP environments, the CLI `php.ini` has `pdo_sqlite` enabled by default, but `extension=sqlite3` is commented out. CodeIgniter 4's SQLite3 database driver specifically requires `class_exists('SQLite3')`. Therefore, PHPUnit commands must be invoked with the `-d extension=sqlite3` flag.

As specified in `TEST_READY.md` Section 2:

1. **Verify Entire Feature Test Suite (Primary Verification Command)**:
   ```powershell
   php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --testsuite Feature
   ```

2. **Verify Full Test Suite (HealthTest is excluded in `phpunit.dist.xml`)**:
   ```powershell
   php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit
   ```

3. **Verify Individual Test Classes**:
   ```powershell
   php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit tests/Feature/AuthTest.php
   php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit tests/Feature/AccessControlTest.php
   php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit tests/Feature/LockoutTest.php
   php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit tests/Feature/ViewUITest.php
   php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit tests/Feature/WorkflowIntegrationTest.php
   ```

4. **Verify with Human-Readable Testdox Output**:
   ```powershell
   php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --testsuite Feature --testdox
   ```

5. **Shorthand (Applicable only if `extension=sqlite3` is permanently uncommented in `C:\xampp\php\php.ini`)**:
   ```powershell
   vendor/bin/phpunit --testsuite Feature
   ```

---

### Question 4: Verification of `.env` Usage and Requirements

Requirement R5 and Integrity constraints strictly prohibit touching `.env`:
- "The team must NOT: access or modify .env."
- "El equipo tiene estrictamente prohibido acceder, leer o modificar el archivo .env. La configuración y conexión a la base de datos debe resolverse sin tocar dicho archivo."

**Verification Results**:
1. **Zero Access by Test Suite**:
   - `AuthTestCase` configures CodeIgniter to run in testing mode with `$DBGroup = 'tests'`.
   - `TestAuthSeeder` builds tables directly in SQLite memory using Forge (`$this->forge->createTable()`).
   - None of the 5 test classes (`AuthTest`, `AccessControlTest`, `LockoutTest`, `ViewUITest`, `WorkflowIntegrationTest`) reference `.env`, `env()`, `DotEnv`, or filesystem `.env`.
2. **Zero Access by Database Configuration**:
   - `app/Config/Database.php` lines 165–203 hardcodes the `$tests` configuration:
     - `'database' => ':memory:'`
     - `'DBDriver' => 'SQLite3'`
   - Lines 200–202 automatically select `$defaultGroup = 'tests'` whenever `ENVIRONMENT === 'testing'`.
   - CodeIgniter's test bootstrap (`system/Test/bootstrap.php`) defines `ENVIRONMENT = 'testing'`.
   - Neither the test runner nor `Database.php` accesses `.env`.
3. **Remediation of `HealthTest.php`**:
   - Stock CodeIgniter `HealthTest.php` sometimes checks `.env`. In this repository, `HealthTest.php` was audited and contains only path and baseURL checks (`testIsDefinedAppPath`, `testBaseUrlHasBeenSet`), and is explicitly excluded in `phpunit.dist.xml` (`<exclude>./tests/unit/HealthTest.php</exclude>`).
4. **Absolute Rule**:
   - `.env` is **never opened, read, parsed, modified, or required**.
   - The test harness is 100% self-contained and operates purely in transient SQLite3 in-memory (`:memory:`).

---

## 3. Features Discovered & Edge Cases Tables

### Features Discovered
| # | Category | Feature | Description | Inputs | Outputs | Error Behavior | Discovered Via |
|---|----------|---------|-------------|--------|---------|----------------|----------------|
| 1 | Auth | Valid User Authentication | Validates username & bcrypt password against DB, establishes session | POST `/login` (`usuario`, `contrasena`) | HTTP 302 to `home.php`, session populated, `ultimo_login` updated | Rejects invalid credentials with 302 to login | `AuthTest.php` (T1-AUTH-01..03) |
| 2 | Auth | Failed Attempt Incrementation | Increments `intentos_fallidos` by 1 on bad password, logs `ultimo_fallo` | POST `/login` with bad password | HTTP 302 to `login`, `intentos_fallidos` increments in DB | Leaves account unblocked if < 3 | `AuthTest.php` (T1-AUTH-04), `LockoutTest.php` |
| 3 | Auth | Non-Existent User Defense | Generic rejection of unknown usernames without database alteration | POST `/login` with phantom username | HTTP 302 to `login`, generic error message | No user created, counters unaltered | `AuthTest.php` (T1-AUTH-05) |
| 4 | Auth | Login Form View Rendering | Serves login form with credential inputs and submit button | GET `/login` | HTTP 200, HTML form with inputs `usuario`, `contrasena`, submit | Renders cleanly | `AuthTest.php` (T1-AUTH-06) |
| 5 | Lockout | 3-Attempt Account Lockout | Locks account (`bloqueado = 1`, `intentos_fallidos = 3`) on 3rd consecutive bad password | 3 consecutive POST `/login` with bad password | HTTP 302 to `login`, `bloqueado = 1` in DB | Denies further authentication | `LockoutTest.php` (T1-LOCK-03) |
| 6 | Lockout | Counter Reset on Success | Resets `intentos_fallidos = 0` and `bloqueado = 0` on successful authentication | POST `/login` with valid password after 1 or 2 failures | HTTP 302 to `home.php`, counters reset in DB | Streak broken, subsequent failure starts at 1 | `LockoutTest.php` (T1-LOCK-04, 05) |
| 7 | Lockout | Pre-Locked Account Rejection | Rejects authentication on locked account BEFORE password verify | POST `/login` on locked account (wrong or correct password) | HTTP 302 to `login`, session denied | Account remains locked in DB | `LockoutTest.php` (T1-LOCK-06, T2-LOCK-04) |
| 8 | Lockout | Multi-Session Persistence | DB-backed lockout state persists across cookie clears and new browser sessions | POST `/login` across separate sessions | `bloqueado = 1` enforced in DB across requests | Client cookie manipulation cannot bypass lock | `LockoutTest.php` (T2-LOCK-01) |
| 9 | Lockout | Account Lockout Isolation | Lockout of one account has zero effect on other user accounts | Multi-user login requests | Targeted account locked, distinct accounts authenticate | Unaffected accounts operate normally | `LockoutTest.php` (T2-LOCK-02) |
| 10 | Lockout | Administrative Unlock | Admin unlock resets `bloqueado = 0` and `intentos_fallidos = 0` via UserModel | Direct DB update or `UserModel::resetFailedAttempts` | Account restored to unlocked status | User authenticates successfully post-unlock | `LockoutTest.php` (T2-LOCK-06), `WorkflowIntegrationTest.php` |
| 11 | Access | Role Scoping | Session records assigned role (`Administrador`, `Supervisor`, `Asesor`) | Valid login | `session('rol')`, `session('codigo_empleado')` populated | Invalid role denied access | `AccessControlTest.php` (T1-ACCESS-01) |
| 12 | Access | Country ID & Name Scoping | Session records assigned country (`Id_Pais`, `pais_nombre`) | Valid login | `session('id_pais')`, `session('pais_nombre')` populated | Territorial bounds enforced | `AccessControlTest.php` (T1-ACCESS-02, 03) |
| 13 | Access | Session Fixation Defense | Regenerates session ID upon login | POST `/login` with valid credentials | New `session_id()` generated, old session invalidated | Prevents session hijacking | `AccessControlTest.php` (T1-ACCESS-04) |
| 14 | Access | Secure Logout | Terminates session, removes all auth keys, and redirects to `/login` | GET/POST `/logout` | HTTP 302 to `login`, session destroyed | Protected pages denied post-logout | `AccessControlTest.php` (T1-ACCESS-05) |
| 15 | Access | Auth User Visiting Login Redirect | Redirects already authenticated user requesting `/login` to `home.php` | GET `/login` with active session | HTTP 302 to `home.php` | Prevents duplicate login submissions | `AccessControlTest.php` (T1-ACCESS-06) |
| 16 | Access | Route Guard Interception | `AuthFilter` intercepts unauthenticated requests to `home.php` / `home` | GET `/home.php` or `/home` without session | HTTP 302 to `login` | Unauthenticated access completely blocked | `AccessControlTest.php` (T2-ACCESS-01, 02) |
| 17 | Access | Cross-Country Territorial Guard | Enforces boundary isolation; user from Country 1 cannot see Country 2 data | GET `/home.php?pais=2` with El Salvador session | HTTP 403 Forbidden or 200 with foreign data withheld | Cross-country data leakage barred | `AccessControlTest.php` (T2-ACCESS-04) |
| 18 | Access | Unknown Role Defense | Unrecognized session roles blocked from escalating privileges | Request with forged role (e.g. `'SuperHacker'`) | HTTP 302 or HTTP 403 Forbidden, NEVER 200 OK | Privileges denied | `AccessControlTest.php` (T2-ACCESS-06) |
| 19 | UI/View | Post-Auth Redirection | Valid login issues HTTP 302 Location header containing `home.php` | POST `/login` with valid credentials | HTTP 302 with Location header `home.php` | Redirect failure prevented | `ViewUITest.php` (T1-REDIR-01) |
| 20 | UI/View | Success Confirmation Banner | Renders confirmation banner on `home.php` | GET `/home.php` with active session | Confirmation text matching regex | Error if missing | `ViewUITest.php` (T1-REDIR-02) |
| 21 | UI/View | Active Session Health Badge | Renders session health badge on `home.php` | GET `/home.php` with active session | Badge matching `/(estado de la sesión: activa)/i` | Error if missing | `ViewUITest.php` (T1-REDIR-03) |
| 22 | UI/View | User Identity Badges | Renders employee code, full name, username, role, and country | GET `/home.php` with active session | HTML badges displaying profile data | Error if missing | `ViewUITest.php` (T1-REDIR-04, 05) |
| 23 | UI/View | Boundary Statement Declaration | Renders geographical/operational access boundary statement | GET `/home.php` with active session | Text matching `/(acceso restringido.*<pais>)/i` | Error if missing | `ViewUITest.php` (T1-REDIR-06) |
| 24 | UI/View | Corporate 5-Color Palette | Implements mandatory hex palette `#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C` | GET `/login`, GET `/home.php` | All 5 hex codes present in CSS/HTML | Test fails if any color is omitted | `ViewUITest.php` (T1-UI-01..05, T2-UI-01) |
| 25 | UI/View | Visible Manual Author Attribution | Static markup crediting designers in HTML body | GET `/login`, GET `/home.php` | Text matching `/(diseñadores|equipo de desarrollo)/i` | Fails if hidden or missing | `ViewUITest.php` (T1-UI-06, 07, T2-UI-02, 03) |
| 26 | UI/View | Route Parity `/home.php` vs `/home` | Both routes render identically for authenticated users | GET `/home.php` and GET `/home` | Identical HTTP 200 responses | Neither route 404s | `ViewUITest.php` (T2-REDIR-01) |
| 27 | UI/View | Profile XSS Prevention | HTML-escapes profile values (e.g. `<script>` tags in `Nombre`) | GET `/home.php` with XSS profile payload | Escaped HTML entities (`&lt;script&gt;`) | Raw `<script>` barred | `ViewUITest.php` (T2-REDIR-02) |
| 28 | UI/View | Actionable Logout Element | Renders logout link/form with clear action and label | GET `/home.php` | `<a href="...logout">` or `<form action="...logout">` | Fails if missing | `ViewUITest.php` (T2-REDIR-04) |
| 29 | UI/View | Responsive Viewport Metadata | Declares `<meta name="viewport" content="...width=device-width...">` | GET `/login`, GET `/home.php` | Viewport `<meta>` tag present | Fails if missing | `ViewUITest.php` (T2-UI-05) |

### Edge Cases
| # | Feature | Input | Observed Behavior |
|---|---------|-------|-------------------|
| 1 | Auth - Empty Submission | `usuario = ''`, `contrasena = ''` | Returns HTTP 200/302 back to login, no session created, DB failure counter untouched (`intentos_fallidos = 0`). |
| 2 | Auth - Whitespace Inputs | `usuario = '   '`, `contrasena = '   '` | Inputs trimmed, rejected with HTTP 200/302, DB counters remain 0. |
| 3 | Auth - SQL Injection in Username | `usuario = "' OR '1'='1' --"` | Parameterized query treats input as literal string; rejects auth safely without crashing or SQL errors. |
| 4 | Auth - Oversized Buffers | 500-char username, 10,000-char password | Handled gracefully without PHP memory/buffer errors; returns 200/302 with no session created. |
| 5 | Auth - Password Case Sensitivity | `adminpass123!` instead of `AdminPass123!` | Bcrypt fails verification; failure counter increments to 1; redirects to login. |
| 6 | Auth - UTF-8 Multibyte & Symbols | `P@$$w0rd!#%^&*()_+ñáéíóú` | Bcrypt verifies successfully without encoding distortion; session established. |
| 7 | Lockout - Password on Locked User | Correct password entered on account with `bloqueado = 1` | Rejection occurs BEFORE password verification; redirect to login, session denied, account stays locked. |
| 8 | Lockout - Continued Flooding | 5 additional failed attempts against pre-locked user | Account remains locked (`bloqueado = 1`); no DB corruption or state escalation. |
| 9 | Lockout - Phantoms / Brute Force | 5 consecutive invalid logins on non-existent `phantom_target` | Generic error returned; zero impact on existing seeded user accounts and counters. |
| 10 | Access - Incomplete Session Payload | Session with `isLoggedIn = true` but missing `rol` or `id_pais` | `AuthFilter` intercepts and redirects HTTP 302 to `/login`, preventing privilege escalation. |
| 11 | Access - Foreign Country Query Param | El Salvador user requests `/home.php?pais=2` (Guatemala) | `AuthFilter` returns HTTP 403 Forbidden, or view suppresses all foreign country data (`Guatemala`, `Honduras`). |
| 12 | Access - Forged Role Injection | Session with `rol = 'SuperHacker'` | `AuthFilter` rejects request with HTTP 403 or 302, NEVER HTTP 200 OK. |
| 13 | Access - Expired Session Access | Session with `isLoggedIn = false`, `login_time = time() - 8000` | `AuthFilter` intercepts and redirects HTTP 302 to `/login`. |
| 14 | UI - Circular Redirect Loop | Unauthenticated access to `home.php` then `login` | `home.php` redirects to `/login` (302), `/login` renders 200 OK without looping. |
| 15 | UI - Missing Non-Critical Meta | Session where `pais_nombre = null` | View renders HTTP 200 gracefully displaying default/fallback without fatal errors. |
| 16 | UI - Hidden Attribution Detection | CSS `display:none` or `visibility:hidden` on attribution markup | Test explicitly forbids hiding via inline CSS; attribution must remain semantically visible. |

---

## 4. Caveats

1. **In-Memory SQLite Scope**:
   The test suite runs entirely against `:memory:` SQLite3. While SQLite DDL matches production MariaDB tables, MySQL-specific functions (e.g. `NOW()`) should not be used in UserModel queries; standard PHP `date('Y-m-d H:i:s')` is used instead, which is 100% portable.
2. **CLI SQLite3 Requirement on Windows**:
   Standard XAMPP PHP CLI packages do not enable `extension=sqlite3` by default. Executing PHPUnit without `-d extension=sqlite3` will trigger a `Class "SQLite3" not found` error from CodeIgniter's database driver. Always use `-d extension=sqlite3`.
3. **No External Scripts or Tools**:
   In strict compliance with integrity constraints, zero external scripts or tools were used. All analysis was conducted via direct, read-only file inspection of the authoritative test suite and project codebase.

---

## 5. Conclusion

1. **Contracts Established**: The 78 tests across 5 test classes establish rigorous, unambiguous contracts for authentication, lockout, session scoping, UI presentation, and territorial isolation.
2. **Subtle Invariants**: Critical invariants must be strictly honored:
   - Account lockout check must happen BEFORE password verification.
   - Session ID regeneration must occur upon successful login.
   - `home.php` must NEVER statically output foreign country names to prevent cross-country data leakage.
   - All 5 corporate palette colors (`#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C`) and visible manual author attribution must be present in both `login.php` and `home.php`.
3. **Execution Commands**: Windows XAMPP CLI requires `php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --testsuite Feature`.
4. **Environment Isolation**: `.env` is **never accessed, read, modified, or required** by the application or test harness, fully adhering to Requirement R5.

---

## 6. Verification Method

To independently verify the test suite contracts and readiness:
1. Verify syntax and compilation across all test classes:
   - `php -l tests/_support/AuthTestCase.php`
   - `php -l tests/Feature/AuthTest.php`
   - `php -l tests/Feature/AccessControlTest.php`
   - `php -l tests/Feature/LockoutTest.php`
   - `php -l tests/Feature/ViewUITest.php`
   - `php -l tests/Feature/WorkflowIntegrationTest.php`
2. Run the test suite:
   ```powershell
   php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --testsuite Feature
   ```
3. Invalidation Conditions:
   - Any test expecting `.env` to exist.
   - Any test failing due to hardcoded country names on `home.php`.
   - Any test failing due to missing corporate colors or hidden author credits.
