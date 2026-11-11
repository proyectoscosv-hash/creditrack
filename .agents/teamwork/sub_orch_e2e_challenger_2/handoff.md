# Adversarial Challenge Report — E2E Testing Track (Challenger 2)

**Document**: E2E Test Suite Adversarial Robustness & Integrity Audit  
**Author**: Empirical Challenger 2 (`sub_orch_e2e_challenger_2`)  
**Verdict**: **CHALLENGE**  
**Risk Assessment**: **HIGH**  
**Target State Folder**: `c:\xampp\htdocs\creditrack\.agents\teamwork\sub_orch_e2e_challenger_2\handoff.md`  
**Date**: 2026-11-10T21:50:00Z  

---

## 1. Observation

Direct observations from codebase inspection, PHP CLI runtime probes, and static test analysis:

### Observation 1: Synthetic Session Injection & Severed Pipeline in Tier 4 Lifecycles
In `tests/Feature/WorkflowIntegrationTest.php` lines 378–410 (`testT4Scen01_FieldAgentCompleteShiftLifecycle`):
```php
        // 2. Submit Valid Credentials
        $loginPost = $this->post('login', [
            'usuario'    => 'asesor_sv',
            'contrasena' => 'AsesorSv123!',
        ]);
        $loginPost->assertStatus(302);
        $postLoc = $loginPost->response()->getHeaderLine('Location');
        $this->assertMatchesRegularExpression('/home\.php/i', $postLoc);

        // 3. Render Landing View (home.php)
        $homeRes = $this->withSession([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ASE-01',
            'usuario'         => 'asesor_sv',
            'nombre'          => 'Juan Perez',
            'rol'             => 'Asesor',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ])->get('home.php');
```
Step 2 logs in, but does not assert session variables. Step 3 ignores whatever session Step 2 established and injects a hardcoded mock array via `$this->withSession([...])`.  
In lines 403–410:
```php
        // 4. Secure Shift Logout
        $logoutRes = $this->get('logout');
        $logoutRes->assertStatus(302);

        // 5. Verify protected route is barred post-logout
        $postLogoutHome = $this->get('home.php');
```
Step 4 calls `get('logout')` with NO session passed. In CodeIgniter 4's `FeatureTestTrait` line 204 (`system/Test/FeatureTestTrait.php`):
```php
        // Simulate having a blank session
        $_SESSION = [];
```
`call()` initializes `$_SESSION = []` on every call. Thus, Step 4 logs out an already-empty session, and Step 5 requests `home.php` with an already-empty session.  
Identical decoupling occurs in `testT4Scen02_RegionalSupervisorMultiCountryGovernance` (lines 421–448), `testT3Pair02_CounterResetOnSuccessAndCountryAssignment` (lines 68–94), and `testT3Pair09_SuccessiveMultiUserSessionSwitchingAcrossCountries` (lines 269–299 where `/login` is never called for either user).

### Observation 2: Incomplete Multi-Session Cookie Clearing in LockoutTest
In `tests/Feature/LockoutTest.php` lines 216–243 (`testT2Lock01_MultiSessionCookieClearingResilience`):
```php
        // Session 1: Attempt 1
        $this->post('/login', [
            'usuario'    => 'user_clean',
            'contrasena' => 'Wrong1',
        ]);

        // Session 1: Attempt 2
        $this->post('/login', [
            'usuario'    => 'user_clean',
            'contrasena' => 'Wrong2',
        ]);

        // Simulate cookie clear / new browser session
        $result3 = $this->withSession([])->post('/login', [
            'usuario'    => 'user_clean',
            'contrasena' => 'Wrong3',
        ]);

        $result3->assertStatus(302);

        $this->seeInDatabase('Usuario', [
            'Usuario'           => 'user_clean',
            'intentos_fallidos' => 3,
            'bloqueado'         => 1,
        ]);
```
In `FeatureTestTrait`, attempts 1 and 2 already run with empty sessions (`$_SESSION = []`). `$this->withSession([])` on attempt 3 does not simulate cookie clearing. Furthermore, `testT2Lock01` stops after attempt 3; it never tests the essential 4th attempt with legitimate credentials on `user_clean` after the cookie reset.

### Observation 3: Default PHP Execution Fails Due to Unloaded `sqlite3` Extension
Executing `php vendor/phpunit/phpunit/phpunit --testsuite Feature`:
```
CodeIgniter\Exceptions\CriticalError: The required PHP extension "sqlite3" is not loaded. Install and enable it to use "SQLite3" driver.
Tests: 78, Assertions: 0, Errors: 78, PHPUnit Warnings: 1.
```
Executing `php -m` shows `pdo_sqlite` is loaded, but `sqlite3` is not.  
Executing `php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --testsuite Feature` loads the driver properly.  
Neither `TEST_INFRA.md` nor `TEST_READY.md` mention the `-d extension=sqlite3` flag.

### Observation 4: Default `vendor/bin/phpunit` Runs `HealthTest.php` Which Reads `.env` (R5 Breach Hazard)
In `phpunit.dist.xml` lines 23–31:
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
In `tests/unit/HealthTest.php` lines 24–26:
```php
        // Check the baseURL in .env
        if (is_file(HOMEPATH . '.env')) {
            $env = preg_grep('/^app\.baseURL = ./', file(HOMEPATH . '.env')) !== false;
        }
```
If a user or CI runner follows `TEST_READY.md` line 37 (`vendor/bin/phpunit`), PHPUnit runs the default `App` testsuite first, which runs `HealthTest.php` and reads `.env` via `file(HOMEPATH . '.env')`.

### Observation 5: Inconsistent Author Attribution Regular Expressions
In `tests/Feature/ViewUITest.php`:
- `testT1Ui06` & `testT1Ui07` (lines 341, 366): `/(diseñad[oa]|desarrollad[oa]|diseñadores|equipo de desarrollo|autores)/i`
- `testT2Ui03` (line 573): `/(diseñad[oa]|desarrollad[oa]|equipo de desarrollo)/i` (omits `diseñadores` and `autores`)
In `tests/Feature/WorkflowIntegrationTest.php`:
- `testT3Pair05` (line 178): `/(diseñad[oa]|desarrollad[oa]|diseñadores|equipo de desarrollo)/i` (omits `autores`)

### Observation 6: Permissive Cross-Country Boundary Assertions
In `tests/Feature/AccessControlTest.php` lines 216–222 (`testT2Access04_CrossCountryBoundaryIsolationEnforcement`):
```php
        $statusCode = $result->response()->getStatusCode();
        $this->assertTrue(in_array($statusCode, [200, 403], true));

        if ($statusCode === 200) {
            $body = (string) $result->getBody();
            // Data or privileges for Guatemala must not be rendered to an El Salvador asesor
            $this->assertStringNotContainsString('País Asignado: Guatemala', $body);
        }
```
Accepts 200 or 403, and on 200 only asserts absence of the exact string `'País Asignado: Guatemala'`.

---

## 2. Logic Chain

1. **Premise 1 (Lifecycle Integrity)**: An End-to-End integration test suite (Tiers 3 & 4) must test true system behavior across boundaries, specifically that authentication populates session state and that subsequent protected routes consume and enforce that real session state.
2. **Step 1 (From Observation 1)**: In `WorkflowIntegrationTest.php` (`testT4Scen01`, `testT4Scen02`, `testT3Pair02`, `testT3Pair09`), the test author overwrote the session between login and landing with hardcoded array data (`$this->withSession([...])`), and executed `logout` without an active session.
3. **Inference 1**: If the application's login logic fails to populate `rol`, sets invalid `id_pais`, or if `logout` completely fails to destroy the session, `testT4Scen01` and `testT4Scen02` will still pass. Therefore, Tier 4 tests do not validate full realistic multi-step user lifecycles.
4. **Step 2 (From Observation 2)**: In `LockoutTest::testT2Lock01`, the test stops after 3 failed attempts and asserts database values. It never asserts attempt 4 with correct credentials on the newly locked account in a fresh session.
5. **Inference 2**: Multi-session cookie clearing resilience is only half-tested: it proves DB counter accumulation across calls, but leaves untested the final boundary where a legitimate user is barred from logging in with their real password in a fresh session.
6. **Step 3 (From Observation 3 & 4)**: The environment requires `-d extension=sqlite3` to run tests, and running the default suite (`vendor/bin/phpunit`) triggers `HealthTest.php` which executes `file(HOMEPATH . '.env')`.
7. **Inference 3**: Following the documentation in `TEST_INFRA.md` and `TEST_READY.md` results in runtime failures and risks violating Requirement R5.
8. **Step 4 (From Observation 5 & 6)**: Attribution regexes reject `'autores'` in `testT2Ui03` and `testT3Pair05` while accepting it in `testT1Ui06` and `testT1Ui07`; cross-country checks allow 200 responses with foreign country data as long as the exact phrase `'País Asignado: Guatemala'` is avoided.

---

## 3. Caveats

1. The application routes and views (`/login`, `/home.php`) are currently in development by parallel tracks (M2/M3), which explains why Feature tests currently return `PageNotFoundException`.
2. The Corporate 5-Color Palette assertions (`#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C`) are well implemented, case-insensitive, and asserted across both views.
3. Database isolation using `:memory:` SQLite via Forge prefixing (`TestAuthSeeder`) is architecturally sound and avoids `.env` reliance when executing Feature tests in isolation.

---

## 4. Conclusion

**Definitive Verdict**: **CHALLENGE**

The test suite cannot be approved in its current state due to two critical issues:
1. **Tier 4 Lifecycle Decoupling**: `WorkflowIntegrationTest` tests do not chain the authentic session created by `POST /login` into `GET home.php` and `GET /logout`, creating false confidence while masking potential authentication/session defects.
2. **R5 Breach Hazard in Default Test Suite**: `phpunit.dist.xml` default suite includes `tests/unit/HealthTest.php`, which reads `.env` via `file(HOMEPATH . '.env')` when `vendor/bin/phpunit` is executed.

### Actionable Remediation Required:
1. **Fix Tier 4 Lifecycles in `WorkflowIntegrationTest.php`**:
   - In `testT4Scen01` and `testT4Scen02`, capture the real session after login (`$session = $_SESSION;`) and pass it to `$this->withSession($session)->get('home.php')`.
   - In Step 4, pass `$this->withSession($session)->get('logout')`.
   - In Step 5, verify that `$this->withSession($_SESSION)->get('home.php')` is redirected to `/login` because `logout` actually cleared `isLoggedIn`.
   - In `testT3Pair09`, execute actual logins for User 1 and User 2 instead of purely synthetic sessions.
2. **Complete Lockout Multi-Session Test in `LockoutTest.php`**:
   - In `testT2Lock01`, add attempt 4 with the valid password (`AdminPass123!`) on `user_clean` in a fresh session and assert rejection (HTTP 302 to login, `isLoggedIn = false`).
3. **Protect Requirement R5 in Test Configuration**:
   - Exclude `tests/unit/HealthTest.php` or update `phpunit.dist.xml` to set `defaultTestSuite="Feature"`.
   - Document the requirement to execute PHPUnit with `-d extension=sqlite3` (e.g. `php -d extension=sqlite3 vendor/bin/phpunit --testsuite Feature`) in `TEST_INFRA.md` and `TEST_READY.md`.
4. **Harmonize Attribution Regexes**:
   - Standardize author attribution regexes across `ViewUITest` (`testT2Ui03`) and `WorkflowIntegrationTest` (`testT3Pair05`) to match `testT1Ui06` (`/(diseñad[oa]|desarrollad[oa]|diseñadores|equipo de desarrollo|autores)/i`).
5. **Tighten Country Boundary Assertions**:
   - In `testT2Access04`, assert that foreign country names (e.g. `Guatemala`) do not appear in the body when an El Salvador user views `home.php`.

---

## 5. Verification Method

To independently verify these findings:

1. **Verify `sqlite3` CLI requirement**:
   ```powershell
   # Observe failure without extension flag:
   php vendor/phpunit/phpunit/phpunit --testsuite Feature
   # Observe proper extension loading with flag:
   php -d extension=sqlite3 -m | Select-String "sqlite3"
   ```
2. **Inspect Synthetic Session Seam in `WorkflowIntegrationTest.php`**:
   Examine lines 388–408 of `tests/Feature/WorkflowIntegrationTest.php`. Notice `$this->withSession([...])` replaces the session, and `get('logout')` has no session context.
3. **Inspect R5 Breach in `HealthTest.php`**:
   Examine lines 24–26 of `tests/unit/HealthTest.php`. Notice `file(HOMEPATH . '.env')` is called unconditionally if `.env` exists.
4. **Inspect Attribution Regex Discrepancy**:
   Compare line 341 of `tests/Feature/ViewUITest.php` (contains `autores`) with line 573 of `tests/Feature/ViewUITest.php` (omits `autores`) and line 178 of `tests/Feature/WorkflowIntegrationTest.php` (omits `autores`).
