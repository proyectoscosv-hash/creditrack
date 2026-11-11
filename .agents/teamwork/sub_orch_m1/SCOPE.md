# Scope: Milestone 1 — M1_db_models

## Architecture & Objective
Milestone 1 establishes the database layer, schema definitions, and UserModel for the CrediTrack application, fully satisfying Requirements R1, R2, and R5.
- Zero dependency or access to `.env` (native database configuration in `app/Config/Database.php`).
- Relational schema and seeders for `Pais` and `Usuario` with security tracking columns (`intentos_fallidos`, `bloqueado`, `ultimo_fallo`, `ultimo_login`, `creado_en`, `actualizado_en`).
- Multi-DBMS support: MariaDB/MySQL for local production runtime, SQLite3 (:memory:) for `$tests` group to support automated testing.
- `app/Models/UserModel.php` implementing authentication query, atomic failed attempt increment, lockout on 3rd failure, counter reset on success, and manual unlock.

## Feature Inventory Scope (Features 1-7 in PROJECT.md)
| # | Feature | Description | Status |
|---|---------|-------------|--------|
| 1 | DB Native Config (No .env) | Configure DB connection directly in app/Config/Database.php (localhost, root, "", creditrack, MySQLi) without .env | DONE |
| 2 | Relational Schema & Seeders | Clean MySQL & SQLite DDL and seeders for Pais and Usuario with security columns | DONE |
| 3 | User Model | UserModel with credential querying, country join, and atomic attempt management | DONE |
| 4 | Consecutive Failure Counter | Increments failure count by 1 for each failed login attempt in DB | DONE |
| 5 | Counter Reset on Success | Resets failed attempt counter to 0 immediately upon valid credentials | DONE |
| 6 | Account Lockout on 3rd Failure | Sets bloqueado = 1 immediately when consecutive failed attempts reach 3 | DONE |
| 7 | Locked Account Rejection | Immediately denies authentication attempts for locked accounts without password check | DONE |

## Interface Contracts
### UserModel ↔ AuthController
- `UserModel::findByUsernameWithCountry(string $username): ?array`
  Returns array with user and country data (`pais_nombre`) or null. Verified.
- `UserModel::recordFailedAttempt(string $codigoEmpleado, int $currentAttempts): int`
  Increments `intentos_fallidos`, sets `bloqueado = 1` if >= 3, updates `ultimo_fallo`, returns new count. Verified.
- `UserModel::resetFailedAttempts(string $codigoEmpleado): bool`
  Resets `intentos_fallidos = 0`, `bloqueado = 0`, updates `ultimo_login`. Verified.
- `UserModel::unlockAccount(string $codigoEmpleado): bool`
  Resets `intentos_fallidos = 0`, `bloqueado = 0`. Verified.

## File Ownership & Delivered Files
- `app/Config/Database.php`: Native MariaDB `$default` and SQLite3 `:memory:` `$tests` with `'DBPrefix' => ''`.
- `app/Models/UserModel.php`: Full UserModel implementation with security methods.
- `app/Database/Migrations/2026-11-10-000001_CreateAuthTables.php`: DDL for `Pais` and `Usuario`.
- `app/Database/Seeds/PaisSeeder.php`: Country seeder for SV, GT, HN.
- `app/Database/Seeds/UsuarioSeeder.php`: User seeder with bcrypt hashes and edge cases.
- `app/Database/Seeds/AuthSeeder.php`: Master seeder.
- `tests/unit/UserModelTest.php`: 10 unit test cases (100% passing).
Strict prohibition: `.env` was never read, touched, or modified.
Milestone Status: **DONE** (Gate PASSED on Iteration 1).
