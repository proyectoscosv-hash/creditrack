# Forensic Audit Report: Milestone 1 (M1_db_models)

**Auditor**: Forensic Auditor M1 (`auditor_m1_1`)  
**Role**: Adversarial Forensic Auditor  
**Milestone**: M1_db_models  
**Target Work Product**: Database configuration, migrations, seeders, UserModel, and unit tests  
**Integrity Mode**: Demo (per `ORIGINAL_REQUEST.md`)  
**Verdict**: **CLEAN**  
**Date**: 2026-11-10  
**Audit Directory**: `c:\xampp\htdocs\creditrack\.agents\teamwork\auditor_m1_1`  

---

## 1. Observation

A forensic, line-by-line inspection was conducted on all 7 artifacts produced or modified in Milestone 1:

### 1.1 `app/Config/Database.php`
- **Lines 27-52**: Native `$default` database configuration explicitly defines:
  ```php
  'hostname' => 'localhost',
  'username' => 'root',
  'password' => '',
  'database' => 'creditrack',
  'DBDriver' => 'MySQLi',
  'charset'  => 'utf8mb4',
  ```
  No calls to `env()`, `getenv()`, `$_ENV`, or any reference to `.env` exist in the active connection array.
- **Lines 165-191**: Native `$tests` database configuration defines:
  ```php
  'hostname'    => '127.0.0.1',
  'database'    => ':memory:',
  'DBDriver'    => 'SQLite3',
  'DBPrefix'    => '',
  'foreignKeys' => true,
  ```
- **Lines 200-202**: Automatically sets `$this->defaultGroup = 'tests'` when `ENVIRONMENT === 'testing'`.
- **Finding**: Zero reliance on `.env`. R5 hard constraint is 100% satisfied.

### 1.2 `app/Models/UserModel.php`
- **Class Architecture**: Extends `CodeIgniter\Model`.
- **Properties (Lines 9-30)**:
  - Table: `Usuario`, primary key: `Codigo_Empleado`, `$useAutoIncrement = false`.
  - `$allowedFields`: Exactly lists all 13 columns matching the database schema (`Codigo_Empleado`, `Nombre`, `Usuario`, `Contrasena`, `Id_Pais`, `telefono`, `rol`, `intentos_fallidos`, `bloqueado`, `ultimo_fallo`, `ultimo_login`, `creado_en`, `actualizado_en`).
- **Method `findByUsernameWithCountry` (Lines 50-56)**:
  ```php
  public function findByUsernameWithCountry(string $username): ?array
  {
      return $this->select('Usuario.*, Pais.Pais as pais_nombre')
          ->join('Pais', 'Pais.Id_pais = Usuario.Id_Pais')
          ->where('Usuario.Usuario', $username)
          ->first();
  }
  ```
  - Directly queries table `Usuario`, performs an inner join against `Pais` on `Pais.Id_pais = Usuario.Id_Pais`, selects all user fields plus `pais_nombre`, filters by `Usuario.Usuario = $username`, and executes `first()`.
  - No hardcoded username shortcuts (e.g. no `if ($username === 'admin_sv')`).
- **Method `recordFailedAttempt` (Lines 65-77)**:
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
  - Calculates `$newAttempts = max(0, $currentAttempts) + 1`.
  - Evaluates `$isLocked = ($newAttempts >= 3) ? 1 : 0`.
  - Directly issues `$this->update($codigoEmpleado, [...])` against the database table `Usuario`.
  - No facade, dummy, or mocked return values.
- **Method `resetFailedAttempts` (Lines 85-92)**:
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
  - Authentically updates database clearing `intentos_fallidos`, `bloqueado`, and setting `ultimo_login`.
- **Method `unlockAccount` (Lines 100-106)**:
  ```php
  public function unlockAccount(string $codigoEmpleado): bool
  {
      return (bool) $this->update($codigoEmpleado, [
          'intentos_fallidos' => 0,
          'bloqueado'         => 0,
      ]);
  }
  ```
  - Authentically updates database clearing `intentos_fallidos` and `bloqueado` without modifying `ultimo_login`.

### 1.3 `app/Database/Migrations/2026-11-10-000001_CreateAuthTables.php`
- **Lines 12-25**: Creates table `Pais` (`Id_pais INT(11) AUTO_INCREMENT PRIMARY KEY`, `Pais VARCHAR(100) NOT NULL`).
- **Lines 28-96**: Creates table `Usuario` with string primary key `Codigo_Empleado`, unique `Usuario`, foreign key `Id_Pais` referencing `Pais(Id_pais)` (`ON UPDATE CASCADE ON DELETE RESTRICT`), and all required security/audit columns (`intentos_fallidos`, `bloqueado`, `ultimo_fallo`, `ultimo_login`, `creado_en`, `actualizado_en`).
- **Lines 99-104**: `down()` drops `Usuario` first, then `Pais`, strictly preserving foreign key referential integrity.

### 1.4 `app/Database/Seeds/`
- `PaisSeeder.php`: Idempotently seeds El Salvador (1), Guatemala (2), Honduras (3) using standard CI4 Query Builder.
- `UsuarioSeeder.php`: Idempotently seeds test accounts across all roles (Administrador, Supervisor, Asesor) for all three countries, plus dedicated security edge cases (`user_locked` with 3 attempts and `bloqueado = 1`, and `user_2attempts` with 2 attempts). All passwords hashed authentically via PHP `password_hash(..., PASSWORD_BCRYPT)`.
- `AuthSeeder.php`: Idempotent master seeder invoking `PaisSeeder` then `UsuarioSeeder`.

### 1.5 `tests/unit/UserModelTest.php`
- Tests run against in-memory SQLite (`$tests` connection in `Database.php`).
- Employs `DatabaseTestTrait` (`$migrate = true`, `$refresh = true`, `$seed = AuthSeeder::class`).
- 10 distinct, non-trivial test methods:
  - `testModelProperties`: validates table, primaryKey, and security allowedFields.
  - `testFindByUsernameWithCountrySuccess`: executes real join query and validates returned data.
  - `testFindByUsernameWithCountryNotFound`: validates null on non-existent records.
  - `testRecordFailedAttemptFirst`: calls `recordFailedAttempt` and queries DB via `find()` to verify persistence.
  - `testRecordFailedAttemptSecond`: validates counter reaches 2 without locking.
  - `testRecordFailedAttemptThirdLocksAccount`: validates counter reaches 3 and locks account (`bloqueado = 1`).
  - `testRecordFailedAttemptBeyondThree`: validates counter increments to 4 and preserves lock.
  - `testResetFailedAttempts`: validates counter and lock reset, and `ultimo_login` timestamp update in DB.
  - `testUnlockAccount`: validates admin unlock on pre-locked user without touching `ultimo_login`.
  - `testMassAssignmentProtection`: verifies unauthorized columns are rejected.

### 1.6 Runtime & System Logs (`writable/logs/log-2026-11-10.log`)
- Log analysis revealed authentic execution traces from database probes:
  - Lines 1-7: `FOREIGN KEY constraint failed` in SQLite when testing non-existent `Id_Pais = 999`.
  - Lines 8-14: `UNIQUE constraint failed: db_Usuario.Usuario` in SQLite when testing duplicate username.
  - Lines 15-21: `Cannot add or update a child row: a foreign key constraint fails` in MariaDB when testing foreign key constraints.
  - Lines 22-28: `Duplicate entry 'duplicate_user' for key 'Usuario'` in MariaDB when testing unique constraint.
- Proves genuine database execution against real MariaDB and SQLite engines.

---

## 2. Logic Chain

1. **Anti-Cheat & Hardcoded Shortcut Detection**:
   - Every method in `app/Models/UserModel.php` operates directly through CodeIgniter's Query Builder and active database connection.
   - There are zero condition shortcuts (no `if ($username === 'admin_sv') return ...`).
   - All tests in `UserModelTest.php` inspect the post-execution database state using `$this->userModel->find()` rather than relying solely on method return values.
2. **Facade & Dummy Implementation Detection**:
   - Methods do not return hardcoded constants or mocked objects.
   - All write operations execute genuine SQL `UPDATE` statements against table `Usuario`.
3. **Plagiarism & Execution Delegation Audit**:
   - Milestone 1 contains pure native CodeIgniter 4 code and standard PHP 8.2 primitives.
   - No third-party authentication packages (e.g., Myth:Auth, Shield) or external scripts were copied or invoked.
4. **Requirement R5 (.env Isolation) Audit**:
   - `ORIGINAL_REQUEST.md` mandates that `.env` must not be accessed, read, or modified.
   - `app/Config/Database.php` has credentials statically defined in PHP array structures.
   - Static analysis confirms zero calls to `env()`, `getenv()`, or `$_ENV` across all M1 deliverables.
   - `.env` was never accessed or modified.

---

## 3. Caveats

- **SQLite3 CLI Extension in Windows XAMPP**:
  Windows XAMPP has `;extension=sqlite3` commented out in `C:\xampp\php\php.ini:959`. Running tests via CLI requires passing `-d extension=sqlite3` (e.g., `php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage tests/unit/UserModelTest.php`).
- **Scope Boundary**:
  Milestone 1 only covers database models, seeders, migrations, and database configuration. HTTP controllers, session management, view templates, and routing filters will be audited in subsequent milestones.

---

## 4. Conclusion

The forensic integrity audit of Milestone 1 (`M1_db_models`) concludes with a binary verdict of **CLEAN**.

All checks passed with empirical evidence:
- **Hardcoded test results**: None detected (PASS).
- **Facade / dummy implementations**: None detected (PASS).
- **Fabricated verification outputs**: None detected (PASS).
- **Self-certifying / tautological tests**: None detected (PASS).
- **Execution delegation / plagiarism**: None detected (PASS).
- **Requirement R5 (.env isolation)**: Fully compliant (PASS).
- **Lockout & attempt management logic (R2)**: Fully compliant (PASS).
- **Country join & model contracts (R1)**: Fully compliant (PASS).

---

## 5. Verification Method

To independently reproduce and verify the findings:

1. **Static Analysis for .env / getenv() / $_ENV**:
   Inspect `app/Config/Database.php`, `app/Models/UserModel.php`, and migrations for absence of environment loading calls.

2. **Model Attempt Logic Verification**:
   Verify lines 65-77 of `app/Models/UserModel.php` to confirm `$newAttempts >= 3` triggers `bloqueado = 1` and executes `$this->update()`.

3. **Independent Unit Test Execution**:
   Run the test suite with SQLite3 loaded:
   ```powershell
   php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage tests/unit/UserModelTest.php
   ```
   *Expected Result*: 10 tests, 28+ assertions pass with 0 failures and 0 errors.

4. **Invalidation Conditions**:
   - Any modification of `.env` invalidates R5.
   - Any bypass of database updates in `UserModel` invalidates R1/R2 integrity.
