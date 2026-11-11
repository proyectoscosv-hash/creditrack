# Scope: Milestone 3 (M3_views_ui)

## Overview
Implement responsive HTML5 views for CrediTrack: `app/Views/login.php` and `app/Views/home.php`. Both views must adhere strictly to the 5-color corporate palette, render manual author attribution visibly, display session health / role / country access boundaries, and pass all automated tests in `tests/Feature/ViewUITest.php` and `tests/Feature/WorkflowIntegrationTest.php`.

## Architecture & Code Layout
- Exclusive Write Ownership:
  - `app/Views/login.php`
  - `app/Views/home.php`
  - Any CSS/view components in `app/Views/`
- Strictly Prohibited:
  - Do NOT modify `Database.php`, `UserModel.php`, `AuthController.php`, `Home.php`, `Routes.php`, or any file in `tests/`.
  - ABSOLUTE PROHIBITION: DO NOT touch, read, or modify `.env`!

## Feature Inventory (Milestone 3)
| # | Feature | Target File | Description | Source |
|---|---------|-------------|-------------|--------|
| 15 | Login View Implementation | `app/Views/login.php` | Responsive HTML5 form posting to `/login` with `usuario`, `contrasena`, CSRF token (`<?= csrf_field() ?>`), flash message displays for errors, warnings (remaining attempts 1 of 3, 2 of 3), and lockout alert ("Acceso bloqueado tras 3 intentos fallidos consecutivos de inicio de sesión erróneos."). | Survey / R1, R2, R4 |
| 16 | Home Landing View Implementation | `app/Views/home.php` | Responsive HTML5 dashboard displaying prominent login success banner, active session health badge ("Estado de la sesión: Activa"), user profile data (employee code, full name, username), assigned role badge, assigned country badge, access boundary statement ("Acceso restringido exclusivamente a información y operaciones de [pais] con privilegios de [rol]."), and secure logout button/link (`/logout`). | Survey / R1, R3, R4 |
| 17 | Corporate 5-Color Palette | `app/Views/login.php`, `app/Views/home.php` | Strict visual styling implementation of all 5 hex colors: `#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C`. Must maintain accessible contrast (light text on dark corporate background). | Survey / R4 |
| 18 | Manual Author Attribution | `app/Views/login.php`, `app/Views/home.php` | Visible static HTML markup crediting system designers/developers. Must be semantically visible (no `display:none` or `visibility:hidden`) and match regex `/(diseñad[oa]|desarrollad[oa]|diseñadores|equipo de desarrollo|autores)/i`. | Survey / R4 |

## Interface Contracts & View Data

### 1. `app/Views/login.php`
- Form method: `POST` to `<?= site_url('login') ?>` (or `action="/login"` / `url_to('login')`)
- CSRF field: `<?= csrf_field() ?>`
- Input field: `name="usuario"` with value preserved via `<?= esc(old('usuario', '')) ?>`
- Input field: `name="contrasena"`, type `password`
- Submit button
- Flash alert handling:
  - `session('error')`: e.g. "Credenciales incorrectas.", "Acceso bloqueado tras 3 intentos fallidos consecutivos de inicio de sesión erróneos.", "Debe iniciar sesión para acceder al sistema", etc.
  - `session('warning')`: e.g. "Credenciales incorrectas. Intentos fallidos: 1 de 3"
  - `session('remaining_attempts')`: integer count
  - `session('message')`: e.g. "Sesión cerrada exitosamente."
  - Visual styling of alerts using corporate colors `#BF0426` or `#A61717`.
- Viewport tag: `<meta name="viewport" content="width=device-width, initial-scale=1.0">`
- Static author attribution in footer or header.

### 2. `app/Views/home.php`
- Incoming Data Array `$data`:
  - `isLoggedIn`: (bool)
  - `codigo_empleado`: (string) e.g. 'EMP-ADM-01'
  - `usuario`: (string) e.g. 'admin_sv'
  - `nombre`: (string) e.g. 'Carlos Admin'
  - `rol`: (string) e.g. 'Administrador', 'Supervisor', 'Asesor'
  - `id_pais`: (int) e.g. 1, 2, 3
  - `pais_nombre`: (?string) e.g. 'El Salvador', 'Guatemala', 'Honduras' (must handle null gracefully)
  - `login_time`: (int) unix timestamp
- Required Output Elements:
  - Confirmation banner matching `/(¡?inicio de sesi[oó]n exitoso!?|bienvenido|sesi[oó]n iniciada)/i`
  - Active session health badge matching `/(estado de la sesi[oó]n:\s*activa|sesi[oó]n\s*activa)/i`
  - User identity: employee code (`$codigo_empleado`), user full name (`$nombre`), username (`$usuario`), escaped with `esc()`
  - Role badge matching `/(rol|rol asignado):?\s*supervisor/i` (and similar for Admin/Asesor)
  - Country badge matching `/(pa[ií]s|pa[ií]s asignado):?\s*guatemala/i` (and El Salvador/Honduras, with fallback if null)
  - Access boundary statement matching `/(acceso restringido.*honduras|l[ií]mite territorial.*honduras|operaciones de honduras)/i`
  - Logout action link/button to `<?= site_url('logout') ?>` with visible label matching `/(cerrar sesi[oó]n|salir|logout)/i`
  - Corporate 5-color palette (#BF0426, #0511F2, #023373, #A61717, #8D253C)
  - Visible manual author attribution matching `/(diseñad[oa]|desarrollad[oa]|diseñadores|equipo de desarrollo|autores)/i`
  - Viewport tag: `<meta name="viewport" content="width=device-width, initial-scale=1.0">`
  - Safe HTML escaping (`esc()`) on all user profile attributes to prevent XSS.

## Verification Gate Criteria
1. PHPUnit feature tests pass: `php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit tests/Feature/ViewUITest.php`
2. Workflow tests pass: `php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit tests/Feature/WorkflowIntegrationTest.php`
3. All existing unit and feature test suites pass.
4. Reviewers APPROVE code structure, accessibility, escaping, and contract compliance.
5. Challengers verify edge cases, XSS escaping, regex matching, and palette presence.
6. Forensic Auditor issues CLEAN verdict (zero .env access, genuine implementation).
