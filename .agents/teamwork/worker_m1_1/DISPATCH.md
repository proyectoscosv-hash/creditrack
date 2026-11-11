## 2026-11-10T21:24:21Z

You are the Worker for Milestone 1 (M1_db_models) in project CrediTrack.
Working directory: c:\xampp\htdocs\creditrack
State folder: c:\xampp\htdocs\creditrack\.agents\teamwork\worker_m1_1
Your parent conversation ID: 647cfd40-eef0-4a1b-9e18-ddfcff234e9f

MANDATORY FIRST STEP:
Read c:\xampp\htdocs\creditrack\.agents\teamwork\ORIGINAL_REQUEST.md before doing anything else.
Also read:
- c:\xampp\htdocs\creditrack\PROJECT.md
- c:\xampp\htdocs\creditrack\.agents\teamwork\sub_orch_m1\SCOPE.md
- c:\xampp\htdocs\creditrack\.agents\teamwork\explorer_m1_1\handoff.md
- c:\xampp\htdocs\creditrack\.agents\teamwork\spec_miner_m1_2\handoff.md
- c:\xampp\htdocs\creditrack\.agents\teamwork\explorer_m1_3\handoff.md

FILE WRITE OWNERSHIP:
You exclusively own:
- app/Config/Database.php
- app/Models/UserModel.php
- app/Database/Migrations/2026-11-10-000001_CreateAuthTables.php
- app/Database/Seeds/PaisSeeder.php
- app/Database/Seeds/UsuarioSeeder.php
- app/Database/Seeds/AuthSeeder.php
- tests/unit/UserModelTest.php
DO NOT modify any other files (no views, no controllers, no routes).

CRITICAL CONSTRAINTS:
- ABSOLUTE PROHIBITION: DO NOT touch, read, or modify .env under any circumstances!
- MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

YOUR TASKS:
1. Update `app/Config/Database.php`:
   - In `$default`, configure MariaDB connection natively without .env:
     `'hostname' => 'localhost'`, `'username' => 'root'`, `'password' => ''`, `'database' => 'creditrack'`, `'DBDriver' => 'MySQLi'`, `'port' => 3306`, `'charset' => 'utf8mb4'`, `'DBCollat' => 'utf8mb4_general_ci'`.
   - In `$tests`, configure SQLite3 in-memory:
     `'database' => ':memory:'`, `'DBDriver' => 'SQLite3'`, `'DBPrefix' => ''`, `'foreignKeys' => true`. (Set DBPrefix to empty string to ensure exact table name matching).
2. Create Migration `app/Database/Migrations/2026-11-10-000001_CreateAuthTables.php`:
   - Table `Pais`: `Id_pais` (INT, 11, auto_increment, PK), `Pais` (VARCHAR, 100, not null).
   - Table `Usuario`:
     - `Codigo_Empleado` (VARCHAR, 50, PK)
     - `Nombre` (VARCHAR, 150, not null)
     - `Usuario` (VARCHAR, 50, unique, not null)
     - `Contrasena` (VARCHAR, 255, not null)
     - `Id_Pais` (INT, 11, not null, FK referencing `Pais(Id_pais)` CASCADE update, RESTRICT delete)
     - `telefono` (VARCHAR, 20, null)
     - `rol` (VARCHAR, 50, not null)
     - `intentos_fallidos` (INT, 11, default 0, not null)
     - `bloqueado` (TINYINT, 1, default 0, not null)
     - `ultimo_fallo` (DATETIME, null)
     - `ultimo_login` (DATETIME, null)
     - `creado_en` (DATETIME, null)
     - `actualizado_en` (DATETIME, null)
   - `down()` drops `Usuario` first, then `Pais`.
3. Create Seeders in `app/Database/Seeds/`:
   - `PaisSeeder.php`: Seeds El Salvador (1), Guatemala (2), Honduras (3) idempotently.
   - `UsuarioSeeder.php`: Seeds representative test users across El Salvador, Guatemala, Honduras for roles (Administrador, Supervisor, Asesor), plus `user_locked` (3 failed attempts, bloqueado=1) and `user_2attempts` (2 failed attempts, bloqueado=0). Password: 'Password123!' hashed with `password_hash('Password123!', PASSWORD_BCRYPT)`.
   - `AuthSeeder.php`: Calls `PaisSeeder` then `UsuarioSeeder`.
4. Create `app/Models/UserModel.php`:
   - Extends `CodeIgniter\Model`.
   - Primary key: `Codigo_Empleado`, `$useAutoIncrement = false`, `$returnType = 'array'`.
   - Full `$allowedFields` list (all 13 columns).
   - Implement:
     - `findByUsernameWithCountry(string $username): ?array` (selects `Usuario.*, Pais.Pais as pais_nombre`, joins `Pais` on `Pais.Id_pais = Usuario.Id_Pais`, where `Usuario.Usuario = $username`, returns record or null).
     - `recordFailedAttempt(string $codigoEmpleado, int $currentAttempts): int` (increments count, sets `bloqueado = 1` if >= 3, updates `ultimo_fallo = date('Y-m-d H:i:s')`, updates DB, returns new attempt count).
     - `resetFailedAttempts(string $codigoEmpleado): bool` (resets `intentos_fallidos = 0`, `bloqueado = 0`, updates `ultimo_login = date('Y-m-d H:i:s')`, returns bool).
     - `unlockAccount(string $codigoEmpleado): bool` (resets `intentos_fallidos = 0`, `bloqueado = 0`, returns bool).
5. Create Unit Test `tests/unit/UserModelTest.php`:
   - Tests all properties and methods of `UserModel` in SQLite `:memory:` using `DatabaseTestTrait` with `AuthSeeder`.
   - Verifies properties, `findByUsernameWithCountry`, `recordFailedAttempt` (1st, 2nd, 3rd lockout, beyond 3), `resetFailedAttempts`, and `unlockAccount`.
6. Run Verification Commands:
   - Run `php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage tests/unit/UserModelTest.php`
   - Run full test suite: `php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage`
   - Check `git status` to ensure `.env` has NOT been created or modified.
7. Document all commands, file diffs, and verification outputs in your handoff report `c:\xampp\htdocs\creditrack\.agents\teamwork\worker_m1_1\handoff.md`, update progress.md, and message your parent when complete.
