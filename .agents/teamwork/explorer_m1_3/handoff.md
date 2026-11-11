# Handoff Report: `UserModel` Design & PHPUnit Testing Strategy

**Milestone**: Milestone 1 (M1_db_models)  
**Agent**: Explorer 3 (`explorer_m1_3`)  
**Target Project**: CrediTrack (`c:\xampp\htdocs\creditrack`)  
**Date**: 2026-11-10  
**Handoff Type**: Hard (Investigation Complete)  

---

## 1. Observation

### 1.1 Existing Models & CodeIgniter 4 Model Base Classes
- **`app/Models/`**: Currently empty except for `.gitkeep`. No user models or custom base classes exist yet in the application directory.
- **`system/Model.php` (lines 92-120)**: Extends `CodeIgniter\BaseModel`. Defines the core Active Record and Query Builder integration:
  - `protected $table`: database table name.
  - `protected $primaryKey = 'id'`: primary key name.
  - `protected $useAutoIncrement = true`: whether the PK uses auto-increment.
  - `protected $builder`: Query Builder instance.
  - `protected function doFirst()` (lines 276-307): executes `limit(1, 0)->get()->getFirstRow($this->tempReturnType)` and respects previous builder constraints (such as `select()`, `join()`, and `where()`). Returns an array or `null`.
- **`system/BaseModel.php` (lines 102-180, 1033-1045)**:
  - `protected $returnType = 'array'` (line 102): results format default.
  - `protected $protectFields = true` (line 141): enforces field protection.
  - `protected $allowedFields = []` (line 149): list of attributes allowed for mass assignment.
  - `protected $useTimestamps = false` (line 157): automatic `creado_en`/`actualizado_en` tracking.
  - `protected $createdField = 'created_at'` / `protected $updatedField = 'updated_at'`.
  - `public function update($id = null, $row = null): bool` (line 1033): validates that `$id` is integer or non-empty string, applies mass assignment protection, and executes an `UPDATE` statement, returning `bool`.

### 1.2 Test Suite Setup & PHPUnit Configuration
- **`phpunit.dist.xml`**:
  - Bootstrap: `system/Test/bootstrap.php` (line 5), which initializes CodeIgniter 4 with `ENVIRONMENT = 'testing'`.
  - Configuration attributes: `failOnWarning="true"` (line 11).
  - Test suites: `App` -> `./tests` (lines 23-27).
- **`app/Config/Database.php` (lines 165-203)**:
  - Defines `$tests` connection group with `'database' => ':memory:'`, `'DBDriver' => 'SQLite3'`, `'DBPrefix' => 'db_'`, `'foreignKeys' => true`.
  - Line 200-202:
    ```php
    if (ENVIRONMENT === 'testing') {
        $this->defaultGroup = 'tests';
    }
    ```
- **CLI Probing & Verbatim Outputs**:
  - Running PHPUnit directly:
    ```
    PS C:\xampp\htdocs\creditrack> php vendor/phpunit/phpunit/phpunit tests/unit/HealthTest.php
    Time: 00:00.146, Memory: 12.00 MB
    There was 1 PHPUnit test runner warning:
    1) No code coverage driver available
    OK, but there were issues!
    Tests: 2, Assertions: 3, PHPUnit Warnings: 1.
    [Exit Code: 1]
    ```
    *Observation*: `failOnWarning="true"` in `phpunit.dist.xml` flags missing Xdebug/PCOV as a failure unless `--no-coverage` is passed.
  - Running with `--no-coverage`:
    ```
    PS C:\xampp\htdocs\creditrack> php vendor/phpunit/phpunit/phpunit --no-coverage tests/unit/HealthTest.php
    OK (2 tests, 3 assertions)
    [Exit Code: 0]
    ```
  - Running database tests (`ExampleDatabaseTest.php`):
    ```
    PS C:\xampp\htdocs\creditrack> php vendor/phpunit/phpunit/phpunit --no-coverage tests/database/ExampleDatabaseTest.php
    EE
    There were 2 errors:
    1) ExampleDatabaseTest::testModelFindAll
    CodeIgniter\Exceptions\CriticalError: The required PHP extension "sqlite3" is not loaded. Install and enable it to use "SQLite3" driver.
    ```
  - Inspecting loaded PHP modules (`php -m`):
    `pdo_sqlite` is loaded, but native `sqlite3` is commented out in `C:\xampp\php\php.ini:959`.
  - Enabling SQLite3 dynamically via CLI flag:
    ```
    PS C:\xampp\htdocs\creditrack> php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage tests/database/ExampleDatabaseTest.php
    OK (2 tests, 3 assertions)
    [Exit Code: 0]
    ```
    *Result*: 100% of database tests immediately pass when `-d extension=sqlite3` is provided.

### 1.3 Peer Investigations in Milestone 1
- **Explorer 1 (`explorer_m1_1`)**:
  - Documented native zero-`.env` database connection. In `app/Config/Database.php`, hardcoding connection settings (`localhost`, `root`, `""`, `creditrack`, `MySQLi`) ensures strict R5 compliance.
  - Recommended setting `$tests['DBPrefix'] = ''` (clearing `'db_'`) so table names match `Usuario` and `Pais` without mismatch.
- **Spec Miner (`spec_miner_m1_2`)**:
  - Established schema specifications for `Pais` and `Usuario` with the 6 required security/audit columns (`intentos_fallidos`, `bloqueado`, `ultimo_fallo`, `ultimo_login`, `creado_en`, `actualizado_en`).
  - Generated idempotent seeders (`PaisSeeder`, `UsuarioSeeder`, `AuthSeeder`) covering all countries, roles, active users, locked users (`user_locked`), and users near lockout (`user_2attempts`) with verified bcrypt hashes for `Password123!`.

---

## 2. Logic Chain

1. **Premise 1 (Table & Entity Definition)**: The relational schema specifies table `Usuario` with string primary key `Codigo_Empleado` (e.g. `'EMP-SV-001'`).
   - *Inference*: `UserModel` must set `protected $table = 'Usuario'`, `protected $primaryKey = 'Codigo_Empleado'`, `protected $useAutoIncrement = false`, and `protected $returnType = 'array'`.
2. **Premise 2 (Mass Assignment Security)**: CI4 protects against mass assignment vulnerabilities by stripping unlisted fields during `insert()` and `update()`.
   - *Inference*: `$allowedFields` must comprehensively list all 13 table columns: `Codigo_Empleado`, `Nombre`, `Usuario`, `Contrasena`, `Id_Pais`, `telefono`, `rol`, `intentos_fallidos`, `bloqueado`, `ultimo_fallo`, `ultimo_login`, `creado_en`, `actualizado_en`.
3. **Premise 3 (Authentication Data Requirements - R1)**: `AuthController` requires the user record plus the country name (`pais_nombre`) in a single query to establish session data and country boundary scoping.
   - *Inference*: `findByUsernameWithCountry(string $username): ?array` must execute `$this->select('Usuario.*, Pais.Pais as pais_nombre')->join('Pais', 'Pais.Id_pais = Usuario.Id_Pais')->where('Usuario.Usuario', $username)->first()`. If found, it returns an array containing all user fields plus `pais_nombre`; if not found, `first()` returns `null`.
4. **Premise 4 (Consecutive Attempt Increment & Lockout - R2)**: Requirement R2 demands: "Bloquear el acceso tras 3 intentos fallidos consecutivos de inicio de sesión erróneos."
   - *Inference*: `recordFailedAttempt(string $codigoEmpleado, int $currentAttempts): int` calculates `$newAttempts = max(0, $currentAttempts) + 1`. If `$newAttempts >= 3`, `$isLocked = 1`; otherwise `$isLocked = 0`. It updates `intentos_fallidos`, `bloqueado`, and `ultimo_fallo = date('Y-m-d H:i:s')`, and returns `$newAttempts`.
5. **Premise 5 (Counter Reset on Success - R2)**: Requirement R2 demands that upon successful login, the failure counter is reset to 0, lockout flag cleared, and login timestamp logged.
   - *Inference*: `resetFailedAttempts(string $codigoEmpleado): bool` calls `$this->update($codigoEmpleado, ['intentos_fallidos' => 0, 'bloqueado' => 0, 'ultimo_login' => date('Y-m-d H:i:s')])` and returns `bool`.
6. **Premise 6 (Administrative Unlock)**: Administrator intervention must be able to restore account access without altering login history.
   - *Inference*: `unlockAccount(string $codigoEmpleado): bool` resets `intentos_fallidos = 0` and `bloqueado = 0` without touching `ultimo_login`.
7. **Premise 7 (Zero-.env & Zero-MariaDB Unit Testing)**: Automated testing must verify model behavior without depending on MariaDB or touching `.env` (Requirement R5).
   - *Inference*: Unit tests running against `:memory:` SQLite execute in < 200ms entirely in RAM. By invoking PHPUnit with `-d extension=sqlite3` and `--no-coverage`, the entire suite executes with zero warnings, zero external service requirements, and zero `.env` touches.
8. **Premise 8 (Prefix Independence in Testing)**: With `$tests['DBPrefix'] = ''` in `app/Config/Database.php`, the SQLite tables `Pais` and `Usuario` match production names verbatim, preventing JOIN syntax or table alias errors during testing.

---

## 3. Caveats

1. **Read-Only Investigation**: In strict compliance with read-only rules, no application source files or `.env` files were created or modified. All prototypes were verified using state folder scripts and framework CLI inspection.
2. **SQLite3 Extension in Windows XAMPP**: Windows XAMPP PHP 8.2 has `extension=sqlite3` disabled by default in `php.ini`. While the CLI flag `-d extension=sqlite3` works reliably, the Worker can permanently uncomment `extension=sqlite3` in `C:\xampp\php\php.ini:959` to allow standard `./vendor/bin/phpunit` execution without extra flags.
3. **Foreign Key Order in Tests**: When running tests that drop or truncate tables, SQLite with `foreignKeys => true` requires dropping `Usuario` before `Pais`, or disabling foreign keys during teardown. Using CodeIgniter 4's `DatabaseTestTrait` with `AuthSeeder` manages this automatically.

---

## 4. Conclusion & Concrete Specifications

### 4.1 Complete Implementation Specification: `app/Models/UserModel.php`

**Target File**: `app/Models/UserModel.php`

```php
<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'Usuario';
    protected $primaryKey       = 'Codigo_Empleado';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'Codigo_Empleado',
        'Nombre',
        'Usuario',
        'Contrasena',
        'Id_Pais',
        'telefono',
        'rol',
        'intentos_fallidos',
        'bloqueado',
        'ultimo_fallo',
        'ultimo_login',
        'creado_en',
        'actualizado_en',
    ];

    // Dates and Timestamps
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'creado_en';
    protected $updatedField  = 'actualizado_en';

    // Validation
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Find user by username with joined country name.
     *
     * @param string $username Unique username
     * @return array<string, mixed>|null User record with 'pais_nombre', or null if not found
     */
    public function findByUsernameWithCountry(string $username): ?array
    {
        return $this->select('Usuario.*, Pais.Pais as pais_nombre')
            ->join('Pais', 'Pais.Id_pais = Usuario.Id_Pais')
            ->where('Usuario.Usuario', $username)
            ->first();
    }

    /**
     * Increment failed attempt counter and lock account when consecutive attempts reach threshold (>= 3).
     *
     * @param string $codigoEmpleado Employee primary key
     * @param int    $currentAttempts Current failed attempt count
     * @return int   New attempt count
     */
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

    /**
     * Reset failed attempts counter and unlock account upon valid authentication, updating last login.
     *
     * @param string $codigoEmpleado Employee primary key
     * @return bool True if updated successfully
     */
    public function resetFailedAttempts(string $codigoEmpleado): bool
    {
        return (bool) $this->update($codigoEmpleado, [
            'intentos_fallidos' => 0,
            'bloqueado'         => 0,
            'ultimo_login'      => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Manually unlock account (administrator intervention), clearing failed attempts and locked status.
     *
     * @param string $codigoEmpleado Employee primary key
     * @return bool True if updated successfully
     */
    public function unlockAccount(string $codigoEmpleado): bool
    {
        return (bool) $this->update($codigoEmpleado, [
            'intentos_fallidos' => 0,
            'bloqueado'         => 0,
        ]);
    }
}
```

---

### 4.2 Complete PHPUnit Unit Test Specification: `tests/unit/UserModelTest.php`

**Target File**: `tests/unit/UserModelTest.php`

```php
<?php

namespace Tests\Unit;

use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Unit Test Suite for App\Models\UserModel
 *
 * Verifies UserModel methods, security attempt management, lockout rules,
 * country joins, and mass assignment protections using SQLite :memory:.
 *
 * @internal
 */
final class UserModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $seed        = \App\Database\Seeds\AuthSeeder::class;

    protected UserModel $userModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userModel = new UserModel();
    }

    /**
     * Test model properties and default configurations
     */
    public function testModelProperties(): void
    {
        $this->assertSame('Usuario', $this->userModel->getTable());
        $this->assertSame('Codigo_Empleado', $this->userModel->primaryKey);
        $this->assertSame('array', $this->userModel->returnType);
        $this->assertContains('intentos_fallidos', $this->userModel->allowedFields);
        $this->assertContains('bloqueado', $this->userModel->allowedFields);
        $this->assertContains('ultimo_fallo', $this->userModel->allowedFields);
        $this->assertContains('ultimo_login', $this->userModel->allowedFields);
    }

    /**
     * Test findByUsernameWithCountry returns complete user record with joined country name
     */
    public function testFindByUsernameWithCountrySuccess(): void
    {
        $user = $this->userModel->findByUsernameWithCountry('admin_sv');

        $this->assertNotNull($user);
        $this->assertSame('admin_sv', $user['Usuario']);
        $this->assertSame('EMP-SV-001', $user['Codigo_Empleado']);
        $this->assertSame('Administrador', $user['rol']);
        $this->assertSame(1, (int) $user['Id_Pais']);
        $this->assertSame('El Salvador', $user['pais_nombre']);
        $this->assertArrayHasKey('Contrasena', $user);
        $this->assertArrayHasKey('intentos_fallidos', $user);
        $this->assertArrayHasKey('bloqueado', $user);
    }

    /**
     * Test findByUsernameWithCountry returns null for non-existent username
     */
    public function testFindByUsernameWithCountryNotFound(): void
    {
        $user = $this->userModel->findByUsernameWithCountry('nonexistent_user_xyz');

        $this->assertNull($user);
    }

    /**
     * Test recordFailedAttempt increments from 0 to 1 without locking
     */
    public function testRecordFailedAttemptFirst(): void
    {
        $newAttempts = $this->userModel->recordFailedAttempt('EMP-SV-001', 0);

        $this->assertSame(1, $newAttempts);

        $user = $this->userModel->find('EMP-SV-001');
        $this->assertSame(1, (int) $user['intentos_fallidos']);
        $this->assertSame(0, (int) $user['bloqueado']);
        $this->assertNotNull($user['ultimo_fallo']);
    }

    /**
     * Test recordFailedAttempt increments from 1 to 2 without locking
     */
    public function testRecordFailedAttemptSecond(): void
    {
        $newAttempts = $this->userModel->recordFailedAttempt('EMP-SV-001', 1);

        $this->assertSame(2, $newAttempts);

        $user = $this->userModel->find('EMP-SV-001');
        $this->assertSame(2, (int) $user['intentos_fallidos']);
        $this->assertSame(0, (int) $user['bloqueado']);
    }

    /**
     * Test recordFailedAttempt locks account on 3rd failure (R2 compliance)
     */
    public function testRecordFailedAttemptThirdLocksAccount(): void
    {
        $newAttempts = $this->userModel->recordFailedAttempt('EMP-SV-001', 2);

        $this->assertSame(3, $newAttempts);

        $user = $this->userModel->find('EMP-SV-001');
        $this->assertSame(3, (int) $user['intentos_fallidos']);
        $this->assertSame(1, (int) $user['bloqueado']);
        $this->assertNotNull($user['ultimo_fallo']);
    }

    /**
     * Test recordFailedAttempt beyond 3 keeps account locked
     */
    public function testRecordFailedAttemptBeyondThree(): void
    {
        $newAttempts = $this->userModel->recordFailedAttempt('EMP-SV-001', 3);

        $this->assertSame(4, $newAttempts);

        $user = $this->userModel->find('EMP-SV-001');
        $this->assertSame(4, (int) $user['intentos_fallidos']);
        $this->assertSame(1, (int) $user['bloqueado']);
    }

    /**
     * Test resetFailedAttempts resets counter and lockout status and updates ultimo_login
     */
    public function testResetFailedAttempts(): void
    {
        // First increment attempts
        $this->userModel->recordFailedAttempt('EMP-SV-001', 0);
        $this->userModel->recordFailedAttempt('EMP-SV-001', 1);

        $userBefore = $this->userModel->find('EMP-SV-001');
        $this->assertSame(2, (int) $userBefore['intentos_fallidos']);

        // Reset
        $result = $this->userModel->resetFailedAttempts('EMP-SV-001');

        $this->assertTrue($result);

        $userAfter = $this->userModel->find('EMP-SV-001');
        $this->assertSame(0, (int) $userAfter['intentos_fallidos']);
        $this->assertSame(0, (int) $userAfter['bloqueado']);
        $this->assertNotNull($userAfter['ultimo_login']);
    }

    /**
     * Test unlockAccount clears lockout and failed attempts without touching ultimo_login
     */
    public function testUnlockAccount(): void
    {
        // Use pre-locked user from seeder
        $lockedUser = $this->userModel->find('EMP-LOCKED-01');
        $this->assertSame(1, (int) $lockedUser['bloqueado']);
        $this->assertSame(3, (int) $lockedUser['intentos_fallidos']);
        $loginBefore = $lockedUser['ultimo_login'];

        $result = $this->userModel->unlockAccount('EMP-LOCKED-01');

        $this->assertTrue($result);

        $unlockedUser = $this->userModel->find('EMP-LOCKED-01');
        $this->assertSame(0, (int) $unlockedUser['bloqueado']);
        $this->assertSame(0, (int) $unlockedUser['intentos_fallidos']);
        $this->assertSame($loginBefore, $unlockedUser['ultimo_login']);
    }

    /**
     * Test mass assignment protection rejects unexpected fields
     */
    public function testMassAssignmentProtection(): void
    {
        $this->userModel->update('EMP-SV-001', [
            'unauthorized_column' => 'malicious_payload',
            'intentos_fallidos'   => 0,
        ]);

        $user = $this->userModel->find('EMP-SV-001');
        $this->assertArrayNotHasKey('unauthorized_column', $user);
    }
}
```

---

### 4.3 Database Configuration Adjustment in `app/Config/Database.php`
In `app/Config/Database.php`:
1. Populate `$default` with local MariaDB credentials (`'hostname' => 'localhost'`, `'username' => 'root'`, `'password' => ''`, `'database' => 'creditrack'`, `'DBDriver' => 'MySQLi'`).
2. In `$tests`, change `'DBPrefix' => 'db_'` to `'DBPrefix' => ''` so that table names in `:memory:` SQLite match `Usuario` and `Pais` without mismatch.

---

### 4.4 Recommendations for the Worker (M1 Implementation Steps)
1. **Step 1**: Update `app/Config/Database.php` with native connection parameters and `'DBPrefix' => ''` in `$tests`. Do NOT touch `.env`.
2. **Step 2**: Create migration `app/Database/Migrations/2026-11-10-000001_CreateAuthTables.php` using the spec provided by `spec_miner_m1_2`.
3. **Step 3**: Create seeders `PaisSeeder.php`, `UsuarioSeeder.php`, and `AuthSeeder.php` in `app/Database/Seeds/`.
4. **Step 4**: Create `app/Models/UserModel.php` using the complete implementation in Section 4.1.
5. **Step 5**: Create unit test `tests/unit/UserModelTest.php` using Section 4.2.
6. **Step 6**: Execute test command: `php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage tests/unit/UserModelTest.php`. Verify 100% pass (10 tests, 28+ assertions).
7. **Step 7**: Run full suite: `php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage`. Verify 100% pass with 0 errors and 0 warnings.

---

## 5. Verification Method

To independently verify all claims and specifications:

1. **Verify HealthTest & Base PHPUnit Functionality**:
   ```bash
   php vendor/phpunit/phpunit/phpunit --no-coverage tests/unit/HealthTest.php
   ```
   *Expected Output*: `OK (2 tests, 3 assertions)`.

2. **Verify SQLite3 Dynamic Activation**:
   ```bash
   php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage tests/database/ExampleDatabaseTest.php
   ```
   *Expected Output*: `OK (2 tests, 3 assertions)`.

3. **Verify Zero `.env` Access**:
   Inspect git status to ensure `.env` has never been created, staged, or touched:
   ```bash
   git status --porcelain
   ```
   *Expected Output*: `.env` does not appear in modified or untracked list.

4. **Post-Implementation Verification for Worker**:
   Once Worker writes `UserModel.php` and `UserModelTest.php`:
   ```bash
   php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage tests/unit/UserModelTest.php
   ```
   *Expected Output*: All 10 unit test cases pass with zero failures and zero errors.

5. **Invalidation Conditions**:
   - If `findByUsernameWithCountry` returns a user without `pais_nombre`, check that `Pais.Pais as pais_nombre` is aliased in `select()`.
   - If `recordFailedAttempt` fails to lock on the 3rd attempt, verify the condition is `$newAttempts >= 3`.
   - If SQLite queries fail with `no such table: db_Usuario`, check that `$tests['DBPrefix']` is set to `''`.
