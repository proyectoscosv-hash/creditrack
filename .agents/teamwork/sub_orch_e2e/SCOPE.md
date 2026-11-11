# Scope: E2E Testing Track (CrediTrack)

## Architecture
- Framework: PHPUnit 10/11 running within CodeIgniter 4 Test Suite (`CodeIgniter\Test\CIUnitTestCase`, `CodeIgniter\Test\FeatureTestTrait`, `CodeIgniter\Test\DatabaseTestTrait`).
- Execution Environment: In-memory SQLite (`:memory:`) using CodeIgniter 4 `$tests` database group.
- Isolation: Strictly isolated from `.env`. Zero `.env` access.
- Test Suite Organization:
  - `tests/_support/Database/Seeds/TestAuthSeeder.php`: In-memory SQLite schema and seed data for `Pais` and `Usuario`.
  - `tests/Feature/AuthTest.php`: Feature & boundary tests for login credential validation, empty fields, SQL injection resilience, session creation, session fixation defense, logout.
  - `tests/Feature/AccessControlTest.php`: Role identification, country assignment, country boundary isolation, unauthenticated access blocking on protected routes.
  - `tests/Feature/LockoutTest.php`: Failed attempt counter increment, 2-failures-then-success reset, 3rd consecutive failed attempt lockout, 4th attempt rejection on locked account even with valid credentials.
  - `tests/Feature/ViewUITest.php`: Visual corporate palette validation (`#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C`), manual author attribution markup presence, `home.php` session status confirmation badge.
  - `tests/Feature/WorkflowIntegrationTest.php`: Tier 3 pairwise interactions and Tier 4 complete real-world multi-user operational workflows.

## Feature Inventory
| # | Feature | Description | Milestone | Source |
|---|---------|-------------|-----------|--------|
| 1 | DB Native Config & SQLite Isolation | Verify tests run without accessing or requiring .env | SM1_infra_setup | Survey / R5 |
| 2 | Test Schema & SQLite Seeding | Setup Pais and Usuario DDL and test seeds in SQLite :memory: | SM1_infra_setup | Survey / R1, R2 |
| 3 | User Authentication Validation | Valid credentials succeed, invalid credentials fail with generic error | SM2_test_implementation | Survey / R1 |
| 4 | Session Role & Country Establishment | Session captures isLoggedIn, rol, id_pais, pais_nombre | SM2_test_implementation | Survey / R1 |
| 5 | Route Guard & Unauthenticated Access Block | AuthFilter intercepts unauthorized visits to home.php and redirects to login | SM2_test_implementation | Survey / R1, R3 |
| 6 | Country Boundary Isolation | Users cannot access data or operations outside their assigned id_pais | SM2_test_implementation | Survey / R1 |
| 7 | Failed Attempt Counter Increment | Consecutive invalid attempts increment counter (1, 2, 3) | SM2_test_implementation | Survey / R2 |
| 8 | Counter Reset on Success | Valid credentials reset failure counter to 0 before 3rd attempt | SM2_test_implementation | Survey / R2 |
| 9 | Account Lockout on 3rd Failure | 3rd consecutive failed login locks account (bloqueado = 1) | SM2_test_implementation | Survey / R2 |
| 10 | Locked Account Immediate Rejection | 4th attempt with valid password on locked account is denied | SM2_test_implementation | Survey / R2 |
| 11 | Post-Auth Redirection to home.php | Successful login issues HTTP 302 redirecting to home.php | SM2_test_implementation | Survey / R3 |
| 12 | home.php Session Confirmation View | Landing page confirms active session, role, country, boundary | SM2_test_implementation | Survey / R3 |
| 13 | Secure Logout | Logout invalidates session and redirects to login | SM2_test_implementation | Survey / R3 |
| 14 | Corporate 5-Color Palette | View markup implements #BF0426, #0511F2, #023373, #A61717, #8D253C | SM2_test_implementation | Survey / R4 |
| 15 | Manual Author Attribution | Static HTML author attribution displayed in login and home views | SM2_test_implementation | Survey / R4 |
| 16 | Cross-Feature & Workload Scenarios | Pairwise feature combinations and realistic multi-user workflows | SM2_test_implementation | Survey / Tiers 3-4 |
| 17 | Test Execution & Ready Publication | PHPUnit run validation and TEST_READY.md publication | SM3_verification_publish | Project Pattern |

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | SM1_infra_setup | SQLite in-memory test harness, Pais/Usuario test schema & seeder, TEST_INFRA.md | none | DONE |
| 2 | SM2_test_implementation | Comprehensive test classes (AuthTest, AccessControlTest, LockoutTest, ViewUITest, WorkflowIntegrationTest) across Tiers 1-4 | SM1_infra_setup | DONE |
| 3 | SM3_verification_publish | PHPUnit test run, coverage verification, TEST_READY.md creation, parent notification | SM2_test_implementation | IN_PROGRESS |

## Interface Contracts
### Test Harness ↔ Application
- Test Runner: `vendor/bin/phpunit` or `php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit`
- DB Connection: `$tests` group in `app/Config/Database.php` (`:memory:` SQLite3).
- Routes Exercised:
  - `GET /login`: Renders login view.
  - `POST /login` (or `/login/submit`): Authenticates credentials, returns redirect or error.
  - `GET /home.php` / `GET /home`: Landing page showing session confirmation.
  - `GET /logout` (or `POST /logout`): Destroys session.
- Database Tables in `:memory:`:
  - `Pais`: `Id_pais INTEGER PRIMARY KEY AUTOINCREMENT`, `Pais TEXT NOT NULL`
  - `Usuario`: `Codigo_Empleado TEXT PRIMARY KEY`, `Nombre TEXT NOT NULL`, `Usuario TEXT NOT NULL UNIQUE`, `Contrasena TEXT NOT NULL`, `Id_Pais INTEGER NOT NULL`, `telefono TEXT`, `rol TEXT NOT NULL`, `intentos_fallidos INTEGER NOT NULL DEFAULT 0`, `bloqueado INTEGER NOT NULL DEFAULT 0`, `ultimo_fallo TEXT`, `ultimo_login TEXT`
