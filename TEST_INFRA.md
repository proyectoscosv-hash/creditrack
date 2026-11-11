# CrediTrack E2E Test Infrastructure & Architectural Specification

**Document**: Test Infrastructure Technical Specification & Guide  
**Project**: CrediTrack Authentication & Role/Country Access Control System  
**Author**: Test Infrastructure Writer (`sub_orch_e2e_worker_sm1`)  
**Scope**: Sub-Milestone SM1 (E2E Test Infrastructure Setup)  
**Target Path**: `TEST_INFRA.md` (Project Root)  
**Status**: Active / Approved  

---

## 1. Test Philosophy & Architectural Overview

The CrediTrack End-to-End (E2E) Test Suite is designed to deliver rigorous, hermetic, and verifiable validation of all functional, security, and presentation requirements (R1 through R5). 

The test harness operates on the following core principles:
1. **Absolute Environment Isolation (R5 Compliance)**: Zero reading, loading, touching, or requiring the `.env` configuration file.
2. **Deterministic In-Memory Database Execution**: High-speed, transient SQLite3 (`:memory:`) database instances ensuring sub-second execution times and zero filesystem or external database footprint.
3. **Total Test Independence**: Fresh database state and fresh session staging per test method via `$seedOnce = false` and CI4 `DatabaseTestTrait` resets.
4. **Authentic Cryptographic Execution**: Genuine password hashing using PHP's native `PASSWORD_BCRYPT` with high-performance memoization to prevent runtime timeouts.
5. **Full Pipeline Emulation**: End-to-end request lifecycle simulation (Routing -> Filters -> Controllers -> Views) via CodeIgniter 4's `FeatureTestTrait`.

---

## 2. Environment & Zero-.env Isolation Architecture

### 2.1 Enforcing Requirement R5
Requirement R5 strictly prohibits the team from accessing, reading, or modifying `.env`. The test harness enforces this natively through CodeIgniter 4's bootstrap mechanics:
- When PHPUnit executes via `phpunit.dist.xml`, the bootstrap script `system/Test/bootstrap.php` sets:
  ```php
  $_SERVER['CI_ENVIRONMENT'] = 'testing';
  define('ENVIRONMENT', 'testing');
  ```
- CodeIgniter's database configuration (`app/Config/Database.php`) evaluates the environment in its constructor:
  ```php
  if (ENVIRONMENT === 'testing') {
      $this->defaultGroup = 'tests';
  }
  ```
- The `$tests` configuration group is completely isolated:
  - Driver: `SQLite3`
  - Database: `:memory:`
  - Table Prefix: `db_`
  - Hostname, Username, Password: Not required / blank
- The application never invokes `DotEnv` during tests, and no test class opens or checks the `.env` file.

### 2.2 Table Prefix Resolution (`db_`)
In `app/Config/Database.php`, the `$tests` connection specifies `'DBPrefix' => 'db_'`. When CI4 Models or Query Builder instances interact with tables (e.g. `Usuario`), they automatically prefix the table name as `db_Usuario`.
To prevent `DatabaseException: no such table: db_Usuario`, all schema definitions in the test infrastructure utilize **CodeIgniter 4 Database Forge**:
- `$this->forge->createTable('Pais', true)` creates `db_Pais`.
- `$this->forge->createTable('Usuario', true)` creates `db_Usuario`.
Forge transparently handles prefix resolution, ensuring consistency regardless of environment configuration.

---

## 3. Database Schema & Relational Specifications

The test harness initializes the relational schema inside SQLite `:memory:` through `TestAuthSeeder::createSchema()`.

### 3.1 Table: `db_Pais`
Stores territorial jurisdiction entities for country-based access control.

| Column | Type | Constraints | Description |
|---|---|---|---|
| `Id_pais` | INTEGER | PRIMARY KEY, AUTOINCREMENT | Unique country identifier |
| `Pais` | TEXT | NOT NULL | Country name (e.g., 'El Salvador', 'Guatemala') |

### 3.2 Table: `db_Usuario`
Stores user credentials, access privileges, territorial boundaries, and security lockout states.

| Column | Type | Constraints | Description |
|---|---|---|---|
| `Codigo_Empleado` | TEXT | PRIMARY KEY, NOT NULL | Unique corporate employee identifier (e.g., 'EMP-ADM-01') |
| `Nombre` | TEXT | NOT NULL | Full employee name |
| `Usuario` | TEXT | UNIQUE, NOT NULL | Unique login username |
| `Contrasena` | TEXT | NOT NULL | Standard bcrypt password hash |
| `Id_Pais` | INTEGER | NOT NULL | Territorial jurisdiction foreign key (`db_Pais.Id_pais`) |
| `telefono` | TEXT | NULL | Employee contact telephone |
| `rol` | TEXT | NOT NULL | Access control role ('Administrador', 'Supervisor', 'Asesor') |
| `intentos_fallidos` | INTEGER | NOT NULL, DEFAULT 0 | Counter tracking consecutive failed login attempts |
| `bloqueado` | INTEGER | NOT NULL, DEFAULT 0 | Account security lock flag (0 = active, 1 = locked) |
| `ultimo_fallo` | TEXT | NULL | Timestamp of most recent failed attempt |
| `ultimo_login` | TEXT | NULL | Timestamp of most recent successful authentication |
| `creado_en` | TEXT | NULL | Record creation audit timestamp |
| `actualizado_en` | TEXT | NULL | Record update audit timestamp |

---

## 4. Canonical Fixture Dataset (12 Canonical Accounts)

`TestAuthSeeder` provisions 3 countries and 12 canonical user accounts covering all roles, territorial scopes, lockout boundary states, and edge-case inputs:

### 4.1 Countries (`db_Pais`)
- `1`: **El Salvador**
- `2`: **Guatemala**
- `3`: **Honduras**

### 4.2 Canonical Users Table (`db_Usuario`)

| # | Username | Password (Plain) | Role | Country (ID) | Code | Attempts | Locked | Test Objective |
|---|---|---|---|---|---|---|---|---|
| 1 | `admin_sv` | `AdminPass123!` | Administrador | El Salvador (1) | EMP-ADM-01 | 0 | 0 | Primary Admin auth & full management access |
| 2 | `admin_user` | `AdminPass123!` | Administrador | El Salvador (1) | EMP-ADM-02 | 0 | 0 | Cross-track alias for administrative workflows |
| 3 | `supervisor_sv` | `SuperSv123!` | Supervisor | El Salvador (1) | EMP-SUP-01 | 0 | 0 | Supervisor role boundary for El Salvador |
| 4 | `supervisor_gt` | `SuperGt123!` | Supervisor | Guatemala (2) | EMP-SUP-02 | 0 | 0 | Supervisor cross-country boundary testing |
| 5 | `asesor_sv` | `AsesorSv123!` | Asesor | El Salvador (1) | EMP-ASE-01 | 0 | 0 | Asesor role boundary for El Salvador |
| 6 | `asesor_gt` | `AsesorGt123!` | Asesor | Guatemala (2) | EMP-ASE-02 | 0 | 0 | Asesor cross-country boundary for Guatemala |
| 7 | `asesor_hn` | `AsesorHn123!` | Asesor | Honduras (3) | EMP-ASE-03 | 0 | 0 | Asesor cross-country boundary for Honduras |
| 8 | `user_warning` | `WarnPass123!` | Asesor | El Salvador (1) | EMP-WRN-01 | 2 | 0 | Pre-set with 2 failures: tests counter reset on success AND lockout on 3rd failure |
| 9 | `locked_user` | `LockedPass123!` | Asesor | El Salvador (1) | EMP-LCK-01 | 3 | 1 | Pre-locked account: tests immediate 4th attempt rejection even with valid credentials |
| 10 | `special_pwd` | `P@$$w0rd!#%^&*()_+ñáéíóú` | Asesor | El Salvador (1) | EMP-SPC-01 | 0 | 0 | Unicode / multibyte / special character password verification |
| 11 | `user_clean` | `AdminPass123!` | Supervisor | El Salvador (1) | EMP-CLN-01 | 0 | 0 | Clean account for 3-step sequential lockout progression tests |
| 12 | `xss_user` | `AdminPass123!` | Asesor | El Salvador (1) | EMP-XSS-01 | 0 | 0 | Cross-site scripting / output escaping integrity test |

---

## 5. CI4 Test Harness & Trait Integration

### 5.1 Base Test Class: `Tests\Support\AuthTestCase`
Located at `tests/_support/AuthTestCase.php`, this abstract base class standardizes test execution across the entire suite.

```php
abstract class AuthTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = false;
    protected $migrateOnce = false;
    protected $refresh   = false;
    protected $seedOnce  = false;
    protected $seed      = TestAuthSeeder::class;
    protected $DBGroup   = 'tests';
    ...
}
```

### 5.2 Test Isolation Mechanics
- `$seedOnce = false`: CI4 executes `TestAuthSeeder::run()` before every single test method. This guarantees that state changes (e.g. failed login increments, account lockouts) never leak across test cases.
- `$migrate = false`: Relies on Forge schema creation inside the seeder, avoiding external migration overhead.
- Memoized Hashes: `TestAuthSeeder` computes bcrypt hashes once statically, avoiding the 0.8s overhead per test and keeping test execution under 50ms per case.

### 5.3 Simulating Requests & Sessions via `FeatureTestTrait`
`FeatureTestTrait` enables full-stack HTTP testing within PHPUnit:
- **Simulating Login Submissions**:
  ```php
  $result = $this->post('/login', [
      'usuario'    => 'admin_sv',
      'contrasena' => 'AdminPass123!',
  ]);
  $result->assertStatus(302);
  $result->assertRedirectTo('/home.php');
  ```
- **Injecting Authenticated Session States (Bypassing UI Login)**:
  ```php
  $result = $this->withSession([
      'isLoggedIn'  => true,
      'usuario'     => 'asesor_sv',
      'nombre'      => 'Juan Perez',
      'rol'         => 'Asesor',
      'id_pais'     => 1,
      'pais_nombre' => 'El Salvador',
  ])->get('/home.php');

  $result->assertStatus(200);
  $result->assertSee('El Salvador');
  ```
- **Testing Unauthenticated Route Guards**:
  ```php
  $result = $this->get('/home.php');
  $result->assertStatus(302);
  $result->assertRedirectTo('/login');
  ```

---

## 6. Visual Corporate Palette & Attribution Standards (R4)

### 6.1 Corporate 5-Color Palette
Requirement R4 mandates that the UI design incorporate the official 5-color corporate palette:

| Color Name | Hex Code | Purpose / Application |
|---|---|---|
| Crimson Red | `#BF0426` | Primary brand accent / action buttons |
| Electric Blue | `#0511F2` | Secondary accent / active focus states |
| Deep Navy | `#023373` | Header, borders, and structured containers |
| Brick Red | `#A61717` | Warning borders, error badges, alert backgrounds |
| Burgundy | `#8D253C` | Footer accents, dark badges, corporate text |

### 6.2 Automated Palette Assertion
`AuthTestCase` provides the helper method `assertCorporatePalettePresent(string $html)`:
```php
protected function assertCorporatePalettePresent(string $html): void
{
    foreach (self::PALETTE as $color) {
        $this->assertStringContainsStringIgnoringCase(
            $color,
            $html,
            "Corporate palette color {$color} was not found in response HTML."
        );
    }
}
```
This helper checks case-insensitively, ensuring compliance whether colors are declared in lowercase (`#bf0426`) or uppercase (`#BF0426`).

### 6.3 Manual Author Attribution
Requirement R4 specifies that design author information must be manually added to the interface. The test suite verifies that static designer attribution markup is rendered in both the login view (`/login`) and the landing view (`/home.php`).

---

## 7. Test Suite Inventory & Class Mapping

The test suite is organized into modular classes under `tests/Feature/`:

| Test Suite File | Requirement | Coverage Scope |
|---|---|---|
| `tests/Feature/AuthTest.php` | R1, R3 | Valid credentials, invalid credentials, empty field validation, SQL injection resilience, post-auth redirect to `home.php`, session establishment, logout invalidation. |
| `tests/Feature/AccessControlTest.php` | R1, R3 | Role assignment (Administrador, Supervisor, Asesor), country isolation (SV vs GT vs HN), unauthenticated route protection, unauthorized country access blocking. |
| `tests/Feature/LockoutTest.php` | R2 | Attempt counter increment (1st, 2nd attempt), counter reset to 0 upon successful login, account lockout on 3rd consecutive failed attempt, immediate rejection on 4th attempt with valid password. |
| `tests/Feature/ViewUITest.php` | R3, R4 | Corporate 5-color palette presence (`#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C`), manual designer/author attribution in login & home, session status confirmation badge on `home.php`. |
| `tests/Feature/WorkflowIntegrationTest.php` | R1-R4 (Tiers 3-4) | Multi-step end-to-end user workflows: login -> work -> logout -> re-login; lockout recovery; concurrent multi-user state separation across countries. |

---

## 8. PHPUnit Execution & Verification Guide

### 8.1 Test Suite Configuration (`phpunit.dist.xml`)
The `phpunit.dist.xml` registers `defaultTestSuite="Feature"` and excludes `HealthTest.php` to prevent inadvertent `.env` access:
```xml
<phpunit
    ...
    defaultTestSuite="Feature"
>
    <testsuites>
        <testsuite name="App">
            <directory>./tests</directory>
            <exclude>./tests/Feature</exclude>
            <exclude>./tests/unit/HealthTest.php</exclude>
        </testsuite>
        <testsuite name="Feature">
            <directory>./tests/Feature</directory>
        </testsuite>
    </testsuites>
```

### 8.2 Execution Commands

In standard Windows XAMPP environments, the CLI `php.ini` has `pdo_sqlite` enabled but `extension=sqlite3` disabled. CodeIgniter 4's SQLite3 database driver specifically requires `class_exists('SQLite3')`. Therefore, execute PHPUnit with `-d extension=sqlite3` or execute via standard CLI:

- **Run Feature Test Suite (Windows XAMPP CLI - Recommended)**:
  ```powershell
  php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --testsuite Feature
  ```

- **Run Entire Test Suite (Windows XAMPP CLI)**:
  ```powershell
  php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit
  ```

- **Run Specific Test Class (Windows XAMPP CLI)**:
  ```powershell
  php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit tests/Feature/AuthTest.php
  php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit tests/Feature/LockoutTest.php
  php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit tests/Feature/AccessControlTest.php
  php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit tests/Feature/ViewUITest.php
  php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit tests/Feature/WorkflowIntegrationTest.php
  ```

- **Run Filtered Test by Method Name**:
  ```powershell
  php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --filter testAccountLocksOnThirdFailedAttempt
  ```

- **Generate Testdox Readable Report**:
  ```powershell
  php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --testsuite Feature --testdox
  ```

- **Standard Vendor Bin Shorthand** (when `extension=sqlite3` is enabled in `C:\xampp\php\php.ini`):
  ```powershell
  vendor/bin/phpunit --testsuite Feature
  ```

---

## 9. Verification & R5 Compliance Audit

To confirm that tests run in total compliance with Requirement R5:
1. Verify that `app/Config/Database.php` has default group `'tests'` when `ENVIRONMENT === 'testing'`.
2. Verify that `$tests` driver is `SQLite3` with `'database' => ':memory:'`.
3. Verify that `TestAuthSeeder` and `AuthTestCase` do not contain any references to `.env`, `env()`, or `DotEnv`.
4. Verify that running `vendor/bin/phpunit` executes without touching or modifying the `.env` file.
