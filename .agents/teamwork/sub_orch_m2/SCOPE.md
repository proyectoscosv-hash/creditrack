# Scope: Milestone 2 (M2_auth_controllers_filters)

## Architecture
- Module boundaries: Controllers (`AuthController`, `Home`), Filters (`AuthFilter`), Configuration (`Config/Routes.php`, `Config/Filters.php`), Tests (`tests/unit/`).
- Data flow:
  1. User requests `/` or `/login` -> `AuthController::login()`: if logged in, redirect to `home.php`; else render `login.php`.
  2. User submits POST `/login` -> `AuthController::loginSubmit()`:
     - Read input: `usuario`, `contrasena`.
     - Query user: `UserModel::findByUsernameWithCountry($usuario)`.
     - If user not found: flash error ("Credenciales incorrectas") -> redirect to `/login`.
     - If user found:
       - Check `bloqueado == 1` or `intentos_fallidos >= 3`:
         - Reject immediately: flash error ("Acceso bloqueado tras 3 intentos fallidos consecutivos de inicio de sesión erróneos") -> redirect to `/login`.
       - Check password: `password_verify($contrasena, $user['Contrasena'])`:
         - If FALSE:
           - Call `UserModel::recordFailedAttempt($user['Codigo_Empleado'], (int)$user['intentos_fallidos'])`.
           - If new count >= 3: flash lockout error ("Acceso bloqueado tras 3 intentos fallidos consecutivos de inicio de sesión erróneos").
           - Else: flash warning ("Credenciales incorrectas. Intentos fallidos: X de 3").
           - Redirect to `/login`.
         - If TRUE:
           - Call `UserModel::resetFailedAttempts($user['Codigo_Empleado'])`.
           - Call `session()->regenerate(true)` (Session fixation defense).
           - Store session payload:
             - `isLoggedIn` => true
             - `codigo_empleado` => $user['Codigo_Empleado']
             - `usuario` => $user['Usuario']
             - `nombre` => $user['Nombre']
             - `rol` => $user['rol']
             - `id_pais` => (int)$user['Id_Pais']
             - `pais_nombre` => $user['pais_nombre']
             - `login_time` => time()
           - Redirect to `home.php` (HTTP 302).
  3. User requests `/home.php` or `/home` -> guarded by `AuthFilter`:
     - `before()` checks `session()->get('isLoggedIn')`. If not true, redirect to `/login` with flash error ("Debe iniciar sesión para acceder al sistema").
     - Validates role and country access boundary: blocks any request targeting a country outside `session('id_pais')`.
     - If authenticated and authorized, invokes `Home::index()` which renders `home.php` passing session data.
  4. User requests `/logout` -> `AuthController::logout()`:
     - Destroys session: `session()->destroy()`.
     - Redirects to `/login`.

## Feature Inventory Mapping
| # | Feature | Description | Status |
|---|---------|-------------|--------|
| 8 | Password Hashing & Verification | Secure password verification using password_verify() with bcrypt | PLANNED |
| 9 | Session Establishment & Scoping | Stores isLoggedIn, codigo_empleado, usuario, nombre, rol, id_pais, pais_nombre | PLANNED |
| 10 | Session Fixation Defense | Calls session()->regenerate(true) upon login | PLANNED |
| 11 | Protected Route Guard (AuthFilter) | Intercepts unauthenticated requests to protected pages, redirecting to /login | PLANNED |
| 12 | Role & Country Boundary Scoping | Validates role and restricts access to user's designated country (id_pais) | PLANNED |
| 13 | Post-Auth Redirection | Automatically redirects user to home.php upon successful authentication | PLANNED |
| 14 | Secure Logout | Terminates user session, cleans data, and redirects to /login | PLANNED |

## Milestones / Iteration Target
Milestone 2 is executed in a single Iteration Loop (2B) targeting:
- `app/Controllers/AuthController.php`
- `app/Controllers/Home.php`
- `app/Filters/AuthFilter.php`
- `app/Config/Filters.php`
- `app/Config/Routes.php`
- Automated tests verifying auth, controllers, and filters.

## Interface Contracts
### UserModel ↔ AuthController
- `UserModel::findByUsernameWithCountry(string $username): ?array`
- `UserModel::recordFailedAttempt(string $codigoEmpleado, int $currentAttempts): int`
- `UserModel::resetFailedAttempts(string $codigoEmpleado): bool`

### AuthController ↔ Session / AuthFilter
- Session keys: `isLoggedIn`, `codigo_empleado`, `usuario`, `nombre`, `rol`, `id_pais`, `pais_nombre`, `login_time`.
- `AuthFilter`: validates `session('isLoggedIn') === true` and provides role/country scoping helper/enforcement.

### Code Layout & Write Boundaries
- Owned by M2:
  - `app/Controllers/AuthController.php`
  - `app/Controllers/Home.php`
  - `app/Filters/AuthFilter.php`
  - `app/Config/Filters.php`
  - `app/Config/Routes.php`
  - `tests/unit/` (auth tests)
- NOT owned:
  - `app/Views/` (owned by M3)
  - `.env` (ABSOLUTE PROHIBITION)
