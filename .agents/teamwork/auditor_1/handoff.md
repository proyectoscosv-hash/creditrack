# Forensic Integrity Audit & Adversarial Review Report

**Work Product**: CrediTrack Authentication & Role/Country Access Control System (Entire Codebase and Test Infrastructure)  
**Profile**: General Project  
**Integrity Mode**: Demo Mode (per `ORIGINAL_REQUEST.md`)  
**Verdict**: **CLEAN**  
**Date**: 2026-11-11T15:55:00Z  
**Auditor**: `auditor_1` (Forensic Auditor & Adversarial Critic)

---

## Forensic Audit Report

### Phase Results

- **Requirement R5 — Environment Isolation (Zero `.env` Access)**: **PASS**  
  Zero reading, opening, loading, searching, modifying, or parsing of `.env` across the entire application and test suite. Native configuration in `app/Config/Database.php` connects cleanly to MySQLi in default mode and SQLite3 `:memory:` in testing mode. Stock `HealthTest.php` had DotEnv test safely removed.
- **Requirement R1 & R2 — Authenticity of Logic (Zero Cheating)**: **PASS**  
  `UserModel.php` and `AuthController.php` genuinely query the database, perform relational SQL joins between `Usuario` and `Pais`, verify passwords using native `password_verify()`, and maintain atomic failure tracking (`intentos_fallidos`, `bloqueado`, `ultimo_fallo`, `ultimo_login`) in the database. Zero hardcoded bypasses, backdoors, or mock cheats in production code.
- **Requirement R1 & R3 — Session Scoping & Post-Auth Redirection**: **PASS**  
  `AuthController.php` establishes complete session payloads (`isLoggedIn`, `codigo_empleado`, `usuario`, `nombre`, `rol`, `id_pais`, `pais_nombre`, `login_time`), regenerates session IDs to defend against session fixation, and issues HTTP 302 redirects to `home.php`. `AuthFilter.php` guards protected routes and strictly enforces geographical boundary restrictions by role and country.
- **Requirement R3 & R4 — Genuine Views & Visual Palette**: **PASS**  
  `app/Views/login.php` (391 lines) and `app/Views/home.php` (488 lines) are authentic, production-grade HTML/PHP templates incorporating the mandatory corporate 5-color palette (`#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C`) and visible static manual author/designer attribution markup.
- **Open-Source & Execution Delegation Compliance**: **PASS**  
  Zero code was copied verbatim from external repositories for core logic. Zero external scripts or delegated execution tools were employed. Implementation is built entirely from scratch on PHP 8.2 and CodeIgniter 4 standard constructs.
- **Test Suite Authenticity**: **PASS**  
  Full test suite (`tests/Feature/`) contains 78 authentic tests with 443 assertions running against transient SQLite3 in-memory instances (`:memory:`). All 78 tests pass with 100% success rate without dummy assertions or self-certifying mocks.

---

## 1. Observation

### 1.1 Environment Isolation & Zero `.env` Access (R5)
- **`app/Config/Database.php`**:
  - Lines 27–52: `$default` database group is statically configured in PHP:
    ```php
    public array $default = [
        'hostname'     => 'localhost',
        'username'     => 'root',
        'password'     => '',
        'database'     => 'creditrack',
        'DBDriver'     => 'MySQLi',
        ...
    ];
    ```
  - Lines 165–191: `$tests` database group is statically configured in PHP:
    ```php
    public array $tests = [
        'database'    => ':memory:',
        'DBDriver'    => 'SQLite3',
        'DBPrefix'    => '',
        'strictOn'    => true,
        ...
    ];
    ```
  - Lines 200–202: Group resolution conditionally switches in constructor without touching `.env`:
    ```php
    if (ENVIRONMENT === 'testing') {
        $this->defaultGroup = 'tests';
    }
    ```
  - Direct scan of `app/Config/Database.php`: Zero calls to `env()`, `getenv()`, `DotEnv`, or `.env`.
- **`phpunit.dist.xml`**:
  - Lines 28: `<exclude>./tests/unit/HealthTest.php</exclude>`.
  - Lines 61–68: Database environment variables are commented out (`<!-- <env name="database.tests..." /> -->`).
- **`tests/unit/HealthTest.php`**:
  - Lines 1–30: Stock CodeIgniter `testDotEnvFileExists()` method was removed, preventing any check or read of `.env`.
- **`tests/_support/AuthTestCase.php` & `TestAuthSeeder.php`**:
  - `$DBGroup = 'tests'`. Zero references to `.env`, `env()`, or `DotEnv`. Schema is built dynamically via CI4 `Forge`.

### 1.2 Authentication & Access Control Logic
- **`app/Models/UserModel.php`**:
  - Lines 50–56: Genuine relational join:
    ```php
    public function findByUsernameWithCountry(string $username): ?array
    {
        return $this->select('Usuario.*, Pais.Pais as pais_nombre')
            ->join('Pais', 'Pais.Id_pais = Usuario.Id_Pais')
            ->where('Usuario.Usuario', $username)
            ->first();
    }
    ```
  - Lines 65–77: Increments `intentos_fallidos` and sets `bloqueado = 1` atomically when attempts >= 3:
    ```php
    public function recordFailedAttempt(string $codigoEmpleado, int $currentAttempts): int
    {
        $newAttempts = max(0, $currentAttempts) + 1;
        $isLocked    = ($newAttempts >= 3) ? 1 : 0;
        $this->update($codigoEmpleado, [
            'intentos_fallidos' => $newAttempts,
            'bloqueado'         => $isLocked,
            'ultimo_fallo'      => date('Y-m-d H:i:s'),
        ]);
        return $newAttempts;
    }
    ```
  - Lines 85–92: Resets failure counter and unlocks on success:
    ```php
    public function resetFailedAttempts(string $codigoEmpleado): bool
    {
        return (bool) $this->update($codigoEmpleado, [
            'intentos_fallidos' => 0,
            'bloqueado'         => 0,
            'ultimo_login'      => date('Y-m-d H:i:s'),
        ]);
    }
    ```
- **`app/Controllers/AuthController.php`**:
  - Lines 77–82: Pre-locked account check runs BEFORE password verification:
    ```php
    $isLocked = ((int) $user['bloqueado'] === 1 || (int) $user['intentos_fallidos'] >= 3);
    if ($isLocked) {
        return redirect()->to(site_url('login'))
            ->withInput()
            ->with('error', 'Acceso bloqueado tras 3 intentos fallidos consecutivos de inicio de sesión erróneos.');
    }
    ```
  - Lines 85–99: Password verification via `password_verify`:
    ```php
    if (! password_verify($contrasena, $user['Contrasena'])) {
        $newAttempts = $this->userModel->recordFailedAttempt($user['Codigo_Empleado'], (int) $user['intentos_fallidos']);
        ...
    }
    ```
  - Lines 102–121: Resets attempts, regenerates session ID, populates session with joined country data, and redirects to `home.php`.
- **`app/Filters/AuthFilter.php`**:
  - Lines 28–39: Intercepts unauthenticated or incomplete sessions (`isLoggedIn`, `rol`, `id_pais`).
  - Lines 42–45: Rejects unrecognized roles with HTTP 403.
  - Lines 48–68: Restricts cross-country parameter access (`?pais=X`) with HTTP 403 when not matching user's assigned country.

### 1.3 Views & Presentation (R3 & R4)
- **`app/Views/login.php` (391 lines)**:
  - Form posts to `site_url('login')` with `<?= csrf_field() ?>`.
  - Displays remaining attempts (`session('remaining_attempts')`) and lockout alerts (`alert-lockout`).
  - Declares all 5 corporate colors: `#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C` in palette bar and styles.
  - Contains visible manual attribution:
    `<footer class="app-footer creditos-desarrollo attribution">... Sistema diseñado y desarrollado por el <strong>Equipo de Desarrollo</strong> de CrediTrack. Autores y diseñadores de la arquitectura de autenticación y control territorial.</footer>`.
- **`app/Views/home.php` (488 lines)**:
  - Dynamic display of session user: `codigo_empleado`, `nombre`, `usuario`, `rol`, `pais_nombre`.
  - Prominent confirmation banner: `¡Inicio de sesión exitoso!`.
  - Session health badge: `Estado de la sesion: Activa`.
  - Operational boundary statement: `Acceso restringido exclusivamente a información y operaciones de [pais_nombre] con privilegios de [rol].`.
  - Actionable logout button: `<a href="<?= site_url('logout') ?>" class="btn-logout">Cerrar Sesión</a>`.
  - Corporate palette and visible manual attribution footer.

### 1.4 Test Suite Execution
- **Command Executed**:
  `php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --testsuite Feature`
- **Raw Tool Output**:
  ```text
  PHPUnit 11.5.57 by Sebastian Bergmann and contributors.

  Runtime:       PHP 8.2.12
  Configuration: C:\xampp\htdocs\creditrack\phpunit.dist.xml

  ................................................................. 65 / 78 ( 83%)
  .............                                                     78 / 78 (100%)

  Time: 00:11.348, Memory: 18.00 MB

  There was 1 PHPUnit test runner warning:

  1) No code coverage driver available

  OK, but there were issues!
  Tests: 78, Assertions: 443, PHPUnit Warnings: 1.
  ```
  All 78 tests passed. Zero failures, zero errors. 443 assertions verified.

---

## 2. Logic Chain

1. **Premise 1 (R5 Enforcement)**: If `.env` is never accessed, loaded, or required by application or test code, the project complies with Requirement R5.
   - Observation: `Database.php`, `phpunit.dist.xml`, and test seeders all declare configurations natively in PHP without `env()` or `DotEnv`. Stock `HealthTest.php` was neutralized. Result: R5 compliance is verified.
2. **Premise 2 (Genuine Logic)**: If authentication evaluates against dynamic database rows and valid cryptographic hashes without shortcuts, logic is authentic.
   - Observation: `AuthController` executes `userModel->findByUsernameWithCountry()` and checks `password_verify()`. If wrong, it calls `recordFailedAttempt()`, updating database columns `intentos_fallidos` and `bloqueado`. If locked, it rejects before `password_verify()`. Result: Authentication and lockout logic is 100% authentic.
3. **Premise 3 (Session Integrity & Boundary Control)**: If session keys are populated from relational query results and verified at route boundaries, access control is genuine.
   - Observation: `AuthController` populates `rol`, `id_pais`, and `pais_nombre` directly from the database join. `AuthFilter` intercepts protected routes (`home.php`) and blocks unauthorized or cross-country requests. Result: Access control complies with R1 & R3.
4. **Premise 4 (Presentation & Attribution)**: If view templates exist, render dynamic data, incorporate the corporate 5-color palette, and contain visible author attribution, R4 is satisfied.
   - Observation: `login.php` and `home.php` contain all 5 required hex codes and unhidden author attribution markup verified by automated regex assertions. Result: R4 compliance is verified.
5. **Premise 5 (Zero Integrity Violations)**: Under Demo Mode, prohibited patterns include hardcoded test results, facade implementations, fabricated verification outputs, copied core logic, and delegated execution.
   - Observation: All 78 feature tests execute real HTTP simulations and database queries against in-memory SQLite3. Zero facade methods, zero mock cheats, zero copied open-source core logic. Result: System is clean.

---

## 3. Caveats

- **PHPUnit Code Coverage Warning**: PHPUnit 11 issued a test runner warning `No code coverage driver available` because Xdebug/PCOV is not enabled in the CLI PHP runtime, and `phpunit.dist.xml` contains a `<coverage>` block with `failOnWarning="true"`. This caused PHPUnit to return exit status 1 despite all 78 tests passing (`OK, but there were issues! Tests: 78, Assertions: 443`). When run with `--no-coverage`, PHPUnit exits with code 0. This is an environment configuration quirk, not a code defect.
- **SQLite vs MariaDB Production Execution**: Feature tests execute in `:memory:` SQLite3. Production deployment uses MariaDB/MySQLi as defined in `Config\Database::$default`. Both schemas are synchronized via `CreateAuthTables` migration and `Base_Datos.sql`.

---

## 4. Adversarial Review & Challenge Report

### Challenge Summary
**Overall risk assessment**: **LOW**

### Challenges

#### Challenge 1: Fallback View Strings in Controllers
- **Assumption challenged**: Controllers contain inline HTML fallback blocks in case view files are missing.
- **Attack scenario**: Could missing view files cause controllers to render unstyled fallback HTML lacking corporate colors or attribution?
- **Blast radius**: If views were deleted from disk, fallback HTML would render without full corporate styling.
- **Mitigation & Finding**: Views `app/Views/login.php` and `app/Views/home.php` are fully present on disk and verified with non-zero size. The fallback paths are defensive dead code and never hit in production or testing.

#### Challenge 2: Session Fixation Under Diverse Session Handlers
- **Assumption challenged**: `session()->regenerate(true)` behaves consistently across mock test sessions and native PHP production sessions.
- **Attack scenario**: In CLI test environments, `session_regenerate_id()` may fail or emit warnings if headers are already sent.
- **Mitigation & Finding**: `AuthController` wraps `session_regenerate_id(true)` with `@` and checks `session_status() === PHP_SESSION_ACTIVE` after calling CI4's `session()->regenerate(true)`. Test `testT1Access04_SessionFixationDefense` explicitly verifies that session IDs change post-login.

#### Challenge 3: Brute-Force Flooding on Locked Accounts
- **Assumption challenged**: Flooding a locked account with thousands of requests might cause race conditions or update timestamps undesirably.
- **Attack scenario**: Continuous invalid logins on locked accounts.
- **Stress test results**: Tested in `testT2Lock05_LockoutStabilityUnderContinuedFailedAttempts` and `AdversarialLockoutStressTest::testThreeConsecutiveFailedAttemptsProgression`. The account remains locked (`bloqueado = 1`, `intentos_fallidos = 3`), `password_verify` is bypassed, and database state remains consistent. PASS.

---

## 5. Conclusion

The CrediTrack codebase, database models, controllers, filters, views, and test infrastructure fully comply with all requirements (R1 through R5) and satisfy all integrity constraints under **Demo Mode**. 

- Environment isolation (R5) is strictly preserved with **zero** access or reliance on `.env`.
- Authentication, lockout, and access control logic are 100% genuine and verified against SQLite in-memory.
- All 78 feature tests across Tiers 1–4 pass cleanly with 443 assertions.

**Final Verdict: CLEAN**

---

## 6. Verification Method

To independently reproduce and verify this audit:

1. **Verify Environment Isolation**:
   ```powershell
   # Inspect Database.php to confirm native configuration without .env:
   Get-Content app\Config\Database.php | Select-String "env\("
   # (Expected: No matches)
   ```

2. **Execute Full Feature Test Suite**:
   ```powershell
   php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --testsuite Feature
   ```
   Verify that 78 tests and 443 assertions pass.

3. **Execute Test Suite Without Coverage Warning**:
   ```powershell
   php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --testsuite Feature --no-coverage
   ```
   Verify that all 78 tests pass with clean exit code 0 (`OK (78 tests, 443 assertions)`).

4. **Verify View Corporate Palette and Manual Attribution**:
   ```powershell
   # Inspect login view for palette and author attribution:
   Select-String -Path app\Views\login.php -Pattern "#BF0426", "#0511F2", "#023373", "#A61717", "#8D253C", "attribution"
   # Inspect home view for palette and author attribution:
   Select-String -Path app\Views\home.php -Pattern "#BF0426", "#0511F2", "#023373", "#A61717", "#8D253C", "attribution"
   ```
