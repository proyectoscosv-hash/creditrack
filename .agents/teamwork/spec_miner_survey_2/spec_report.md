# Detailed Specification Report — Creditrack Authentication & Access Control System

**Document Version**: 1.0.0  
**Phase**: Phase 0 — Survey & Specification Mining  
**Author**: Spec Miner 2 (Requirements & Spec Miner)  
**Date**: 2026-11-10  
**Status**: Authoritative Reference Document  

---

## 1. Executive Summary & Scope Definition

The objective of this specification is to define the exact architectural, functional, security, visual, and environmental requirements for the **Creditrack Authentication and Role/Country Access Control System** in production.

Creditrack is an operational credit tracking and field management platform built on CodeIgniter 4 (PHP 8.2+). The system requires a secure login mechanism that validates users against the database, establishes role- and country-scoped sessions, locks out accounts after three consecutive failed attempts, redirects authenticated users to a dedicated `home.php` landing page confirming session state and authorization boundaries, presents an interface compliant with an exact 5-color corporate palette with manual author attribution, and strictly enforces the prohibition of the `.env` configuration file.

---

## 2. Authoritative Sources & Core Constraints

### 2.1 Authoritative Sources
1. **`ORIGINAL_REQUEST.md`**: Primary requirements document defining R1 through R5, integrity constraints, and acceptance criteria.
2. **`Base_Datos.sql`**: Authoritative relational schema establishing the geographical hierarchy (`Pais`, `Departamento`, `Municipio`, `Distrito`), operational entities (`Agencias`, `Marca`, `Ruta`, `Ubicaciones_Ruta`, `Asignacion`, `Tareas`), notifications (`Notificaciones`), and user management (`Usuario`).
3. **CodeIgniter 4 Framework Architecture**: `app/Config/Database.php`, `app/Config/Routes.php`, `app/Config/Filters.php`, `app/Config/Session.php`, and `system/`.

### 2.2 Hard Integrity Constraints
- **PROHIBITION OF `.env` (R5)**: The team and application are strictly prohibited from creating, accessing, reading, or modifying `.env`. Any file access or inspection of `.env` constitutes a critical integrity violation. Database connection configuration must reside strictly in `app/Config/Database.php`.
- ** Bespoke Core Logic**: No code copying from third-party authentication packages (e.g., Myth:Auth, Shield, Laravel Breeze/Fortify). All authentication, attempt-tracking, and session logic must be custom-developed.
- **No External Scripts / Execution Delegation**: No delegation to external CLI tools or unmanaged scripts.
- **Read-Only Survey Phase**: During this Phase 0 Survey, no application source files or database schemas are modified.

---

## 3. Data Model & Access Control Architecture

### 3.1 Existing Database Schema (`Base_Datos.sql`)

```sql
-- Geographical Entity
CREATE TABLE Pais (
    Id_pais SERIAL PRIMARY KEY,
    Pais VARCHAR(100) NOT NULL
);

-- Territorial Hierarchy
CREATE TABLE Departamento (
    Id_departamento SERIAL PRIMARY KEY,
    Departamento VARCHAR(100) NOT NULL,
    Id_pais INT NOT NULL REFERENCES Pais(Id_pais) ON DELETE CASCADE
);

CREATE TABLE Municipio (
    Id_municipio SERIAL PRIMARY KEY,
    Municipio VARCHAR(100) NOT NULL,
    Id_departamento INT NOT NULL REFERENCES Departamento(Id_departamento) ON DELETE CASCADE
);

CREATE TABLE Distrito (
    Id_distrito SERIAL PRIMARY KEY,
    Distrito VARCHAR(100) NOT NULL,
    Id_municipio INT NOT NULL REFERENCES Municipio(Id_municipio) ON DELETE CASCADE
);

-- User Entity
CREATE TABLE Usuario (
    Codigo_Empleado VARCHAR(50) PRIMARY KEY,
    Nombre VARCHAR(150) NOT NULL,
    Usuario VARCHAR(50) UNIQUE NOT NULL,
    Contrasena VARCHAR(255) NOT NULL,
    Id_Pais INT NOT NULL REFERENCES Pais(Id_pais),
    telefono VARCHAR(20),
    rol VARCHAR(50) NOT NULL
);
```

### 3.2 Required Extensions for Attempt Tracking & Lockout (R2)
To satisfy R2 (lockout after 3 consecutive failed attempts) with persistent state across sessions and browsers, the `Usuario` entity (or an auxiliary `LoginAttempts` tracking structure) must support:
- `intentos_fallidos`: INT DEFAULT 0 (Counter of consecutive failed authentication attempts).
- `bloqueado`: BOOLEAN / TINYINT DEFAULT 0 (Flag indicating whether the account is currently locked).
- `ultimo_intento_fallido`: TIMESTAMP / DATETIME NULL (Timestamp of the most recent failure for audit/lockout expiration).

### 3.3 Roles and Geographical Scopes (R1)
1. **Roles to Support**:
   - `Administrador` (Admin): System-wide or national administrative governance; oversees configuration, users, and audit logs.
   - `Supervisor`: Regional supervisor; oversees routes, agencies, assignments, and tasks strictly within their designated country (`Id_Pais`).
   - `Asesor` / `Agente` / `Operador`: Field agent; executes assigned tasks (`Tareas`) and routes (`Ruta`) exclusively within their designated agency and country.
2. **Country Boundary Rule**:
   - Data partitioning is partitioned at the top level by `Pais.Id_pais`.
   - A user assigned to Country $A$ (e.g., El Salvador, $Id\_pais = 1$) cannot query, view, or modify agencies, assignments, routes, or user profiles belonging to Country $B$ (e.g., Guatemala, $Id\_pais = 2$).
   - The session must enforce `Id_Pais` validation on every resource request.

---

## 4. Detailed Functional Requirements

### 4.1 Requirement 1 (R1): Authentication & Access Control by Role and Country
- **Objective**: Authenticate user credentials securely, establish an immutable session, and restrict data boundaries.
- **Workflow**:
  1. User submits login form with `usuario` and `contrasena`.
  2. Input validation: Both fields are required; string length limits enforced; input sanitized/trimmed.
  3. Pre-auth Lockout Check: Query user by `Usuario`. If `bloqueado == 1` or `intentos_fallidos >= 3`, reject immediately with a lockout notice.
  4. Credential Verification: Verify password hash via `password_verify($contrasena, $user['Contrasena'])`.
  5. Session Establishment:
     - On success: Reset `intentos_fallidos = 0`, `bloqueado = 0`.
     - Regenerate session ID (`session()->regenerate()`) to defend against session fixation attacks.
     - Store session payload:
       ```php
       [
           'isLoggedIn'      => true,
           'codigo_empleado' => $user['Codigo_Empleado'],
           'usuario'         => $user['Usuario'],
           'nombre'          => $user['Nombre'],
           'rol'             => $user['rol'],
           'id_pais'         => (int)$user['Id_Pais'],
           'pais_nombre'     => $pais['Pais'],
           'login_time'      => time(),
       ]
       ```
  6. Access Boundary Enforcement:
     - Protect all internal application endpoints using an authentication filter (`AuthFilter`).
     - Any request missing `isLoggedIn === true` is immediately redirected to `/login` with an unauthorized alert.
     - Any request targeting resources outside `session('id_pais')` is denied with an HTTP 403 Forbidden.

### 4.2 Requirement 2 (R2): Security Lock on 3 Consecutive Failed Attempts
- **Objective**: Prevent brute-force password guessing by enforcing account lockout upon 3 consecutive incorrect credentials.
- **Consecutive Failure Rules**:
  - **Attempt 1 (Failure)**: `intentos_fallidos` becomes 1. Error message: *"Credenciales incorrectas. Le quedan 2 intentos."*
  - **Attempt 2 (Failure)**: `intentos_fallidos` becomes 2. Warning message: *"Credenciales incorrectas. Le queda 1 intento antes del bloqueo."*
  - **Attempt 3 (Failure)**: `intentos_fallidos` becomes 3, `bloqueado` set to 1. Alert message: *"Acceso bloqueado tras 3 intentos fallidos consecutivos. Comuníquese con el administrador."*
  - **Success Reset Rule**: If attempt 1 or 2 fails, but the subsequent attempt succeeds with valid credentials, `intentos_fallidos` MUST immediately be reset to 0. No lockout is triggered.
  - **Locked Account Behavior**: If an account has `bloqueado = 1`, all login attempts are blocked immediately without validating the password.
  - **Tracking Scope**: State must be persisted in the database so that switching browsers, clearing cookies, or using private browsing cannot bypass the 3-attempt lockout.

### 4.3 Requirement 3 (R3): Redirection & Landing Page (`home.php`)
- **Objective**: Direct authenticated users to `home.php` and render session status, role, country, and operational boundaries.
- **Redirection**:
  - Immediately following successful authentication, redirect user via HTTP 302 to `home.php`.
- **Target Page Specifications (`home.php`)**:
  - **Location**: Exists as an accessible landing view and route (supporting both CI4 route `home.php` / `/home` and direct web access).
  - **View Content**:
    1. **Authentication Confirmation**: Prominent greeting and confirmation banner: *"¡Inicio de sesión exitoso! Bienvenido al sistema Creditrack."*
    2. **Session Health Indicator**: Badge or alert showing *"Estado de la sesión: Activa"* with current session timestamp.
    3. **User Profile Data**:
       - Código de Empleado
       - Nombre Completo
       - Nombre de Usuario
    4. **Role Confirmation**: Explicit badge showing *"Rol Asignado: [rol]"* (e.g., Administrador, Supervisor, Asesor).
    5. **Country Confirmation**: Explicit badge showing *"País Asignado: [pais_nombre]"*.
    6. **Access Boundary Statement**: Clear boundary declaration, confirming: *"Acceso restringido exclusivamente a información y operaciones de [pais_nombre] con privilegios de [rol]."*
    7. **Secure Logout**: A functional logout button that calls `/logout`, invalidates session data (`session()->destroy()`), and redirects to `/login`.
  - **Access Protection**: Direct access to `home.php` by an unauthenticated user must be blocked, redirecting to login.

### 4.4 Requirement 4 (R4): Visual Interface & Author Attribution
- **Objective**: Implement a cohesive, professional login and landing interface using the mandatory 5-color corporate palette and displaying manual author attribution.
- **Mandatory Corporate Palette**:
  - `Color 1`: `#BF0426` (Crimson / Deep Primary Red) — Primary alerts, security warning banners, accent borders.
  - `Color 2`: `#0511F2` (Electric Royal Blue) — Primary action buttons, active focus rings, interactive states.
  - `Color 3`: `#023373` (Navy Dark Blue) — Header background, card headers, container framing, brand title text.
  - `Color 4`: `#A61717` (Dark Brick Red) — Lockout alerts, consecutive failure counters, critical warning badges.
  - `Color 5`: `#8D253C` (Wine / Claret) — Subheaders, attribution container background, secondary accents.
- **Manual Author Attribution**:
  - Must be explicitly written in the markup (manual HTML entry).
  - Located in a visible footer or credit banner on the login interface and `home.php`.
  - Format:
    ```html
    <footer class="system-attribution" style="background-color: #8D253C; color: #ffffff;">
        <p>Sistema Creditrack &bull; Diseñado y desarrollado por: <strong>Equipo de Desarrollo Creditrack</strong></p>
    </footer>
    ```

### 4.5 Requirement 5 (R5): Environment Configuration Restriction (.env)
- **Objective**: Prevent any interaction with `.env` and configure all database connections natively.
- **Implementation Rules**:
  - **ZERO ACCESS**: Under no circumstances should `.env` be created, read, opened, written, or checked in any agent workflow or application script.
  - **Native PHP Configuration**: Database connectivity is configured strictly in `app/Config/Database.php`.
  - **Connection Parameters**:
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
        'port'         => 3306,
    ];
    ```
  - **Testing Environment**: For automated unit tests, the `$tests` configuration group uses SQLite in-memory (`:memory:`) or SQLite file (`database.db`), guaranteeing zero dependency on `.env`.

---

## 5. Features Discovered

| # | Category | Feature | Description | Inputs | Outputs | Error Behavior | Discovered Via |
|---|----------|---------|-------------|--------|---------|----------------|----------------|
| 1 | Auth (R1) | User Credential Validation | Compares submitted user credentials against `Usuario` table in DB using `password_verify` | `usuario` (string), `contrasena` (string) | Valid user record & session creation | Returns generic error message "Credenciales incorrectas" | `ORIGINAL_REQUEST.md` § R1, `Base_Datos.sql` |
| 2 | Auth (R1) | Role Identification & Scoping | Retrieves and assigns user role (`rol`) into session | User record from DB | `session('rol')` set | Blocks session initialization if role is missing or invalid | `ORIGINAL_REQUEST.md` § R1, `Base_Datos.sql` |
| 3 | Auth (R1) | Country Identification & Boundary | Associates user with their assigned `Id_Pais` and `Pais` name in session | `Id_Pais` from `Usuario` joined with `Pais` | `session('id_pais')`, `session('pais_nombre')` | Blocks session if country relation does not exist | `ORIGINAL_REQUEST.md` § R1, `Base_Datos.sql` |
| 4 | Security (R1) | Session Fixation Prevention | Regenerates session ID upon successful login | Existing session ID | New secure session ID | Prevents session hijacking; drops unverified tokens | CI4 Framework Architecture (`Session.php`) |
| 5 | Security (R1) | Protected Route Filter (`AuthFilter`) | Blocks unauthenticated requests from accessing protected pages (`home.php`, dashboard) | Incoming HTTP Request | Allows request or redirects to `/login` | 302 Redirect to `/login` with flash error | `app/Config/Filters.php`, `ORIGINAL_REQUEST.md` § R1 |
| 6 | Security (R2) | Consecutive Failure Counter | Increments failure count by 1 for each failed login attempt on a specific account | `Usuario`, failed password submission | Incremented `intentos_fallidos` (1, 2, 3) | Logs attempt; displays remaining attempt count | `ORIGINAL_REQUEST.md` § R2 |
| 7 | Security (R2) | Counter Reset on Success | Resets failed attempt counter to 0 immediately upon entering valid credentials before threshold | Valid credentials | `intentos_fallidos = 0`, `bloqueado = 0` | None | `ORIGINAL_REQUEST.md` § R2 |
| 8 | Security (R2) | Account Lockout on 3rd Failure | Sets `bloqueado = 1` immediately when consecutive failed attempts reach 3 | 3rd consecutive failed password attempt | Account locked state persisted in DB | Rejects login; displays "Acceso bloqueado tras 3 intentos fallidos" | `ORIGINAL_REQUEST.md` § R2 |
| 9 | Security (R2) | Locked Account Rejection | Immediately denies authentication attempts for locked accounts without password validation | Any login attempt on account where `bloqueado = 1` | Lockout rejection message | Denies login regardless of entered password | `ORIGINAL_REQUEST.md` § R2 |
| 10 | Routing (R3) | Post-Auth Redirection | Automatically redirects user to `home.php` upon successful authentication | Valid session creation | HTTP 302 Redirect to `home.php` | Fallback to default route if redirect fails | `ORIGINAL_REQUEST.md` § R3 |
| 11 | View (R3) | Session Status Display | Displays active session confirmation and timestamp on `home.php` | Active session | Visual badge "Estado de la sesión: Activa" | Redirects to login if session inactive | `ORIGINAL_REQUEST.md` § R3 |
| 12 | View (R3) | Role & Country Confirmation | Explicitly displays the authenticated user's role and country boundaries on `home.php` | `session('rol')`, `session('pais_nombre')` | Badges and authorization confirmation text | None (fields guaranteed by login) | `ORIGINAL_REQUEST.md` § R3 |
| 13 | View (R3) | Secure Logout | Terminates user session, cleans data, and redirects to login page | Logout action / button click | Destroyed session, redirect to `/login` | None | CodeIgniter 4 Best Practices |
| 14 | UI (R4) | Corporate 5-Color Palette | Styles all UI elements using `#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C` | CSS stylesheet / embedded styles | Compliant color rendering across views | None | `ORIGINAL_REQUEST.md` § R4 |
| 15 | UI (R4) | Manual Author Attribution | Displays visible static credit mentioning system designers in the interface | Static HTML in view templates | Rendered attribution text in footer/card | None | `ORIGINAL_REQUEST.md` § R4 |
| 16 | Config (R5) | Natively-configured Database | Configures database connection directly in `app/Config/Database.php` without `.env` | `app/Config/Database.php` | Active DB connection | DB Connection Exception if credentials invalid | `ORIGINAL_REQUEST.md` § R5, `Database.php` |
| 17 | Config (R5) | Strict `.env` Isolation | Explicit prohibition against reading or touching `.env` | CI4 environment loader | Zero access to `.env` | Build/Integrity failure if `.env` accessed | `ORIGINAL_REQUEST.md` § R5 |

---

## 6. Edge Cases & Boundary Conditions

| # | Feature | Input / Scenario | Observed / Required Behavior |
|---|---------|-------------------|------------------------------|
| 1 | Lockout (R2) | 2 consecutive failed attempts followed by 1 successful login | First attempt fails (`intentos=1`); second attempt fails (`intentos=2`); third attempt succeeds with correct password -> `intentos_fallidos` is reset to 0, session established, redirect to `home.php`. Lockout is NOT triggered. |
| 2 | Lockout (R2) | 3 consecutive failed attempts on same account | Attempts 1, 2, and 3 fail -> `intentos_fallidos` reaches 3 -> `bloqueado` set to 1. User receives "Acceso bloqueado tras 3 intentos fallidos consecutivos". |
| 3 | Lockout (R2) | 4th attempt on locked account with CORRECT password | Account is already locked (`bloqueado = 1`). User enters the correct password. System rejects login immediately with lockout notice without verifying password or resetting counter. |
| 4 | Lockout (R2) | Brute force attempt using private/incognito browsing or clearing cookies | Attacker fails 2 times, clears browser cookies/opens incognito, fails 1 more time -> Lockout counter stored in DB reaches 3. Account is locked. Cookie clearing cannot bypass DB-backed lockout. |
| 5 | Auth (R1) | Non-existent username login attempt | User enters username that does not exist in `Usuario` table. System returns generic "Credenciales incorrectas" error (preventing username enumeration) and does not crash. |
| 6 | Auth (R1) | Blank or whitespace-only username/password | Form submitted with empty fields or only spaces -> Client & Server validation catches empty inputs before DB query ("El nombre de usuario es obligatorio", "La contraseña es obligatoria"). Does not increment failed attempt counter for blank submissions. |
| 7 | Auth (R1) | SQL Injection payload in username (e.g., `' OR '1'='1' --`) | CI4 Query Builder uses parameterized queries/prepared statements. Input treated strictly as literal string. No SQL injection occurs. Authentication fails cleanly. |
| 8 | Access (R3) | Unauthenticated user visits `home.php` directly | User enters URL `http://localhost/creditrack/home.php` without active session -> `AuthFilter` intercepts request, denies access, and redirects to `/login` with flash alert: "Debe iniciar sesión para acceder". |
| 9 | Access (R1) | Authenticated user visits `/login` | User with active session enters `/login` -> Redirected to `home.php` to prevent duplicate sessions. |
| 10 | Access (R1) | Cross-country data access attempt | User from Country 1 (`Id_Pais = 1`) attempts to view/modify Country 2 (`Id_Pais = 2`) records -> System enforces boundary condition, returns 403 Forbidden or filters out foreign records. |
| 11 | UI (R4) | Responsive layout on mobile / narrow viewports | Screen width < 600px -> Form fields, corporate palette elements, and manual attribution scale adaptively without breaking layout or hiding attribution. |
| 12 | UI (R4) | Palette compliance verification | CSS contains exact hexadecimal colors `#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C`. No unapproved fallback colors used for primary UI components. |
| 13 | Config (R5) | Application bootstrap without `.env` | Application boots up with only `app/Config/Database.php` present. No error occurs, and no file read operation targets `.env`. |

---

## 7. Security, Non-Functional, and Integrity Requirements

1. **Password Security**:
   - Passwords must be hashed using PHP's native `password_hash($password, PASSWORD_BCRYPT)`.
   - Never store plain text passwords.
   - Verification must use constant-time `password_verify($password, $hash)`.
2. **Session Security**:
   - `session()->regenerate()` must be invoked upon login.
   - Session cookies configured with `HttpOnly`, `SameSite=Lax`, and secure where HTTPS is present.
   - Session destroyed on logout (`session()->destroy()`).
3. **Cross-Site Scripting (XSS) Prevention**:
   - All dynamic variables rendered in views (`$usuario`, `$nombre`, `$rol`, `$pais`) must be sanitized using `esc()` or `htmlspecialchars()`.
4. **CSRF Protection**:
   - Login form must include a CSRF token: `<?= csrf_field() ?>`.
5. **Database Configuration Isolation**:
   - Database credentials reside exclusively in `app/Config/Database.php`.
   - Under no circumstances should `.env` be referenced in configuration loaders or file access routines.

---

## 8. Acceptance & Verification Criteria

| ID | Criteria | Verification Method |
|----|----------|---------------------|
| AC-1 | User credentials validate against database | Execute login with valid DB user; verify authentication success and redirect. |
| AC-2 | Invalid credentials rejected with generic error | Execute login with wrong password; verify rejection and error message. |
| AC-3 | Session holds `rol` and `id_pais` / `pais` | Inspect session contents after login; verify presence and accuracy of role and country. |
| AC-4 | Failed attempt counter resets to 0 upon successful login after 1 or 2 failures | Perform 2 failed attempts, then 1 successful login; verify `intentos_fallidos` is 0. |
| AC-5 | Account locks after 3 consecutive failed attempts | Perform 3 failed attempts consecutively; verify account status `bloqueado = 1`. |
| AC-6 | 4th attempt on locked account rejected even with valid credentials | Attempt login on locked account with correct password; verify rejection due to lockout. |
| AC-7 | Successful login redirects to `home.php` | Check HTTP response status 302 and `Location: .../home.php`. |
| AC-8 | `home.php` displays session status, role, country, and boundary | Inspect `home.php` HTML; verify presence of active status, user role, assigned country, and access boundary confirmation. |
| AC-9 | Unauthenticated access to `home.php` blocked | Clear session, request `home.php`; verify 302 redirect to login. |
| AC-10 | UI implements palette: `#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C` | Inspect CSS / HTML view markup; verify all 5 hex colors are implemented. |
| AC-11 | UI visibly includes manual author attribution | Inspect HTML of login and `home.php`; verify visible author text. |
| AC-12 | `.env` is never accessed, read, or modified | Audit file logs and execution traces; verify zero access to `.env`. |
| AC-13 | Automated tests validate R1-R5 | Run test suite via PHPUnit; verify all test cases pass without contacting `.env`. |

---
*Report completed by Spec Miner 2 (Phase 0 Survey).*
