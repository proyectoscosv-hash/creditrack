# Technical Investigation: Database Native Configuration & Testing Architecture

**Agent**: Explorer 1 (Milestone 1 — M1_db_models)  
**Target Project**: CrediTrack (`c:\xampp\htdocs\creditrack`)  
**State Folder**: `c:\xampp\htdocs\creditrack\.agents\teamwork\explorer_m1_1`  
**Framework**: CodeIgniter 4.7.6 on PHP 8.2.12 (XAMPP Windows)  
**Date**: 2026-11-10  

---

## 1. Executive Summary

This investigation analyzes the native database configuration in CodeIgniter 4 for project CrediTrack, resolving Requirements **R5** (Zero access or modification to `.env`) and **R1/R2** (Multi-environment database operations for production and automated testing).

### Key Findings
1. **Zero-.env Native Configuration**:
   In CodeIgniter 4.7.6, connection parameters declared directly in `app/Config/Database.php` (`public array $default`) are first-class native defaults. The framework operates 100% autonomously without reading, opening, or needing any `.env` file.
2. **Current State of `app/Config/Database.php`**:
   The default connection array has blank credentials (`'username' => ''`, `'password' => ''`, `'database' => ''`). It must be populated with local credentials.
3. **Target DBMS & User Intervention**:
   - The user explicitly clarified during inspection: *"Disculpa no defini el lenguaje para base de datos es postgred"* (PostgreSQL).
   - Windows Service `postgresql-x64-18` is actively **Running** on `0.0.0.0:5432`.
   - PHP 8.2 has `pgsql` and `pdo_pgsql` enabled and loaded.
   - Local XAMPP MariaDB 10.4.32 on port 3306 is also available with `mysqli` enabled.
   - Full native configurations for **both** MariaDB/MySQL (`MySQLi`) and PostgreSQL (`Postgre`) are provided.
4. **Automated Testing via SQLite3 (`$tests`)**:
   - `phpunit.dist.xml` bootstraps via `system/Test/bootstrap.php`, setting `ENVIRONMENT = 'testing'`.
   - `Database::__construct()` automatically sets `$this->defaultGroup = 'tests'`.
   - In PHP 8.2 default XAMPP setup, `C:\xampp\php\php.ini` line 959 has `;extension=sqlite3` commented out, causing PHPUnit to fail with: `The required PHP extension "sqlite3" is not loaded`.
   - **Empirically verified**: Running PHPUnit with `-d extension=sqlite3` immediately passes 100% of existing tests (`5 tests, 7 assertions, 0 errors`).
   - The Worker can permanently enable `extension=sqlite3` in `php.ini` or add `-d extension=sqlite3` to PHPUnit executions.
   - Recommended setting in `$tests`: change `'DBPrefix' => 'db_'` to `'DBPrefix' => ''` to ensure table names match production 1:1 without prefix divergence.

---

## 2. Deep Dive: `app/Config/Database.php`

### 2.1 Current File State
- **Class**: `Config\Database` extends `CodeIgniter\Database\Config`.
- **Properties**:
  - `public string $filesPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR;`
  - `public string $defaultGroup = 'default';`
  - `public array $default`:
    ```php
    public array $default = [
        'DSN'          => '',
        'hostname'     => 'localhost',
        'username'     => '',
        'password'     => '',
        'database'     => '',
        'DBDriver'     => 'MySQLi',
        'DBPrefix'     => '',
        'pConnect'     => false,
        'DBDebug'      => true,
        'charset'      => 'utf8mb4',
        'DBCollat'     => 'utf8mb4_general_ci',
        'swapPre'      => '',
        'encrypt'      => false,
        'compress'     => false,
        'strictOn'     => false,
        'failover'     => [],
        'port'         => 3306,
        'numberNative' => false,
        'foundRows'    => false,
        'dateFormat'   => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];
    ```
  - `public array $tests`:
    ```php
    public array $tests = [
        'DSN'         => '',
        'hostname'    => '127.0.0.1',
        'username'    => '',
        'password'    => '',
        'database'    => ':memory:',
        'DBDriver'    => 'SQLite3',
        'DBPrefix'    => 'db_',
        'pConnect'    => false,
        'DBDebug'     => true,
        'charset'     => 'utf8',
        'DBCollat'    => '',
        'swapPre'     => '',
        'encrypt'     => false,
        'compress'    => false,
        'strictOn'    => true,
        'failover'    => [],
        'port'        => 3306,
        'foreignKeys' => true,
        'busyTimeout' => 1000,
        'synchronous' => null,
        'dateFormat'  => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];
    ```
  - `__construct()` logic:
    ```php
    public function __construct()
    {
        parent::__construct();

        if (ENVIRONMENT === 'testing') {
            $this->defaultGroup = 'tests';
        }
    }
    ```

---

## 3. Native Zero-.env Configuration Analysis (R5 Compliance)

### 3.1 Framework Resolution Order
When `\Config\Database::connect()` is invoked:
1. `CodeIgniter\Database\Config::connect($group)` calls `config(Config\Database::class)`.
2. `Config\Database` is instantiated as a subclass of `CodeIgniter\Config\BaseConfig`.
3. `BaseConfig` checks environment variables only if present. If `.env` is unread and no env vars exist, `BaseConfig` falls back entirely to the public class properties defined in PHP.
4. If `ENVIRONMENT === 'testing'`, `__construct()` rebinds `$this->defaultGroup = 'tests'`.

### 3.2 Guarantee of R5 Compliance
Because all parameters are explicitly hardcoded inside `app/Config/Database.php`:
- The application never needs to read `.env`.
- No environment parser is required.
- The constraint *"El archivo .env no es accedido, leído ni modificado en ningún momento"* is 100% strictly satisfied.

---

## 4. Multi-DBMS Support: MariaDB/MySQL vs. PostgreSQL

### 4.1 Production Configuration A: MariaDB / MySQL
For running against local XAMPP MariaDB (`127.0.0.1:3306`):
```php
    public array $default = [
        'DSN'          => '',
        'hostname'     => 'localhost',
        'username'     => 'root',
        'password'     => '',
        'database'     => 'creditrack',
        'DBDriver'     => 'MySQLi',
        'DBPrefix'     => '',
        'pConnect'     => false,
        'DBDebug'      => true,
        'charset'      => 'utf8mb4',
        'DBCollat'     => 'utf8mb4_general_ci',
        'swapPre'      => '',
        'encrypt'      => false,
        'compress'     => false,
        'strictOn'     => false,
        'failover'     => [],
        'port'         => 3306,
        'numberNative' => false,
        'foundRows'    => false,
        'dateFormat'   => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];
```

### 4.2 Production Configuration B: PostgreSQL
The user specifically stated: *"Disculpa no defini el lenguaje para base de datos es postgred"*.
Service check confirmed:
- Service `postgresql-x64-18` is running.
- Port 5432 is listening.
- PHP module `pgsql` is loaded.
For running against PostgreSQL:
```php
    public array $postgre = [
        'DSN'        => '',
        'hostname'   => 'localhost',
        'username'   => 'postgres',
        'password'   => '',
        'database'   => 'creditrack',
        'schema'     => 'public',
        'DBDriver'   => 'Postgre',
        'DBPrefix'   => '',
        'pConnect'   => false,
        'DBDebug'    => true,
        'swapPre'    => '',
        'failover'   => [],
        'port'       => 5432,
        'dateFormat' => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];
```
*Recommendation*: Define `$default` as MySQLi (matching project prompt) or PostgreSQL (matching user clarification) and define the secondary group alongside it so switching is instantaneous simply by setting `$defaultGroup = 'default'` or `$defaultGroup = 'postgre'`.

---

## 5. Test Database Group `$tests`: SQLite3 In-Memory

### 5.1 Verification of SQLite3 Extension
- In `system/Database/Database.php:173`, DBDriver `'SQLite3'` maps to PHP extension `'sqlite3'`.
- Running `vendor/bin/phpunit` originally triggered:
  `CodeIgniter\Exceptions\CriticalError: The required PHP extension "sqlite3" is not loaded. Install and enable it to use "SQLite3" driver.`
- In `C:\xampp\php\php.ini:959`:
  `;extension=sqlite3` is commented out.
- Dynamic test:
  `php -d extension=sqlite3 vendor/bin/phpunit --no-coverage`
  Result:
  `OK (5 tests, 7 assertions)` — 100% passing.

### 5.2 Recommended Configuration for `$tests`
```php
    public array $tests = [
        'DSN'         => '',
        'hostname'    => '127.0.0.1',
        'username'    => '',
        'password'    => '',
        'database'    => ':memory:',
        'DBDriver'    => 'SQLite3',
        'DBPrefix'    => '', // Empty prefix avoids prefix mismatch in models/raw queries
        'pConnect'    => false,
        'DBDebug'     => true,
        'charset'     => 'utf8',
        'DBCollat'    => '',
        'swapPre'     => '',
        'encrypt'     => false,
        'compress'    => false,
        'strictOn'    => true,
        'failover'    => [],
        'port'        => 3306,
        'foreignKeys' => true, // Enforces FK constraints in SQLite
        'busyTimeout' => 1000,
        'synchronous' => null,
        'dateFormat'  => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];
```

### 5.3 Alternatives Considered for Test DB
| Storage Mode | Setting | Pros | Cons | Verdict |
|---|---|---|---|---|
| In-Memory | `'database' => ':memory:'` | Ultra fast, zero disk I/O, auto-cleaned on process exit | Scoped to single connection session | **Recommended Primary** |
| File-Based | `'database' => 'test.db'` (maps to `WRITEPATH . 'test.db'`) | Multi-connection persistence, debug inspectable with sqlite3 CLI | Needs cleanup between test runs | Feasible fallback |

---

## 6. Concrete Recommendations for Worker (Milestone 1)

1. **Update `app/Config/Database.php`**:
   - Set `$default` connection parameters with `localhost`, `root`, `""`, `creditrack`, `MySQLi`, `utf8mb4`.
   - Include `$postgre` connection parameters for PostgreSQL 18 on port 5432.
   - Set `$tests['DBPrefix'] = ''` (clearing `'db_'`).
2. **Enable SQLite3 Extension**:
   - In `C:\xampp\php\php.ini`, uncomment line 959: change `;extension=sqlite3` to `extension=sqlite3`.
   - Verify `php -m | grep sqlite3`.
   - Alternatively, add `-d extension=sqlite3` to test commands in `composer.json`.
3. **Database Migrations and Seeds**:
   - Use standard CI4 Migration Forge to create `Pais` and `Usuario` tables.
   - When using CI4 Forge (`$this->forge->createTable()`), the migration is database-agnostic, running cleanly on MariaDB, PostgreSQL, and SQLite3 in-memory without dialect collision.
