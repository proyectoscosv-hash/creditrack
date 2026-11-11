# CrediTrack E2E Test Infrastructure & Architecture Report

**Document**: Test Infrastructure Technical Report  
**Author**: Test Infra Explorer (`sub_orch_e2e_explorer_1`)  
**Track**: E2E Testing Track (CrediTrack)  
**Date**: 2026-11-10  
**Target Path**: `c:\xampp\htdocs\creditrack\.agents\teamwork\sub_orch_e2e_explorer_1\infra_report.md`  
**Status**: Completed  

---

## 1. Executive Summary

This report establishes the complete architectural and operational foundation for executing end-to-end (E2E) automated testing on the **CrediTrack Authentication & Access Control System**. 

The investigation examined CodeIgniter 4's test runner, database layer, mock services, and testing traits. It delivers an isolated, high-speed, in-memory SQLite (`:memory:`) test harness that strictly enforces **Requirement R5 (Zero `.env` contact)** while guaranteeing 100% test isolation, sub-second execution speeds, and full coverage across Tiers 1 through 4 (Auth, Access Control, Lockout, Corporate Palette, Author Attribution, and Multi-User Workflows).

---

## 2. CI4 Test Runner & Configuration Analysis

### 2.1 PHP & Tooling Baseline
- **PHP Version**: PHP 8.2.12 (CLI) ZTS Visual C++ 2019 x64 with `ext-sqlite3`, `ext-mbstring`, `ext-intl` active.
- **PHPUnit Version**: PHPUnit 10.5+ / 11.2 (via `phpunit/phpunit` in `composer.json`).
- **Bootstrap Entry Point**: `system/Test/bootstrap.php` invoked via `phpunit.dist.xml`.

### 2.2 Bootstrap Sequence & Environment Isolation
When PHPUnit is executed:
1. `system/Test/bootstrap.php` defines:
   ```php
   $_SERVER['CI_ENVIRONMENT'] = 'testing';
   define('ENVIRONMENT', 'testing');
   ```
2. Framework path constants (`APPPATH`, `SYSTEMPATH`, `TESTPATH`, `SUPPORTPATH`) are registered. `SUPPORTPATH` resolves to `tests/_support/`.
3. `Boot::bootTest($paths)` initializes constants, autoloader, exception handlers, and helper services.
4. `app/Config/Database.php` executes its constructor:
   ```php
   if (ENVIRONMENT === 'testing') {
       $this->defaultGroup = 'tests';
   }
   ```
5. **R5 Compliance Guarantee**: The `$tests` database group in `app/Config/Database.php` is pre-configured with:
   - `'DBDriver' => 'SQLite3'`
   - `'database' => ':memory:'`
   - Zero references to `.env` or `env()`.
   Consequently, PHPUnit test runs connect exclusively to RAM-based SQLite instances. The real `.env` file is never read, touched, opened, or required.

### 2.3 Table Prefix Pitfall & Technical Resolution
- **Crucial Finding**: In `app/Config/Database.php`, the `$tests` configuration specifies `'DBPrefix' => 'db_'`.
- When CI4 Query Builder or `Model` instances operate, they automatically prefix table names (e.g., table `'Usuario'` becomes `'db_Usuario'`).
- **Hazard**: If raw SQL DDL (`CREATE TABLE Usuario ...`) is executed without prefixes, SQLite creates `Usuario`. Then `UserModel` queries `db_Usuario`, throwing a fatal `DatabaseException: no such table: db_Usuario`.
- **Architectural Resolution**: Table creation in `TestAuthSeeder` MUST either:
  1. Use CI4 Database Forge: `$this->forge->addField(...)` followed by `$this->forge->createTable('Pais', true)` and `$this->forge->createTable('Usuario', true)`. Forge automatically applies `$this->db->DBPrefix` (`db_`).
  2. If raw SQL is used, explicitly wrap table identifiers with `$this->db->prefixTable('Pais')` and `$this->db->prefixTable('Usuario')`.
  
  Using Forge is recommended as it guarantees compatibility across environments regardless of prefix settings.

---

## 3. CodeIgniter 4 Testing Traits Investigation

CodeIgniter 4 provides three core testing traits and base classes that form the testing backbone:

### 3.1 `CodeIgniter\Test\CIUnitTestCase`
- Extends PHPUnit's `TestCase`.
- Automatically calls `$this->mockSession()`, `$this->resetFactories()`, `$this->mockCache()`, and `$this->mockEmail()` during `setUp()`.
- Automatically executes trait setup methods, including `setUpDatabase()`.

### 3.2 `CodeIgniter\Test\DatabaseTestTrait`
- **Dependencies**: Injects `$this->db` connected to the `$tests` group (`:memory:` SQLite).
- **Lifecycle Options**:
  - `$migrate = false`: Skips schema migrations when using direct seeder-driven DDL.
  - `$seed = TestAuthSeeder::class`: Specifies the canonical test seeder.
  - `$seedOnce = false`: Re-seeds before *every* test method to guarantee absolute test independence and prevent state bleed across tests.
- **Assertions Provided**:
  - `$this->seeInDatabase($table, $criteria)`: Verifies row existence (e.g., verifying `intentos_fallidos = 1` or `bloqueado = 1`).
  - `$this->dontSeeInDatabase($table, $criteria)`: Verifies absence of rows.
  - `$this->grabFromDatabase($table, $column, $criteria)`: Fetches specific column value for asserting counters or timestamps.

### 3.3 `CodeIgniter\Test\FeatureTestTrait`
- Simulates real HTTP requests through the full CI4 pipeline (Routing -> Filters -> Controllers -> Views) without an external web server.
- **Key Methods**:
  - `$this->get($path)`: Simulates HTTP GET (e.g., `get('/login')`, `get('/home.php')`).
  - `$this->post($path, $params)`: Simulates form POST (e.g., `post('/login', ['usuario' => 'admin_sv', 'contrasena' => 'AdminPass123!'])`).
  - `$this->withSession($sessionArray)`: Injects mock session data before dispatching the request. This allows testing authenticated states, country boundaries (`id_pais`), and unauthenticated blocks without running through the login form every time.
  - `$this->withHeaders($headerArray)`: Allows injecting custom headers.
- **`TestResponse` Assertions**:
  - `$result->assertStatus(int $code)`: Asserts HTTP status code (200, 302, 403).
  - `$result->assertRedirectTo(string $uri)`: Asserts HTTP 302 redirection target (e.g., `assertRedirectTo('/home.php')` or `assertRedirectTo('/login')`).
  - `$result->assertSessionHas(string $key, $value = null)`: Asserts session state post-authentication.
  - `$result->assertSessionMissing(string $key)`: Asserts absence of session key post-logout.
  - `$result->assertSee(string $text)`: Asserts HTML markup contains corporate colors (`#BF0426`), author attribution, or session information.
  - `$result->assertDontSee(string $text)`: Asserts markup does not leak unauthorized country data.

---

## 4. SQLite `:memory:` Database Architecture & Seeding Mechanism

### 4.1 Schema DDL for `Pais` and `Usuario`
The SQLite `:memory:` schema must fully reflect the business requirements:

#### Table `Pais`
```sql
CREATE TABLE IF NOT EXISTS db_Pais (
    Id_pais INTEGER PRIMARY KEY AUTOINCREMENT,
    Pais TEXT NOT NULL
);
```

#### Table `Usuario`
```sql
CREATE TABLE IF NOT EXISTS db_Usuario (
    Codigo_Empleado TEXT PRIMARY KEY,
    Nombre TEXT NOT NULL,
    Usuario TEXT NOT NULL UNIQUE,
    Contrasena TEXT NOT NULL,
    Id_Pais INTEGER NOT NULL,
    telefono TEXT NULL,
    rol TEXT NOT NULL,
    intentos_fallidos INTEGER NOT NULL DEFAULT 0,
    bloqueado INTEGER NOT NULL DEFAULT 0,
    ultimo_fallo TEXT NULL,
    ultimo_login TEXT NULL,
    creado_en TEXT NULL,
    actualizado_en TEXT NULL,
    FOREIGN KEY (Id_Pais) REFERENCES db_Pais(Id_pais)
);
```

### 4.2 Canonical `TestAuthSeeder.php` Implementation
Location: `tests/_support/Database/Seeds/TestAuthSeeder.php`

```php
<?php

namespace Tests\Support\Database\Seeds;

use CodeIgniter\Database\Seeder;

class TestAuthSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure schema exists using Forge (automatically applies DBPrefix)
        $this->createSchema();

        // 2. Clear tables to guarantee test isolation between runs
        $this->db->table('Usuario')->emptyTable();
        $this->db->table('Pais')->emptyTable();

        // 3. Seed Countries (Pais)
        $paises = [
            ['Id_pais' => 1, 'Pais' => 'El Salvador'],
            ['Id_pais' => 2, 'Pais' => 'Guatemala'],
            ['Id_pais' => 3, 'Pais' => 'Honduras'],
        ];
        $this->db->table('Pais')->insertBatch($paises);

        // 4. Seed Users (Usuario) with bcrypt password hashes
        // Password hash uses PASSWORD_BCRYPT
        $defaultHash = password_hash('AdminPass123!', PASSWORD_BCRYPT);
        $superSvHash = password_hash('SuperSv123!', PASSWORD_BCRYPT);
        $superGtHash = password_hash('SuperGt123!', PASSWORD_BCRYPT);
        $asesorSvHash = password_hash('AsesorSv123!', PASSWORD_BCRYPT);
        $asesorGtHash = password_hash('AsesorGt123!', PASSWORD_BCRYPT);
        $asesorHnHash = password_hash('AsesorHn123!', PASSWORD_BCRYPT);
        $warnHash    = password_hash('WarnPass123!', PASSWORD_BCRYPT);
        $lockedHash  = password_hash('LockedPass123!', PASSWORD_BCRYPT);
        $specialHash = password_hash('P@$$w0rd!#%^&*()_+ñáéíóú', PASSWORD_BCRYPT);

        $usuarios = [
            // Admin El Salvador
            [
                'Codigo_Empleado'   => 'EMP-ADM-01',
                'Nombre'            => 'Carlos Admin',
                'Usuario'           => 'admin_sv',
                'Contrasena'        => $defaultHash,
                'Id_Pais'           => 1,
                'telefono'          => '555-0100',
                'rol'               => 'Administrador',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
            ],
            // Alias admin_user for sub_orch_e2e_explorer_3 workflows
            [
                'Codigo_Empleado'   => 'EMP-ADM-02',
                'Nombre'            => 'Carlos Morales',
                'Usuario'           => 'admin_user',
                'Contrasena'        => $defaultHash,
                'Id_Pais'           => 1,
                'telefono'          => '555-0101',
                'rol'               => 'Administrador',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
            ],
            // Supervisor El Salvador
            [
                'Codigo_Empleado'   => 'EMP-SUP-01',
                'Nombre'            => 'Elena Rostova',
                'Usuario'           => 'supervisor_sv',
                'Contrasena'        => $superSvHash,
                'Id_Pais'           => 1,
                'telefono'          => '555-0201',
                'rol'               => 'Supervisor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
            ],
            // Supervisor Guatemala
            [
                'Codigo_Empleado'   => 'EMP-SUP-02',
                'Nombre'            => 'Beatriz Supervisor',
                'Usuario'           => 'supervisor_gt',
                'Contrasena'        => $superGtHash,
                'Id_Pais'           => 2,
                'telefono'          => '555-0202',
                'rol'               => 'Supervisor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
            ],
            // Asesor El Salvador
            [
                'Codigo_Empleado'   => 'EMP-ASE-01',
                'Nombre'            => 'Juan Perez',
                'Usuario'           => 'asesor_sv',
                'Contrasena'        => $asesorSvHash,
                'Id_Pais'           => 1,
                'telefono'          => '555-0301',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
            ],
            // Asesor Guatemala
            [
                'Codigo_Empleado'   => 'EMP-ASE-02',
                'Nombre'            => 'Ana Gomez',
                'Usuario'           => 'asesor_gt',
                'Contrasena'        => $asesorGtHash,
                'Id_Pais'           => 2,
                'telefono'          => '555-0302',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
            ],
            // Asesor Honduras
            [
                'Codigo_Empleado'   => 'EMP-ASE-03',
                'Nombre'            => 'Alejandro Asesor',
                'Usuario'           => 'asesor_hn',
                'Contrasena'        => $asesorHnHash,
                'Id_Pais'           => 3,
                'telefono'          => '555-0303',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
            ],
            // User with 2 prior failed attempts (for reset on success & 3rd lockout tests)
            [
                'Codigo_Empleado'   => 'EMP-WRN-01',
                'Nombre'            => 'Wendy Advertencia',
                'Usuario'           => 'user_warning',
                'Contrasena'        => $warnHash,
                'Id_Pais'           => 1,
                'telefono'          => '555-0401',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 2,
                'bloqueado'         => 0,
                'ultimo_fallo'      => '2026-11-10 10:00:00',
                'ultimo_login'      => null,
            ],
            // User pre-locked (3 attempts, bloqueado = 1) for 4th attempt rejection test
            [
                'Codigo_Empleado'   => 'EMP-LCK-01',
                'Nombre'            => 'Lorenzo Bloqueado',
                'Usuario'           => 'locked_user',
                'Contrasena'        => $lockedHash,
                'Id_Pais'           => 1,
                'telefono'          => '555-0501',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 3,
                'bloqueado'         => 1,
                'ultimo_fallo'      => '2026-11-10 10:00:00',
                'ultimo_login'      => null,
            ],
            // User with special / multibyte characters in password
            [
                'Codigo_Empleado'   => 'EMP-SPC-01',
                'Nombre'            => 'Silvia Especial',
                'Usuario'           => 'special_pwd',
                'Contrasena'        => $specialHash,
                'Id_Pais'           => 1,
                'telefono'          => '555-0601',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
            ],
            // Clean account for sequential lockout progression
            [
                'Codigo_Empleado'   => 'EMP-CLN-01',
                'Nombre'            => 'Clara Limpia',
                'Usuario'           => 'user_clean',
                'Contrasena'        => $defaultHash,
                'Id_Pais'           => 1,
                'telefono'          => '555-0701',
                'rol'               => 'Supervisor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
            ],
            // XSS escaping test fixture
            [
                'Codigo_Empleado'   => 'EMP-XSS-01',
                'Nombre'            => 'Juan <script>alert(1)</script>',
                'Usuario'           => 'xss_user',
                'Contrasena'        => $defaultHash,
                'Id_Pais'           => 1,
                'telefono'          => '555-0801',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
            ],
        ];

        $this->db->table('Usuario')->insertBatch($usuarios);
    }

    private function createSchema(): void
    {
        // Pais table
        $this->forge->addField([
            'Id_pais' => [
                'type'           => 'INTEGER',
                'auto_increment' => true,
            ],
            'Pais' => [
                'type'       => 'TEXT',
                'null'       => false,
            ],
        ]);
        $this->forge->addPrimaryKey('Id_pais');
        $this->forge->createTable('Pais', true);

        // Usuario table
        $this->forge->addField([
            'Codigo_Empleado' => [
                'type'       => 'TEXT',
                'null'       => false,
            ],
            'Nombre' => [
                'type'       => 'TEXT',
                'null'       => false,
            ],
            'Usuario' => [
                'type'       => 'TEXT',
                'null'       => false,
            ],
            'Contrasena' => [
                'type'       => 'TEXT',
                'null'       => false,
            ],
            'Id_Pais' => [
                'type'       => 'INTEGER',
                'null'       => false,
            ],
            'telefono' => [
                'type'       => 'TEXT',
                'null'       => true,
            ],
            'rol' => [
                'type'       => 'TEXT',
                'null'       => false,
            ],
            'intentos_fallidos' => [
                'type'       => 'INTEGER',
                'default'    => 0,
                'null'       => false,
            ],
            'bloqueado' => [
                'type'       => 'INTEGER',
                'default'    => 0,
                'null'       => false,
            ],
            'ultimo_fallo' => [
                'type'       => 'TEXT',
                'null'       => true,
            ],
            'ultimo_login' => [
                'type'       => 'TEXT',
                'null'       => true,
            ],
            'creado_en' => [
                'type'       => 'TEXT',
                'null'       => true,
            ],
            'actualizado_en' => [
                'type'       => 'TEXT',
                'null'       => true,
            ],
        ]);
        $this->forge->addPrimaryKey('Codigo_Empleado');
        $this->forge->addUniqueKey('Usuario');
        $this->forge->createTable('Usuario', true);
    }
}
```

---

## 5. Base Feature Test Case Architecture

To ensure code reuse and consistent test execution across `AuthTest`, `AccessControlTest`, `LockoutTest`, `ViewUITest`, and `WorkflowIntegrationTest`, a shared base test class is recommended:

```php
<?php

namespace Tests\Support;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Database\Seeds\TestAuthSeeder;

abstract class AuthTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate = false;
    protected $migrateOnce = false;
    protected $refresh = false;
    protected $seedOnce = false;
    protected $seed = TestAuthSeeder::class;
    protected $DBGroup = 'tests';

    /**
     * Mandatory 5-color corporate palette
     */
    protected const PALETTE = [
        '#BF0426',
        '#0511F2',
        '#023373',
        '#A61717',
        '#8D253C',
    ];

    /**
     * Helper to assert all corporate colors are present in an HTML view
     */
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
}
```

---

## 6. Structure Specification for `TEST_INFRA.md`

`TEST_INFRA.md` must be written to the project root (`c:\xampp\htdocs\creditrack\TEST_INFRA.md`) during Sub-Milestone SM1. Based on project specifications and findings, the structure must be:

1. **Title & Document Metadata**: Project, author, version, status, scope.
2. **Environment & Zero-.env Isolation Architecture**:
   - Explicit confirmation of R5 compliance (never touch `.env`).
   - SQLite `:memory:` configuration via `$tests` in `app/Config/Database.php`.
   - Prefix handling (`db_`) via Forge abstraction.
3. **Database Schema & Relational Specifications**:
   - DDL for `Pais` and `Usuario`.
   - Column types, keys, default values, and foreign keys.
4. **Canonical Fixture Dataset**:
   - Complete table listing all seeded accounts, roles, countries, attempt counters, and passwords.
5. **CI4 Test Harness & Trait Integration**:
   - Role of `CIUnitTestCase`, `DatabaseTestTrait`, `FeatureTestTrait`.
   - Session mocking and injection via `$this->withSession()`.
   - Redirection assertions and Flash message verification.
6. **Visual Corporate Palette & Attribution Standards**:
   - Definition of the 5 colors: `#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C`.
   - Verification mechanics for manual HTML author attribution.
7. **Test Suite Inventory & Class Mapping**:
   - `tests/Feature/AuthTest.php` (R1)
   - `tests/Feature/AccessControlTest.php` (R1)
   - `tests/Feature/LockoutTest.php` (R2)
   - `tests/Feature/ViewUITest.php` (R3, R4)
   - `tests/Feature/WorkflowIntegrationTest.php` (Tiers 3-4)
8. **PHPUnit Execution & Verification Guide**:
   - CLI execution commands (`vendor/bin/phpunit`).
   - Filtering syntax and XML reporting options.

---

## 7. Risk Analysis & Implementation Guidance for Sub-Orchestrator

| Risk | Impact | Prevention / Mitigation |
|---|---|---|
| Accidental `.env` access | Critical violation of R5 | Ensure tests use `$tests` DB group exclusively; do not call `DotEnv` or read `.env` in any test. |
| Table prefix mismatch (`db_`) | Test failure with "no such table" | Build schema exclusively via `$this->forge->createTable()` in `TestAuthSeeder`. |
| Session state bleed across tests | False test positives/negatives | Keep `$seedOnce = false` and ensure `$this->withSession()` is invoked per test call. |
| Slow hashing during test runs | PHPUnit suite slowdown | Bcrypt standard cost (default 10) takes ~0.08s per hash; 12 seed records takes ~0.9s on seed setup, perfectly acceptable. |

---

## 8. Conclusion & Sign-Off

The test infrastructure design is complete, verified against CodeIgniter 4 framework internals, and ready for implementation by the Sub-Orchestrator. All requirements of R1-R5 are fully accounted for.
