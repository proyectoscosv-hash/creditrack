# Project: CrediTrack Authentication & Access Control System

## Architecture
- Framework: CodeIgniter 4 (PHP 8.2+) standard MVC.
- Web Entry Point: `public/index.php` -> `app/Config/Routes.php` -> Controllers -> Views.
- Data Flow:
  1. Client sends HTTP POST to `/login` with `usuario`, `contrasena`, CSRF token.
  2. `AuthController` validates form inputs and calls `UserModel`.
  3. `UserModel` queries `Usuario` joined with `Pais`:
     - Checks if account is locked (`bloqueado = 1` or `intentos_fallidos >= 3`). If locked, rejects immediately without verifying password.
     - Verifies password hash using `password_verify()`.
     - If password fails: increments `intentos_fallidos`. If count reaches 3, sets `bloqueado = 1`. Returns error with remaining attempts.
     - If password succeeds: resets `intentos_fallidos = 0`, `bloqueado = 0`.
  4. On successful login:
     - Calls `session()->regenerate(true)` for session fixation defense.
     - Sets session data: `isLoggedIn`, `codigo_empleado`, `usuario`, `nombre`, `rol`, `id_pais`, `pais_nombre`, `login_time`.
     - Redirects via HTTP 302 to `home.php`.
  5. `AuthFilter` intercepts protected routes (`home.php`, etc.):
     - Checks `session('isLoggedIn')`. If false, redirects to `/login`.
     - Checks country boundary (`session('id_pais')`) for any country-scoped requests.
  6. `Home::index` renders `app/Views/home.php`:
     - Confirms successful login and active session status.
     - Displays employee code, user name, username, assigned role, assigned country, access boundary statement.
     - Includes secure logout button.
  7. Visual styling:
     - Mandatory corporate palette: `#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C`.
     - Visible manual author attribution in HTML markup of `login.php` and `home.php`.
  8. Environment configuration:
     - Strict isolation: `.env` is never accessed, read, or modified.
     - Database configuration defined in `app/Config/Database.php` (MariaDB for dev, SQLite memory for tests).

## Feature Inventory
| # | Feature | Description | Milestone | Source |
|---|---------|-------------|-----------|--------|
| 1 | DB Native Config (No .env) | Configure DB connection directly in app/Config/Database.php without .env | M1_db_models | Survey / R5 |
| 2 | Relational Schema & Seeders | Clean MySQL & SQLite DDL and seeders for Pais and Usuario with security columns | M1_db_models | Survey / R1, R2 |
| 3 | User Model | UserModel with credential querying, country join, and atomic attempt management | M1_db_models | Survey / R1, R2 |
| 4 | Consecutive Failure Counter | Increments failure count by 1 for each failed login attempt in DB | M1_db_models | Survey / R2 |
| 5 | Counter Reset on Success | Resets failed attempt counter to 0 immediately upon valid credentials | M1_db_models | Survey / R2 |
| 6 | Account Lockout on 3rd Failure | Sets bloqueado = 1 immediately when consecutive failed attempts reach 3 | M1_db_models | Survey / R2 |
| 7 | Locked Account Rejection | Immediately denies authentication attempts for locked accounts without password check | M1_db_models | Survey / R2 |
| 8 | Password Hashing & Verification | Secure password verification using password_verify() with bcrypt | M2_auth_controllers_filters | Survey / R1 |
| 9 | Session Establishment & Scoping | Stores isLoggedIn, codigo_empleado, usuario, nombre, rol, id_pais, pais_nombre | M2_auth_controllers_filters | Survey / R1 |
| 10 | Session Fixation Defense | Calls session()->regenerate(true) upon login | M2_auth_controllers_filters | Survey / R1 |
| 11 | Protected Route Guard (AuthFilter) | Intercepts unauthenticated requests to protected pages, redirecting to /login | M2_auth_controllers_filters | Survey / R1, R3 |
| 12 | Role & Country Boundary Scoping | Validates role and restricts access to user's designated country (id_pais) | M2_auth_controllers_filters | Survey / R1 |
| 13 | Post-Auth Redirection | Automatically redirects user to home.php upon successful authentication | M2_auth_controllers_filters | Survey / R3 |
| 14 | Secure Logout | Terminates user session, cleans data, and redirects to /login | M2_auth_controllers_filters | Survey / R3 |
| 15 | Login View Implementation | Login page with form, CSRF token, remaining attempts display, lockout alert | M3_views_ui | Survey / R1, R2, R4 |
| 16 | Home Landing View Implementation | Landing page displaying session status, role, country, boundary statement, logout | M3_views_ui | Survey / R1, R3, R4 |
| 17 | Corporate 5-Color Palette | Implements #BF0426, #0511F2, #023373, #A61717, #8D253C across views | M3_views_ui | Survey / R4 |
| 18 | Manual Author Attribution | Displays visible static credit to system designers in login and home HTML | M3_views_ui | Survey / R4 |
| 19 | E2E Test Suite (Tiers 1-4) | Comprehensive opaque-box test suite verifying R1-R5 | E2E_Test_Track | Survey / All |
| 20 | Final E2E Pass & Hardening | Pass 100% E2E tests, Tier 5 adversarial coverage, and Forensic Audit | M4_final_e2e_hardening | Survey / All |

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | M1_db_models | Database native config (app/Config/Database.php), schema setup for MariaDB & SQLite, UserModel with attempt tracking | none | DONE |
| 2 | M2_auth_controllers_filters | AuthController, Home controller, Routes, AuthFilter for route protection & role/country access control | M1_db_models | DONE |
| 3 | M3_views_ui | Views login.php and home.php with 5-color palette, manual author attribution, session confirmation | M2_auth_controllers_filters | IN_PROGRESS |
| - | E2E_Test_Track | E2E Test Infrastructure & Test Suite (Tiers 1-4), publishes TEST_READY.md | none (parallel) | DONE |
| 4 | M4_final_e2e_hardening | Run 100% E2E tests (Tiers 1-4), Tier 5 adversarial testing & forensic audit | M3_views_ui, E2E_Test_Track | PLANNED |

## Interface Contracts
### UserModel ↔ AuthController
- `UserModel::findByUsernameWithCountry(string $username): ?array`
  Returns array with user and country data or null.
- `UserModel::recordFailedAttempt(string $codigoEmpleado, int $currentAttempts): int`
  Increments `intentos_fallidos`, sets `bloqueado = 1` if >= 3, updates `ultimo_fallo`, returns new count.
- `UserModel::resetFailedAttempts(string $codigoEmpleado): bool`
  Resets `intentos_fallidos = 0`, `bloqueado = 0`, updates `ultimo_login`.

### AuthController ↔ Session / AuthFilter
- Session payload keys:
  - `isLoggedIn`: bool (true)
  - `codigo_empleado`: string
  - `usuario`: string
  - `nombre`: string
  - `rol`: string ('Administrador', 'Supervisor', 'Asesor', etc.)
  - `id_pais`: int
  - `pais_nombre`: string
  - `login_time`: int (timestamp)
- `AuthFilter`:
  - Before: checks `session('isLoggedIn') === true`. If false, redirects to `/login` with error flash message.
  - Checks route access restrictions based on `session('rol')` and `session('id_pais')`.

### Views ↔ Controllers
- `/login` (GET): renders `app/Views/login.php`.
- `/login` (POST): handled by `AuthController::loginSubmit`. On error, redirects back with flash error message or remaining attempts. On success, redirects to `/home.php`.
- `/home.php` and `/home` (GET): guarded by `AuthFilter`, rendered by `Home::index` using `app/Views/home.php` passing session data.
- `/logout` (GET/POST): handled by `AuthController::logout`, destroys session, redirects to `/login`.

## Code Layout
- `app/Config/Database.php`: Native database configuration (MariaDB & SQLite `$tests`).
- `app/Config/Routes.php`: Application routes definition.
- `app/Config/Filters.php`: Filter aliases and globals/route filters.
- `app/Models/UserModel.php`: User model with security methods.
- `app/Controllers/AuthController.php`: Authentication controller (login, process, logout).
- `app/Controllers/Home.php`: Home landing page controller.
- `app/Filters/AuthFilter.php`: Authentication and role/country guard filter.
- `app/Views/login.php`: Login template with palette and attribution.
- `app/Views/home.php`: Landing page template with palette and attribution.
- `app/Database/Migrations/` or `app/Database/Seeds/`: Database initialization scripts.
- `tests/`: Automated PHPUnit test suite.
