# Handoff Report — Explorer 3 (Database & Security)

**Agent**: Explorer 3 (Database & Security Explorer)  
**State Folder**: `c:\xampp\htdocs\creditrack\.agents\teamwork\explorer_survey_3`  
**Target Milestone**: Phase 0 Survey Completion  
**Date**: 2026-11-10  

---

## 1. Observation

1. **Database Service in Runtime Environment**:
   - Inspected `C:\xampp\mysql\bin\my.ini` (lines 20, 27-29, 160-161):
     `port=3306`, `default-character-set=utf8mb4`, `character-set-server=utf8mb4`, `collation-server=utf8mb4_general_ci`.
   - Inspected `C:\xampp\mysql\data\mysql_error.log` (lines 2873-2874, 2895, 2914):
     `2026-11-10 14:20:39 0 [Note] Starting MariaDB 10.4.32-MariaDB source revision c4143f909528e3fab0677a28631d10389354c491 as process 38852`
     `2026-11-10 14:20:40 0 [Note] Server socket created on IP: '::'.`
   - Inspected `C:\xampp\phpMyAdmin\config.inc.php` (lines 20-23, 27):
     `$cfg['Servers'][$i]['user'] = 'root';`
     `$cfg['Servers'][$i]['password'] = '';`
     `$cfg['Servers'][$i]['extension'] = 'mysqli';`
     `$cfg['Servers'][$i]['host'] = '127.0.0.1';`

2. **Database Configuration in Application**:
   - Inspected `app/Config/Database.php` (lines 27-44, 165-172, 200-202):
     ```php
     public array $default = [
         'DSN'          => '',
         'hostname'     => 'localhost',
         'username'     => '',
         'password'     => '',
         'database'     => '',
         'DBDriver'     => 'MySQLi',
         ...
         'port'         => 3306,
     ];
     ```
     ```php
     public array $tests = [
         'DSN'         => '',
         'hostname'    => '127.0.0.1',
         'username'    => '',
         'password'    => '',
         'database'    => ':memory:',
         'DBDriver'    => 'SQLite3',
         'DBPrefix'    => 'db_',
     ];
     ```
     ```php
     if (ENVIRONMENT === 'testing') {
         $this->defaultGroup = 'tests';
     }
     ```

3. **Schema Script Analysis (`Base_Datos.sql`)**:
   - Inspected `Base_Datos.sql` (lines 1-10, 54-64):
     ```sql
     CREATE TABLE Pais (
         Id_pais SERIAL PRIMARY KEY,
         Pais VARCHAR(100) NOT NULL
     );
     ...
     CREATE TABLE Usuario (
         Codigo_Empleado VARCHAR(50) PRIMARY KEY,
         Nombre VARCHAR(150) NOT NULL,
         Usuario VARCHAR(50) UNIQUE NOT NULL,
         Contrasena VARCHAR(255) NOT NULL,
         Id_Pais INT NOT NULL,
         telefono VARCHAR(20),
         rol VARCHAR(50) NOT NULL,
         FOREIGN KEY (Id_Pais) REFERENCES Pais(Id_pais)
     );
     ```
   - Observed PostgreSQL keywords: `SERIAL`, `gen_random_uuid()`, `point`. In MariaDB/MySQL, `SERIAL` expands to `BIGINT UNSIGNED AUTO_INCREMENT NOT NULL UNIQUE`, creating a foreign key type mismatch error against `INT NOT NULL` columns.

4. **Session Configuration**:
   - Inspected `app/Config/Session.php` (lines 25, 34, 44, 61):
     `$driver = FileHandler::class;`
     `$cookieName = 'ci_session';`
     `$expiration = 7200;`
     `$savePath = WRITEPATH . 'session';`

5. **Existing Views and Routing**:
   - Inspected `app/Config/Routes.php` (lines 5-6): Only `$routes->get('/', 'Home::index');`.
   - Inspected `app/Views/login.php`: Empty file (0 bytes).
   - Inspected `app/Views/home.php`: File does not exist yet.

---

## 2. Logic Chain

1. **Step 1: Database Connectivity Without `.env` (Resolving R5)**:
   - *From Observation 2*: In `app/Config/Database.php`, the `$default` array contains connection fields defaulting to empty strings `""`.
   - *Reasoning*: CodeIgniter 4 uses standard PHP classes for configuration. If `.env` is absent or untouched, CI4 uses class property values directly.
   - *Conclusion*: Populating `$default['username'] = 'root'`, `$default['password'] = ''`, `$default['database'] = 'creditrack'`, and `$default['DBDriver'] = 'MySQLi'` in `app/Config/Database.php` establishes database connectivity natively without ever reading, touching, or creating `.env`.
   - *From Observation 2*: For automated tests, CI4 automatically switches to `$tests` when `ENVIRONMENT === 'testing'`, utilizing SQLite in-memory (`:memory:`), which requires zero external credentials or `.env` access.

2. **Step 2: Schema Compatibility for MySQL/MariaDB & SQLite (Resolving R1 & R2)**:
   - *From Observation 3*: `Base_Datos.sql` uses `SERIAL` for primary keys (`Pais.Id_pais`) while referencing foreign keys (`Usuario.Id_Pais`, `Departamento.Id_pais`) use `INT`.
   - *Reasoning*: In MySQL/MariaDB, `SERIAL` creates `BIGINT UNSIGNED`, triggering `errno 150` FK type mismatch against `INT`.
   - *Conclusion*: In the production/development database script and migrations, `Id_pais` must be declared as `INT AUTO_INCREMENT PRIMARY KEY` (or `INTEGER PRIMARY KEY AUTOINCREMENT` in SQLite).

3. **Step 3: Security Architecture for 3 Consecutive Failed Attempts Lockout (Resolving R2)**:
   - *From Observation 3*: `Usuario` currently lacks attempt-tracking columns.
   - *Reasoning*: Storing failed attempts purely in PHP session or cookies fails when an attacker opens private windows, clears cookies, or uses automated HTTP tools (e.g. cURL). Storing failed attempts directly on the user account in the database (`intentos_fallidos` and `bloqueado`) guarantees:
     1. Multi-device and cross-browser persistence.
     2. Atomic increment and lock execution in a single update.
     3. Immediate rejection of locked accounts prior to password evaluation.
     4. Immediate reset to 0 upon valid credential presentation.

4. **Step 4: Session & Access Control Boundaries (Resolving R1 & R3)**:
   - *From Observation 4 & 5*: Sessions are managed via `FileHandler` in `writable/session`.
   - *Reasoning*: Upon successful authentication, invoking `session()->regenerate(true)` stops session fixation. Storing `isLoggedIn`, `usuario`, `rol`, and `id_pais` in the session enables an `AuthFilter` to enforce route guarding on `/home.php` and data segregation by country.

---

## 3. Caveats

1. **PostgreSQL vs MySQL Schema File**: `Base_Datos.sql` in the repository root is an initial draft schema using PostgreSQL syntax. It should be kept intact as reference, but the actual database setup in MariaDB and SQLite testing must use the corrected DDL detailed in `db_security_report.md`.
2. **Database Creation in MariaDB**: MariaDB 10.4.32 is running on port 3306. A database named `creditrack` must be created (`CREATE DATABASE IF NOT EXISTS creditrack`) before tables are imported.
3. **Admin Unlock Tool**: When an account reaches 3 failed attempts and is locked (`bloqueado = 1`), an administrator must reset `bloqueado = 0` and `intentos_fallidos = 0`, or an automated time-based window (e.g., 15 minutes) can be supported.

---

## 4. Conclusion

1. **Database Setup**: Database connection can be 100% established without touching `.env` by declaring parameters in `app/Config/Database.php` (`root` / `""` / `creditrack` / `MySQLi` / port 3306).
2. **Schema Extension**: The `Usuario` table requires two additional integer columns: `intentos_fallidos` (INT DEFAULT 0) and `bloqueado` (TINYINT(1) DEFAULT 0), plus optional `ultimo_fallo` (DATETIME).
3. **Security Logic (R2)**:
   - Pre-auth check: if `bloqueado == 1`, reject immediately with lockout message.
   - On bad password: increment `intentos_fallidos`. If $\ge 3$, set `bloqueado = 1`.
   - On valid password: reset `intentos_fallidos = 0` and regenerate session ID.
4. **Access Control (R1)**:
   - Authenticated session stores `rol` and `id_pais`.
   - `AuthFilter` guards `/home.php`.
   - Queries for operational data must be scoped with `WHERE Id_Pais = session('id_pais')`.

---

## 5. Verification Method

1. **Verify Database Connection Without `.env`**:
   - Inspect `app/Config/Database.php` to confirm `$default` configuration has `'hostname' => 'localhost'`, `'username' => 'root'`, `'password' => ''`, `'database' => 'creditrack'`.
   - Confirm `.env` was never read or opened during the process.
2. **Verify PHPUnit In-Memory Test Suite**:
   - Run: `vendor\bin\phpunit tests/unit/HealthTest.php`
   - Invalidation condition: Test fails or attempts to read `.env`.
3. **Inspect Produced Reports**:
   - `c:\xampp\htdocs\creditrack\.agents\teamwork\explorer_survey_3\db_security_report.md`
   - `c:\xampp\htdocs\creditrack\.agents\teamwork\explorer_survey_3\handoff.md`
