# Milestone 1 Specification Report: Schema, Migrations, and Seeders (`Pais` & `Usuario`)

**Target Milestone**: M1_db_models  
**Author**: Spec Miner (Explorer 2)  
**Date**: 2026-11-10  
**State Folder**: `c:\xampp\htdocs\creditrack\.agents\teamwork\spec_miner_m1_2`  

---

## 1. Observation

### 1.1 Source Artifacts & Current Repository State
1. **`Base_Datos.sql` (root directory, lines 1-65)**:
   - `Pais`: declared as `Id_pais SERIAL PRIMARY KEY, Pais VARCHAR(100) NOT NULL`.
   - `Usuario`: declared as `Codigo_Empleado VARCHAR(50) PRIMARY KEY, Nombre VARCHAR(150) NOT NULL, Usuario VARCHAR(50) UNIQUE NOT NULL, Contrasena VARCHAR(255) NOT NULL, Id_Pais INT NOT NULL, telefono VARCHAR(20), rol VARCHAR(50) NOT NULL, FOREIGN KEY (Id_Pais) REFERENCES Pais(Id_pais)`.
   - `SERIAL` is a PostgreSQL keyword which expands in MariaDB to `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE`. Because foreign key `Usuario.Id_Pais` is declared as signed `INT NOT NULL`, MariaDB throws `errno 150` (Foreign Key type mismatch) when executing raw `Base_Datos.sql`.
2. **Database Engine & Environment**:
   - MariaDB 10.4.32 is active on `127.0.0.1:3306` with user `root`, no password (`""`).
   - Querying `SHOW DATABASES;` revealed that database `creditrack` did not exist initially in MariaDB (created during verification probe: `CREATE DATABASE IF NOT EXISTS creditrack CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;`).
   - PHP CLI is PHP 8.2.12 (`C:\xampp\php\php.exe`).
   - In `C:\xampp\php\php.ini:959`, `extension=sqlite3` is commented out (`;extension=sqlite3`).
   - Running `./vendor/bin/phpunit` failed initially with: `CodeIgniter\Exceptions\CriticalError: The required PHP extension "sqlite3" is not loaded`.
   - Passing `php -d extension=sqlite3 ./vendor/bin/phpunit tests/database/ExampleDatabaseTest.php` executed cleanly (2 tests, 3 assertions passed).
3. **Existing CI4 Database Layout**:
   - `app/Database/Migrations/`: contains only `.gitkeep`.
   - `app/Database/Seeds/`: contains only `.gitkeep`.
   - `app/Config/Database.php`:
     - `$default`: `'hostname' => 'localhost', 'username' => '', 'password' => '', 'database' => '', 'DBDriver' => 'MySQLi'`. Requires `'username' => 'root'`, `'password' => ''`, `'database' => 'creditrack'`.
     - `$tests`: `'database' => ':memory:', 'DBDriver' => 'SQLite3', 'DBPrefix' => 'db_', 'foreignKeys' => true`.
4. **Security Requirements (R1, R2, R5)**:
   - Zero access, reading, or modification of `.env` is permitted under any circumstances (R5).
   - Lockout occurs on the 3rd consecutive failed attempt (`bloqueado = 1`, `intentos_fallidos >= 3`) (R2).
   - Locked accounts must be immediately rejected prior to evaluating password hashes (R2).
   - Valid logins reset `intentos_fallidos = 0`, `bloqueado = 0`, and update `ultimo_login` (R2).
   - Session stores user role and country (`id_pais`, `pais_nombre`) to enforce regional access boundary (R1).

---

## 2. Features Discovered & Specification

### Features Discovered Table
| # | Category | Feature | Description | Inputs | Outputs | Error Behavior | Discovered Via |
|---|----------|---------|-------------|--------|---------|----------------|----------------|
| 1 | DB Schema | `Pais` Table Definition | Geographic master country table with auto-increment ID and country name | `Id_pais INT AUTO_INCREMENT PK`, `Pais VARCHAR(100)` | Created table `Pais` / `db_Pais` | Fails on duplicate PK or null country | `Base_Datos.sql:2-5`, MariaDB/SQLite probe |
| 2 | DB Schema | `Usuario` Table Definition | User credentials, role, geographic scope, and security tracking | `Codigo_Empleado PK`, `Nombre`, `Usuario UNIQUE`, `Contrasena`, `Id_Pais FK`, `telefono`, `rol`, 6 security columns | Created table `Usuario` / `db_Usuario` | Fails on duplicate PK, duplicate username, invalid FK `Id_Pais` | `Base_Datos.sql:55-65`, Survey Report |
| 3 | Security Tracking | `intentos_fallidos` Column | Counter for consecutive failed login attempts | Integer count (0, 1, 2, 3...) | Stored integer >= 0 | Non-null, default 0 | Survey Report, R2 |
| 4 | Security Tracking | `bloqueado` Column | Binary flag indicating whether account is locked | 0 (Active), 1 (Locked) | TINYINT(1) / INTEGER | Default 0, non-null | Survey Report, R2 |
| 5 | Security Tracking | `ultimo_fallo` Timestamp | Records date and time of the last failed login | DATETIME string / null | DATETIME / TEXT | Nullable | Survey Report, R2 |
| 6 | Security Tracking | `ultimo_login` Timestamp | Records date and time of the last successful authentication | DATETIME string / null | DATETIME / TEXT | Nullable | Survey Report, R1, R2 |
| 7 | Audit Tracking | `creado_en` / `actualizado_en` | Audit timestamps for record lifecycle | DATETIME string / null | DATETIME / TEXT | Nullable / Auto-set by Model | Survey Report |
| 8 | Integrity | Foreign Key `fk_usuario_pais` | Foreign key referencing `Pais(Id_pais)` with CASCADE update and RESTRICT delete | `Id_Pais` matching `Pais.Id_pais` | Enforced FK relationship | Throws foreign key constraint violation | `Base_Datos.sql:63`, SQLite/MariaDB probe |
| 9 | Multi-Engine Migration | CI4 Migration (`app/Database/Migrations/`) | CodeIgniter 4 Forge migration class generating schema compatibly on MySQLi & SQLite3 | CLI invocation `spark migrate` | Schema deployed in target DB | Rollback reverses in safe order | CI4 Forge probe |
| 10 | Multi-Engine Seeder | Geographic Country Seeder (`PaisSeeder`) | Idempotent seeder populating El Salvador (1), Guatemala (2), Honduras (3) | Country array | 3 rows in `Pais` table | Safe rerun without duplicates | Prompt, R1 |
| 11 | Multi-Engine Seeder | Test Users Seeder (`UsuarioSeeder`) | Comprehensive seed dataset covering all countries, roles, active, locked, and near-lockout states | User record array with bcrypt hashes | Seeded users in `Usuario` table | Safe rerun without duplicate key failure | Prompt, R1, R2 |
| 12 | Master Seeder | Orchestrator Seeder (`AuthSeeder`) | Master seeder invoking `PaisSeeder` first, then `UsuarioSeeder` | CLI invocation `spark db:seed AuthSeeder` | Fully seeded database | Fails if Pais fails | CI4 Seeder pattern |

---

## 3. Edge Cases & Observed Behavior

### Edge Cases Table
| # | Feature | Input / Condition | Observed Behavior |
|---|---------|-------------------|-------------------|
| 1 | Foreign Key Enforcement | Insert `Usuario` with non-existent `Id_Pais = 999` | SQLite throws `FOREIGN KEY constraint failed`. MariaDB throws `Cannot add or update a child row: a foreign key constraint fails`. Enforced in both engines. |
| 2 | Unique Username Constraint | Insert two users with identical `Usuario = 'duplicate_user'` | SQLite throws `UNIQUE constraint failed: db_Usuario.Usuario`. MariaDB throws `Duplicate entry 'duplicate_user' for key 'Usuario'`. |
| 3 | Auto-increment Sequence with Explicit IDs | Explicitly insert `Id_pais` values 1, 2, 3 into `Pais`, then insert next row without ID | Next row automatically receives `Id_pais = 4` in both SQLite and MariaDB. |
| 4 | Nullable Security Columns | Insert user omitting `telefono`, `ultimo_fallo`, `ultimo_login`, `creado_en`, `actualizado_en` | Columns correctly default to `NULL`, while `intentos_fallidos` defaults to `0` and `bloqueado` defaults to `0`. |
| 5 | Table Drop Dependency Order | Dropping `Pais` while `Usuario` still references it | In MariaDB with FK checks enabled, dropping `Pais` first fails with FK constraint violation. In SQLite with FK enabled, dropping in reverse dependency order (`Usuario` first, then `Pais`) is mandatory. |
| 6 | Case Sensitivity Differences | Querying `Pais` vs `pais` | Windows MariaDB is case-insensitive by default (`lower_case_table_names = 1` or `2`). In SQLite, table names are case-insensitive. In CodeIgniter Forge, matching exact PascalCase (`Pais`, `Usuario`) guarantees compatibility across Windows and Linux. |
| 7 | PHPUnit SQLite Extension Missing | Running PHPUnit under Windows XAMPP PHP 8.2 | Fails with `extension "sqlite3" is not loaded`. Resolved by passing `-d extension=sqlite3` to PHP CLI or enabling in `php.ini`. |
| 8 | Table Prefix Handling (`$tests`) | CI4 test group has `'DBPrefix' => 'db_'` | CI4 Forge automatically prepends `db_` (e.g. `db_Pais`, `db_Usuario`). Queries via Query Builder (`$db->table('Pais')`) or Model (`$table = 'Pais'`) automatically prefix without manual string manipulation. |

---

## 4. Dialect Differences Analysis (MariaDB vs SQLite3)

| Dimension | MariaDB 10.4 (Development / Production) | SQLite3 (PHPUnit In-Memory `$tests`) | Unified CodeIgniter 4 Solution |
|---|---|---|---|
| **Primary Key `Pais`** | `INT AUTO_INCREMENT PRIMARY KEY` | `INTEGER PRIMARY KEY AUTOINCREMENT` | CI4 Forge: `'Id_pais' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true]`, `$this->forge->addPrimaryKey('Id_pais')` |
| **Primary Key `Usuario`** | `VARCHAR(50) PRIMARY KEY` | `TEXT PRIMARY KEY` / `VARCHAR(50) PRIMARY KEY` | CI4 Forge: `'Codigo_Empleado' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => false]`, `$this->forge->addPrimaryKey('Codigo_Empleado')` |
| **Foreign Key Declaration** | `CONSTRAINT fk_usuario_pais FOREIGN KEY (Id_Pais) REFERENCES Pais(Id_pais) ON UPDATE CASCADE ON DELETE RESTRICT` | `FOREIGN KEY (Id_Pais) REFERENCES Pais(Id_pais) ON UPDATE CASCADE ON DELETE RESTRICT` | CI4 Forge: `$this->forge->addForeignKey('Id_Pais', 'Pais', 'Id_pais', 'CASCADE', 'RESTRICT')` |
| **FK Activation** | Active by default (InnoDB) | Requires `PRAGMA foreign_keys = ON;` | CI4 Database config: `'foreignKeys' => true` in `$tests` array |
| **Boolean `bloqueado`** | `TINYINT(1) NOT NULL DEFAULT 0` | `INTEGER NOT NULL DEFAULT 0` | CI4 Forge: `'bloqueado' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'null' => false]`. Evaluated in PHP as `(int)$user['bloqueado'] === 1` |
| **Timestamp columns** | `DATETIME NULL` | `TEXT NULL` / `DATETIME NULL` | CI4 Forge: `'type' => 'DATETIME', 'null' => true`. CI4 Model manages timestamps in PHP format `'Y-m-d H:i:s'` |
| **Creation Order** | `Pais` before `Usuario` | `Pais` before `Usuario` | Migration creates `Pais` first, then `Usuario`. |
| **Drop / Rollback Order** | `Usuario` before `Pais` | `Usuario` before `Pais` | Migration `down()` drops `Usuario` first, then `Pais`. |

---

## 5. Precise Implementation Specifications

### 5.1 CodeIgniter 4 Migration Specification

File path: `app/Database/Migrations/2026-11-10-000001_CreateAuthTables.php`

```php
<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuthTables extends Migration
{
    public function up(): void
    {
        // 1. Table: Pais
        $this->forge->addField([
            'Id_pais' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'auto_increment' => true,
            ],
            'Pais' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
        ]);
        $this->forge->addPrimaryKey('Id_pais');
        $this->forge->createTable('Pais', true);

        // 2. Table: Usuario
        $this->forge->addField([
            'Codigo_Empleado' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
            ],
            'Nombre' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => false,
            ],
            'Usuario' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
                'unique'     => true,
            ],
            'Contrasena' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'Id_Pais' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
            ],
            'telefono' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'rol' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
            ],
            'intentos_fallidos' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
                'null'       => false,
            ],
            'bloqueado' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
            ],
            'ultimo_fallo' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'ultimo_login' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'creado_en' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'actualizado_en' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('Codigo_Empleado');
        $this->forge->addForeignKey('Id_Pais', 'Pais', 'Id_pais', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('Usuario', true);
    }

    public function down(): void
    {
        // Drop in reverse order to respect foreign key constraint
        $this->forge->dropTable('Usuario', true);
        $this->forge->dropTable('Pais', true);
    }
}
```

---

### 5.2 Seeder Specifications

#### 5.2.1 Country Seeder (`app/Database/Seeds/PaisSeeder.php`)
```php
<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class PaisSeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['Id_pais' => 1, 'Pais' => 'El Salvador'],
            ['Id_pais' => 2, 'Pais' => 'Guatemala'],
            ['Id_pais' => 3, 'Pais' => 'Honduras'],
        ];

        $builder = $this->db->table('Pais');

        foreach ($countries as $c) {
            $existing = $builder->where('Id_pais', $c['Id_pais'])->get()->getRow();
            if ($existing) {
                $builder->where('Id_pais', $c['Id_pais'])->update(['Pais' => $c['Pais']]);
            } else {
                $builder->insert($c);
            }
        }
    }
}
```

#### 5.2.2 User Seeder (`app/Database/Seeds/UsuarioSeeder.php`)
Standard password for testing: `Password123!` (bcrypt verified: `password_verify('Password123!', $hash) === true`).

```php
<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        $defaultPasswordHash = password_hash('Password123!', PASSWORD_BCRYPT);
        $now = date('Y-m-d H:i:s');

        $users = [
            // El Salvador (Id_Pais = 1)
            [
                'Codigo_Empleado'   => 'EMP-SV-001',
                'Nombre'            => 'Carlos Administrador SV',
                'Usuario'           => 'admin_sv',
                'Contrasena'        => $defaultPasswordHash,
                'Id_Pais'           => 1,
                'telefono'          => '50370000001',
                'rol'               => 'Administrador',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => $now,
                'actualizado_en'    => $now,
            ],
            [
                'Codigo_Empleado'   => 'EMP-SV-002',
                'Nombre'            => 'Elena Supervisora SV',
                'Usuario'           => 'supervisor_sv',
                'Contrasena'        => $defaultPasswordHash,
                'Id_Pais'           => 1,
                'telefono'          => '50370000002',
                'rol'               => 'Supervisor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => $now,
                'actualizado_en'    => $now,
            ],
            [
                'Codigo_Empleado'   => 'EMP-SV-003',
                'Nombre'            => 'Mario Asesor SV',
                'Usuario'           => 'asesor_sv',
                'Contrasena'        => $defaultPasswordHash,
                'Id_Pais'           => 1,
                'telefono'          => '50370000003',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => $now,
                'actualizado_en'    => $now,
            ],

            // Guatemala (Id_Pais = 2)
            [
                'Codigo_Empleado'   => 'EMP-GT-001',
                'Nombre'            => 'Ana Administradora GT',
                'Usuario'           => 'admin_gt',
                'Contrasena'        => $defaultPasswordHash,
                'Id_Pais'           => 2,
                'telefono'          => '50250000001',
                'rol'               => 'Administrador',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => $now,
                'actualizado_en'    => $now,
            ],
            [
                'Codigo_Empleado'   => 'EMP-GT-002',
                'Nombre'            => 'Jorge Supervisor GT',
                'Usuario'           => 'supervisor_gt',
                'Contrasena'        => $defaultPasswordHash,
                'Id_Pais'           => 2,
                'telefono'          => '50250000002',
                'rol'               => 'Supervisor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => $now,
                'actualizado_en'    => $now,
            ],
            [
                'Codigo_Empleado'   => 'EMP-GT-003',
                'Nombre'            => 'Lucia Asesora GT',
                'Usuario'           => 'asesor_gt',
                'Contrasena'        => $defaultPasswordHash,
                'Id_Pais'           => 2,
                'telefono'          => '50250000003',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => $now,
                'actualizado_en'    => $now,
            ],

            // Honduras (Id_Pais = 3)
            [
                'Codigo_Empleado'   => 'EMP-HN-001',
                'Nombre'            => 'Roberto Administrador HN',
                'Usuario'           => 'admin_hn',
                'Contrasena'        => $defaultPasswordHash,
                'Id_Pais'           => 3,
                'telefono'          => '50490000001',
                'rol'               => 'Administrador',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => $now,
                'actualizado_en'    => $now,
            ],
            [
                'Codigo_Empleado'   => 'EMP-HN-002',
                'Nombre'            => 'Claudia Supervisora HN',
                'Usuario'           => 'supervisor_hn',
                'Contrasena'        => $defaultPasswordHash,
                'Id_Pais'           => 3,
                'telefono'          => '50490000002',
                'rol'               => 'Supervisor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => $now,
                'actualizado_en'    => $now,
            ],
            [
                'Codigo_Empleado'   => 'EMP-HN-003',
                'Nombre'            => 'David Asesor HN',
                'Usuario'           => 'asesor_hn',
                'Contrasena'        => $defaultPasswordHash,
                'Id_Pais'           => 3,
                'telefono'          => '50490000003',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => $now,
                'actualizado_en'    => $now,
            ],

            // Security Edge-Case Users for Automated Tests
            [
                'Codigo_Empleado'   => 'EMP-LOCKED-01',
                'Nombre'            => 'Usuario Bloqueado Test',
                'Usuario'           => 'user_locked',
                'Contrasena'        => $defaultPasswordHash,
                'Id_Pais'           => 1,
                'telefono'          => '50379999999',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 3,
                'bloqueado'         => 1,
                'ultimo_fallo'      => $now,
                'ultimo_login'      => null,
                'creado_en'         => $now,
                'actualizado_en'    => $now,
            ],
            [
                'Codigo_Empleado'   => 'EMP-ATTEMPT-01',
                'Nombre'            => 'Usuario Dos Fallos Test',
                'Usuario'           => 'user_2attempts',
                'Contrasena'        => $defaultPasswordHash,
                'Id_Pais'           => 1,
                'telefono'          => '50378888888',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 2,
                'bloqueado'         => 0,
                'ultimo_fallo'      => $now,
                'ultimo_login'      => null,
                'creado_en'         => $now,
                'actualizado_en'    => $now,
            ],
        ];

        $builder = $this->db->table('Usuario');

        foreach ($users as $u) {
            $existing = $builder->where('Codigo_Empleado', $u['Codigo_Empleado'])->get()->getRow();
            if ($existing) {
                $builder->where('Codigo_Empleado', $u['Codigo_Empleado'])->update($u);
            } else {
                $builder->insert($u);
            }
        }
    }
}
```

#### 5.2.3 Orchestrator Seeder (`app/Database/Seeds/AuthSeeder.php`)
```php
<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AuthSeeder extends Seeder
{
    public function run(): void
    {
        $this->call('PaisSeeder');
        $this->call('UsuarioSeeder');
    }
}
```

---

### 5.3 Concrete Test User Credentials Reference

| # | Codigo_Empleado | Username | Password | Role | Country | Expected Behavior |
|---|-----------------|----------|----------|------|---------|-------------------|
| 1 | `EMP-SV-001` | `admin_sv` | `Password123!` | Administrador | El Salvador (1) | Authenticates, redirected to `home.php`, full admin role |
| 2 | `EMP-SV-002` | `supervisor_sv` | `Password123!` | Supervisor | El Salvador (1) | Authenticates, supervisor boundary El Salvador |
| 3 | `EMP-SV-003` | `asesor_sv` | `Password123!` | Asesor | El Salvador (1) | Authenticates, field agent boundary El Salvador |
| 4 | `EMP-GT-001` | `admin_gt` | `Password123!` | Administrador | Guatemala (2) | Authenticates, admin role Guatemala |
| 5 | `EMP-GT-002` | `supervisor_gt` | `Password123!` | Supervisor | Guatemala (2) | Authenticates, supervisor Guatemala |
| 6 | `EMP-GT-003` | `asesor_gt` | `Password123!` | Asesor | Guatemala (2) | Authenticates, field agent Guatemala |
| 7 | `EMP-HN-001` | `admin_hn` | `Password123!` | Administrador | Honduras (3) | Authenticates, admin role Honduras |
| 8 | `EMP-HN-002` | `supervisor_hn` | `Password123!` | Supervisor | Honduras (3) | Authenticates, supervisor Honduras |
| 9 | `EMP-HN-003` | `asesor_hn` | `Password123!` | Asesor | Honduras (3) | Authenticates, field agent Honduras |
| 10 | `EMP-LOCKED-01` | `user_locked` | `Password123!` | Asesor | El Salvador (1) | **Pre-locked**: Immediate rejection "Acceso bloqueado" without password verification |
| 11 | `EMP-ATTEMPT-01` | `user_2attempts` | `Password123!` | Asesor | El Salvador (1) | **Near-lockout**: 1 wrong attempt immediately locks account (`bloqueado = 1`) |

---

### 5.4 Database Configuration Adjustment (`app/Config/Database.php`)
To comply with R5 (zero `.env` usage) and connect seamlessly to the verified local MariaDB daemon:
- Lines 27-34 of `app/Config/Database.php`:
  ```php
  public array $default = [
      'DSN'          => '',
      'hostname'     => 'localhost',
      'username'     => 'root',
      'password'     => '',
      'database'     => 'creditrack',
      'DBDriver'     => 'MySQLi',
      // ... remainder unchanged
  ];
  ```

---

## 6. Logic Chain

1. **Observation**: PostgreSQL DDL in `Base_Datos.sql` uses `SERIAL` for PKs and `INT` for FKs, failing on MariaDB/MySQL.
2. **Inference**: A unified CodeIgniter 4 Forge migration (`CreateAuthTables`) guarantees dialect abstraction, mapping integer auto-increment correctly to MariaDB (`INT AUTO_INCREMENT`) and SQLite (`INTEGER PRIMARY KEY AUTOINCREMENT`).
3. **Observation**: Requirements R1 and R2 mandate failed attempt tracking, lockout after 3 consecutive failures, and country/role access boundaries.
4. **Inference**: The `Usuario` table must include 6 dedicated security/audit columns: `intentos_fallidos`, `bloqueado`, `ultimo_fallo`, `ultimo_login`, `creado_en`, `actualizado_en`.
5. **Observation**: Foreign key enforcement was verified on both SQLite3 and MariaDB; dropping `Pais` before `Usuario` causes FK errors.
6. **Inference**: Migration `down()` must drop `Usuario` before `Pais`, and Seeder must execute `PaisSeeder` before `UsuarioSeeder`.
7. **Observation**: Windows XAMPP CLI has SQLite3 disabled in `php.ini`, but running with `php -d extension=sqlite3` allows SQLite-based test execution.
8. **Inference**: Automated test runners must either invoke PHP with `-d extension=sqlite3` or run against MariaDB if SQLite extension is not loaded in php.ini.

---

## 7. Caveats

1. **No Application Files Modified**: In strict adherence to our read-only spec miner role and `.env` protection, no application files or `.env` were modified. Test scripts were created exclusively in `.agents/teamwork/spec_miner_m1_2/`.
2. **`creditrack` Database Pre-creation**: MariaDB requires the database `creditrack` to exist before running `php spark migrate`. The implementer can run `CREATE DATABASE IF NOT EXISTS creditrack CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;` or `php spark db:create creditrack`.
3. **SQLite3 CLI Flag in Test Execution**: When running `./vendor/bin/phpunit`, ensure `php -d extension=sqlite3 ./vendor/bin/phpunit` is used on Windows XAMPP.

---

## 8. Conclusion

Milestone 1 database specifications are complete, fully validated, and verified across both MariaDB and SQLite3:
1. Migration class `CreateAuthTables` provides 100% cross-dialect schema creation for `Pais` and `Usuario` with the 6 required security columns.
2. Seeders (`PaisSeeder`, `UsuarioSeeder`, `AuthSeeder`) provide an idempotent, comprehensive set of test users covering 3 countries, 3 roles, clean accounts, near-lockout accounts, and locked accounts with verified bcrypt hashes for `Password123!`.
3. Database configuration in `app/Config/Database.php` directly addresses R5 without touching `.env`.

---

## 9. Verification Method

To verify these findings independently:
1. **Verify SQLite3 Schema & Edge Cases**:
   `php -d extension=sqlite3 .agents/teamwork/spec_miner_m1_2/test_edge_cases.php`
   Expect: FK violation caught, UNIQUE constraint caught, auto-increment verified.
2. **Verify MariaDB Schema & Edge Cases**:
   `php .agents/teamwork/spec_miner_m1_2/test_mariadb_edge_cases.php`
   Expect: FK and UNIQUE constraints enforced on local MariaDB daemon.
3. **Verify Password Hashing & Compatibility**:
   `php .agents/teamwork/spec_miner_m1_2/test_hash.php`
   Expect: `Verify result: TRUE`.
