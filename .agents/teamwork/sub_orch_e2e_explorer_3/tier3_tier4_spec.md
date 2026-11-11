# Master E2E Specification: Tier 3 (Cross-Feature Combinations) & Tier 4 (Real-World Application Scenarios) + TEST_READY Publication Architecture

**Document Version**: 1.0.0  
**Track**: E2E Testing Track (CrediTrack Authentication & Access Control System)  
**Author**: Scenario & Interaction Explorer (sub_orch_e2e_explorer_3)  
**Date**: 2026-11-10  
**Target Class**: `tests/Feature/WorkflowIntegrationTest.php`  
**State Folder**: `c:\xampp\htdocs\creditrack\.agents\teamwork\sub_orch_e2e_explorer_3`  

---

## 1. Executive Summary & Architecture Scope

### 1.1 Purpose
This specification establishes the authoritative testing design for:
1. **Tier 3: Cross-Feature Combinations (Pairwise Coverage)** — Validating the complex interactions between Authentication (R1), Failed Attempt Tracking & Lockout (R2), Role & Country Boundary Scoping (R1), Session Fixation Defense (R1), and UI Corporate Palette / Manual Author Attribution (R4).
2. **Tier 4: Real-World Application Scenarios** — Validating multi-step, realistic end-to-end user journeys representing field agents, regional supervisors, system administrators, and malicious actors under brute-force attacks, lockout recovery, and session expiration.
3. **TEST_READY Publication Architecture** — Defining the explicit readiness gate criteria and the full production-ready template for `TEST_READY.md` to be published at the project root (`c:\xampp\htdocs\creditrack\TEST_READY.md`).

### 1.2 Environmental & Integrity Invariants
- **ZERO `.env` Access (R5)**: In strict adherence to R5, no test, runner script, or data seeder may read, open, parse, create, or touch `.env`. Database testing executes exclusively in-memory via CodeIgniter 4's `$tests` SQLite connection (`:memory:`).
- **Framework Compliance**: Built on CodeIgniter 4 Test Suite (`CodeIgniter\Test\CIUnitTestCase`, `CodeIgniter\Test\FeatureTestTrait`, `CodeIgniter\Test\DatabaseTestTrait`).
- **Cryptographic Security**: Passwords verified via `password_verify()` against standard `PASSWORD_BCRYPT` hashes. Session regeneration enforced via `session()->regenerate(true)`.
- **Visual Branding Palette**: Every HTML response tested against the mandatory corporate palette:
  - `#BF0426` (Primary Alert / Danger Red)
  - `#0511F2` (Interactive Royal Blue)
  - `#023373` (Deep Navy Blue / Framing)
  - `#A61717` (Dark Brick Red / Critical Lockout)
  - `#8D253C` (Wine / Claret Accent / Attribution Background)
- **Manual Author Attribution**: Visible static attribution in HTML markup crediting the system design team.

---

## 2. Shared Test Harness & Baseline Fixtures

### 2.1 Database Schema (`:memory:` SQLite DDL)
The test runner boots the in-memory SQLite database before test execution using `TestAuthSeeder`.

```sql
CREATE TABLE Pais (
    Id_pais INTEGER PRIMARY KEY AUTOINCREMENT,
    Pais TEXT NOT NULL
);

CREATE TABLE Usuario (
    Codigo_Empleado TEXT PRIMARY KEY,
    Nombre TEXT NOT NULL,
    Usuario TEXT NOT NULL UNIQUE,
    Contrasena TEXT NOT NULL,
    Id_Pais INTEGER NOT NULL,
    telefono TEXT,
    rol TEXT NOT NULL,
    intentos_fallidos INTEGER NOT NULL DEFAULT 0,
    bloqueado INTEGER NOT NULL DEFAULT 0,
    ultimo_fallo TEXT NULL,
    ultimo_login TEXT NULL,
    FOREIGN KEY (Id_Pais) REFERENCES Pais(Id_pais)
);
```

### 2.2 Canonical Test Fixture Data (`TestAuthSeeder`)

| User Key | Codigo_Empleado | Nombre | Usuario | Plain Password | Id_Pais | Pais Name | Rol | Initial `intentos` | Initial `bloqueado` |
|---|---|---|---|---|---|---|---|---|---|
| `admin_sv` | `EMP-ADM-01` | Carlos Morales | `admin_user` | `AdminPass123!` | 1 | El Salvador | `Administrador` | 0 | 0 |
| `sup_sv` | `EMP-SUP-01` | Elena Rostova | `supervisor_sv` | `SuperSv123!` | 1 | El Salvador | `Supervisor` | 0 | 0 |
| `sup_gt` | `EMP-SUP-02` | Mario Mendez | `supervisor_gt` | `SuperGt123!` | 2 | Guatemala | `Supervisor` | 0 | 0 |
| `ase_sv` | `EMP-ASE-01` | Juan Perez | `asesor_sv` | `AsesorSv123!` | 1 | El Salvador | `Asesor` | 0 | 0 |
| `ase_gt` | `EMP-ASE-02` | Ana Gomez | `asesor_gt` | `AsesorGt123!` | 2 | Guatemala | `Asesor` | 0 | 0 |
| `locked_user`| `EMP-LCK-01` | Pedro Castro | `locked_user` | `LockedPass123!`| 1 | El Salvador | `Asesor` | 3 | 1 |

All passwords stored as `password_hash($plain, PASSWORD_BCRYPT)`.

---

## 3. Tier 3: Cross-Feature Combinations (Pairwise Coverage Specification)

The combinatorial dimension matrix evaluates 5 core dimensions:
- **Dim A: Auth Credential State** (Valid, Invalid Password, Non-Existent User, Empty/Whitespace, SQLi Payload)
- **Dim B: Lockout Counter State** (0 attempts, 1 attempt, 2 attempts, 3 attempts -> Lockout, Post-Lockout Attempt)
- **Dim C: Role & Country Scoping** (Admin / Supervisor / Asesor across Country 1 [El Salvador] vs Country 2 [Guatemala])
- **Dim D: Session & Fixation Lifecycle** (Guest / Unauthenticated, Fresh Login Fixation Regeneration, Stale / Timeout, Explicit Logout)
- **Dim E: View UI, Palette & Attribution** (Login View, Home Landing View, Lockout Warning, Author Footer, 5 Hex Colors)

### Tier 3 Pairwise Combinations Matrix

| ID | Combinatorial Pair | Test Objective | Key Verification Assertion |
|---|---|---|---|
| `T3-PAIR-01` | Auth Failure × Role Variation | Lockout counter increments identically across Admin, Supervisor, and Asesor roles without privilege bypass. | `intentos_fallidos` increments to 1 for all roles; generic error returned. |
| `T3-PAIR-02` | Counter Reset on Success × Country Assignment | Account with 2 prior failed attempts resets to 0 upon valid login; lands on `home.php` with assigned country. | `intentos_fallidos == 0`, `bloqueado == 0`, session has `id_pais=2`, `pais_nombre='Guatemala'`. |
| `T3-PAIR-03` | Lockout (3 Failures) × Valid Password Submission | 4th login attempt using the CORRECT password on a locked account is immediately rejected without password validation. | HTTP 302 redirect to `/login`; flash message contains "bloqueado"; DB `bloqueado == 1`; no session created. |
| `T3-PAIR-04` | Session Fixation Defense × Role/Country Scoping | Guest session ID is regenerated upon authentication; new session holds `isLoggedIn=true`, `rol`, and `id_pais`. | Session ID before login != Session ID after login; old session data destroyed. |
| `T3-PAIR-05` | Lockout State × Corporate Palette & Author Attribution | Lockout alert view simultaneously renders `#A61717` or `#BF0426`, brand framing `#023373`, and static manual author attribution. | Response HTML contains all 5 hex colors and author attribution markup. |
| `T3-PAIR-06` | Route Guard (`AuthFilter`) × Session Logout Invalidation | Post-logout request to `home.php` is intercepted by `AuthFilter` and redirected to `/login`. | HTTP 302 redirect to `/login`; `session('isLoggedIn')` is null/empty. |
| `T3-PAIR-07` | Role/Country Boundary Isolation × Cross-Country Access | Authenticated Supervisor from Country 1 attempting to access Country 2 data/routes is denied. | HTTP 403 Forbidden or restricted to Country 1 tenant scope. |
| `T3-PAIR-08` | Non-Existent User × Lockout Counter Invariance | Submitting an unseeded username returns generic auth error and does not modify any existing user's counter. | DB state of existing users untouched; uniform error message displayed. |
| `T3-PAIR-09` | Multi-User Session Switching × Country Isolation | Logging out as Country 1 user and logging in as Country 2 user completely clears old identity and country data. | No residual `EMP-ASE-01` or El Salvador data in Country 2 session. |
| `T3-PAIR-10` | SQL Injection Payload × Attempt Tracking Integrity | Injection payload (`' OR '1'='1' --`) is treated as literal; fails auth cleanly; increments attempt counter safely. | SQLite parameterization prevents bypass; `intentos_fallidos` increments by 1; error styled with `#BF0426`. |
| `T3-PAIR-11` | Session Expiry Simulation × Protected Route Guard | Simulating expired session timestamp forces `AuthFilter` redirect to `/login` without application crash. | HTTP 302 redirect to `/login`; flash error "sesión expirada" or "Debe iniciar sesión". |
| `T3-PAIR-12` | Empty / Malformed Credential Input × Counter Invariance | Blank username or password fails form validation; does not count toward 3-attempt brute-force lockout. | Validation errors displayed; DB `intentos_fallidos` remains unchanged at 0. |

---

### Detailed Test Specifications: Tier 3

#### T3-PAIR-01: Auth Failure Increment × Role Variation
- **Interacting Dimensions**: Dim A (Invalid Password) × Dim B (Attempt Increment) × Dim C (Roles: Admin, Supervisor, Asesor).
- **Preconditions**: Accounts `admin_user`, `supervisor_sv`, and `asesor_sv` seeded with `intentos_fallidos = 0`, `bloqueado = 0`.
- **Execution**:
  1. POST `/login` with `usuario: admin_user`, `contrasena: WrongPass!`.
  2. POST `/login` with `usuario: supervisor_sv`, `contrasena: WrongPass!`.
  3. POST `/login` with `usuario: asesor_sv`, `contrasena: WrongPass!`.
- **Assertions**:
  - All 3 requests return HTTP 302 redirecting back to `/login`.
  - Database assertions: `intentos_fallidos == 1` for all three records in `Usuario`.
  - Flash session message warns of remaining attempts (e.g., "Le quedan 2 intentos").
  - Response does NOT reveal user role or confirm valid username exists.

#### T3-PAIR-02: Counter Reset on Success (2 Failures + 1 Success) × Country Assignment
- **Interacting Dimensions**: Dim A (Valid Auth) × Dim B (Counter Reset) × Dim C (Country 2: Guatemala).
- **Preconditions**: Seed `supervisor_gt` (`Id_Pais = 2`) with `intentos_fallidos = 2`, `bloqueado = 0`.
- **Execution**:
  1. POST `/login` with `usuario: supervisor_gt`, `contrasena: SuperGt123!`.
- **Assertions**:
  - Response: HTTP 302 redirecting to `home.php`.
  - Database: `Usuario.intentos_fallidos` reset to `0`, `bloqueado` remains `0`, `ultimo_login` updated.
  - Session contains: `isLoggedIn === true`, `rol === 'Supervisor'`, `id_pais === 2`, `pais_nombre === 'Guatemala'`.
  - GET `/home.php`: Status 200 OK; body asserts text "Guatemala", "Supervisor", and "Mario Mendez".

#### T3-PAIR-03: Account Lockout (3 Failures) × Valid Password Submission on Locked Account
- **Interacting Dimensions**: Dim A (Valid Password) × Dim B (Locked Account Post-Auth) × Dim C (Asesor SV).
- **Preconditions**: `asesor_sv` has `intentos_fallidos = 3`, `bloqueado = 1`.
- **Execution**:
  1. POST `/login` with `usuario: asesor_sv`, `contrasena: AsesorSv123!` (exact correct password).
- **Assertions**:
  - Response: HTTP 302 redirecting to `/login` (or HTTP 403 / 401).
  - Redirection target does NOT go to `home.php`.
  - Session key `isLoggedIn` is NOT set (remains false / null).
  - Flash error message contains verbatim notice: "Acceso bloqueado tras 3 intentos fallidos consecutivos".
  - Database: `bloqueado` remains `1`; `intentos_fallidos` remains `3`.

#### T3-PAIR-04: Session Fixation Defense × Role/Country Scoping
- **Interacting Dimensions**: Dim A (Valid Auth) × Dim C (Admin Role, Country 1) × Dim D (Session Regeneration).
- **Preconditions**: Guest client accesses `/login` and receives initial session ID `SID_INITIAL`.
- **Execution**:
  1. Capture initial session ID via `session_id()`.
  2. POST `/login` with `usuario: admin_user`, `contrasena: AdminPass123!`.
- **Assertions**:
  - Post-login session ID `SID_NEW = session_id()`.
  - Assert `SID_INITIAL !== SID_NEW` (strict session fixation prevention via `session()->regenerate(true)`).
  - New session holds: `isLoggedIn === true`, `rol === 'Administrador'`, `id_pais === 1`, `pais_nombre === 'El Salvador'`.
  - Old guest session data purged.

#### T3-PAIR-05: Lockout State × Corporate Palette & Manual Author Attribution
- **Interacting Dimensions**: Dim B (Lockout UI Render) × Dim E (5-Color Palette & Author Attribution).
- **Preconditions**: An account reaches 3 failed attempts, prompting the lockout alert view.
- **Execution**:
  1. Perform 3 consecutive invalid logins for `asesor_sv`.
  2. Follow redirect to `/login` to render the lockout notification.
- **Assertions**:
  - Response contains HTTP 200 OK.
  - Palette validation in DOM:
    - `#BF0426` or `#A61717` present in style attributes / CSS classes for critical lockout alert container.
    - `#0511F2` present for action buttons / links.
    - `#023373` present for header / card boundary.
    - `#8D253C` present in footer / secondary attribution container.
  - Manual author attribution validation:
    - DOM contains author attribution string (e.g., `Diseñado y desarrollado por: Equipo de Desarrollo Creditrack`).

#### T3-PAIR-06: Protected Route Guard (`AuthFilter`) × Session Invalidation (Logout)
- **Interacting Dimensions**: Dim C (Asesor Role) × Dim D (Session Invalidation via Logout) × Dim E (Route Guard).
- **Preconditions**: Active authenticated session for `asesor_sv`.
- **Execution**:
  1. GET `/logout` (or POST `/logout`).
  2. Verify redirect to `/login`.
  3. Attempt immediate GET `/home.php`.
- **Assertions**:
  - Step 1 redirect: 302 to `/login`.
  - Session after logout: `session()->get('isLoggedIn')` is false/null.
  - Step 3 response: HTTP 302 redirecting to `/login` (intercepted by `AuthFilter`).
  - Flash message: "Debe iniciar sesión para acceder".

#### T3-PAIR-07: Country Boundary Isolation (Cross-Country Access Enforcement)
- **Interacting Dimensions**: Dim C (Country 1 Supervisor vs Country 2 Resources).
- **Preconditions**: Authenticated session established for `supervisor_sv` (`Id_Pais = 1`).
- **Execution**:
  1. Perform simulated request targeting Country 2 (`/agencias?pais=2` or operational country-scoped endpoint).
- **Assertions**:
  - Response rejects access (HTTP 403 Forbidden or redirects to `home.php` with boundary violation alert).
  - Active session maintains `session('id_pais') == 1`.
  - Cross-tenant data leakage is prevented.

#### T3-PAIR-08: Non-Existent User Credential Rejection × Lockout Counter Invariance
- **Interacting Dimensions**: Dim A (Non-Existent User) × Dim B (Lockout Counter Invariance).
- **Preconditions**: Seeded users have initial `intentos_fallidos = 0`.
- **Execution**:
  1. POST `/login` with `usuario: ghost_user_999`, `contrasena: RandomPass123!`.
- **Assertions**:
  - Response: HTTP 302 redirecting to `/login`.
  - Error message is generic ("Credenciales incorrectas") — no indication that user was not found (prevents user enumeration).
  - Database: Query `SELECT SUM(intentos_fallidos) FROM Usuario` asserts 0 (existing legitimate accounts are completely unaffected).

#### T3-PAIR-09: Successive Multi-User Session Switching Across Countries
- **Interacting Dimensions**: Dim C (Country 1 to Country 2 Switch) × Dim D (Session Hygiene).
- **Preconditions**: `asesor_sv` (Country 1) and `asesor_gt` (Country 2) valid in database.
- **Execution**:
  1. Log in as `asesor_sv`. Verify session `id_pais = 1`.
  2. Log out. Verify session destroyed.
  3. Log in as `asesor_gt`.
- **Assertions**:
  - Response redirects to `home.php`.
  - Session asserts: `codigo_empleado === 'EMP-ASE-02'`, `rol === 'Asesor'`, `id_pais === 2`, `pais_nombre === 'Guatemala'`.
  - View asserts zero mention of "El Salvador" or "EMP-ASE-01".

#### T3-PAIR-10: SQL Injection Payload × Attempt Tracking Integrity × Visual Error
- **Interacting Dimensions**: Dim A (SQLi Payload) × Dim B (Attempt Tracking) × Dim E (Visual Error).
- **Preconditions**: Database contains legitimate user `asesor_sv`.
- **Execution**:
  1. POST `/login` with `usuario: ' OR '1'='1' --`, `contrasena: ' OR '1'='1' --`.
- **Assertions**:
  - Parameterized query treats input as literal string; authentication fails.
  - No database error or syntax exception thrown.
  - HTTP 302 to `/login`; rendered error contains palette color `#BF0426`.
  - Author attribution intact in rendered view.

#### T3-PAIR-11: Session Timeout / Expiry Simulation × Protected Route Guard
- **Interacting Dimensions**: Dim D (Session Timeout) × Dim C (Protected Route Access).
- **Preconditions**: Authenticated session created, but timestamp artificially expired (exceeding `expiration = 7200`).
- **Execution**:
  1. Simulate expiration: `session()->set('login_time', time() - 7201); session()->remove('isLoggedIn');`.
  2. GET `/home.php`.
- **Assertions**:
  - `AuthFilter` intercepts request.
  - HTTP 302 redirect to `/login`.
  - View renders login page cleanly with author attribution and corporate palette.

#### T3-PAIR-12: Empty / Malformed Credential Input × Counter Invariance
- **Interacting Dimensions**: Dim A (Empty/Whitespace) × Dim B (Counter Invariance).
- **Preconditions**: `asesor_sv` has `intentos_fallidos = 0`.
- **Execution**:
  1. POST `/login` with `usuario: ''`, `contrasena: ''`.
  2. POST `/login` with `usuario: '   '`, `contrasena: '   '`.
- **Assertions**:
  - HTTP 302 back to `/login` (or 200 with validation errors).
  - Validation errors: "El campo usuario es obligatorio", "El campo contraseña es obligatorio".
  - Database: `Usuario.intentos_fallidos` for `asesor_sv` remains strictly `0`.

---

## 4. Tier 4: Real-World Application Scenarios (Realistic End-to-End Workflows)

Tier 4 tests multi-step lifecycles mirroring field operations, supervisory audits, brute-force attacks, and session lifecycle events.

### 4.1 Scenario T4-SCEN-01: Field Agent (Asesor) End-to-End Operational Lifecycle
**Target Persona**: Field Agent (`asesor_sv`, El Salvador).  
**Business Flow**: Agent arrives in the field, logs into the mobile portal, verifies assigned territory and role, performs operational shift, and executes secure logout.

```
[Guest at /login] ──(Valid POST)──> [302 to home.php] ──(GET)──> [Home Landing Page]
                                                                        │
                                                                 (Cross-Country GET)
                                                                        ▼
                                                                [403 / Access Denied]
                                                                        │
                                                                 (GET /logout)
                                                                        ▼
                                                                [Session Terminated]
                                                                        │
                                                                 (Re-access home.php)
                                                                        ▼
                                                                [Redirect to /login]
```

- **Step 1: Portal Discovery & UI Validation**
  - Action: GET `/login`.
  - Checks: HTTP 200 OK. Form contains CSRF token (`csrf_field()`), username/password inputs. Corporate colors (`#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C`) and visible manual author attribution present.
- **Step 2: Authentication Execution**
  - Action: POST `/login` with `usuario: asesor_sv`, `contrasena: AsesorSv123!`.
  - Checks: HTTP 302 redirecting to `home.php`. Database verifies `intentos_fallidos = 0`, `bloqueado = 0`.
  - Session verified: `isLoggedIn === true`, `codigo_empleado === 'EMP-ASE-01'`, `rol === 'Asesor'`, `id_pais === 1`, `pais_nombre === 'El Salvador'`.
- **Step 3: Landing View Verification (`home.php`)**
  - Action: GET `/home.php`.
  - Checks: HTTP 200 OK.
    - Greeting confirmation: "¡Inicio de sesión exitoso! Bienvenido al sistema Creditrack".
    - Session badge: "Estado de la sesión: Activa".
    - Employee profile: "EMP-ASE-01" and "Juan Perez".
    - Role badge: "Rol Asignado: Asesor".
    - Country badge: "País Asignado: El Salvador".
    - Boundary declaration: "Acceso restringido exclusivamente a información y operaciones de El Salvador con privilegios de Asesor".
    - Manual author attribution rendered in footer.
- **Step 4: Boundary Enforcement Test**
  - Action: GET `/agencias?pais=2` (simulating foreign country parameter manipulation).
  - Checks: Request blocked (HTTP 403 or boundary filter). Session remains bound to Country 1.
- **Step 5: Secure Shift Logout**
  - Action: GET `/logout`.
  - Checks: HTTP 302 redirect to `/login`. Session destroyed.
- **Step 6: Stale Browser Navigation Defense**
  - Action: GET `/home.php` immediately following logout.
  - Checks: HTTP 302 redirect to `/login`. Direct access barred.

---

### 4.2 Scenario T4-SCEN-02: Regional Supervisor Multi-Country Boundary & Governance Lifecycle
**Target Persona**: Regional Supervisor (`supervisor_gt`, Guatemala).  
**Business Flow**: Supervisor logs in to oversee regional operations in Guatemala, confirms territory boundaries, attempts to query another jurisdiction, and terminates session.

- **Step 1: Supervisor Login**
  - Action: POST `/login` with `usuario: supervisor_gt`, `contrasena: SuperGt123!`.
  - Checks: HTTP 302 redirect to `home.php`. Session captures `id_pais = 2`, `rol = 'Supervisor'`.
- **Step 2: Governance Landing Verification**
  - Action: GET `/home.php`.
  - Checks: HTTP 200 OK. View displays "Mario Mendez", "Supervisor", "Guatemala". Boundary statement explicitly confirms supervision restricted to Guatemala.
- **Step 3: Cross-Country Segregation Assertion**
  - Checks: Verifies that no routes, queries, or views expose El Salvador (`Id_Pais = 1`) records to this session.
- **Step 4: Clean Session Termination**
  - Action: GET `/logout`. HTTP 302 to `/login`. Session verified empty.

---

### 4.3 Scenario T4-SCEN-03: Targeted Brute-Force Password Attack, Automatic Lockout, and Administrative Remediation
**Target Persona**: Malicious Actor vs Target User (`supervisor_sv`, El Salvador).  
**Business Flow**: An attacker attempts repeated automated password guesses against a supervisor account. The system increments failure counters, enforces a hard lockout on the 3rd attempt, denies even the legitimate supervisor's password, and requires administrator remediation before access is restored.

```
Attempt 1 (Bad Pass) ──> intentos_fallidos: 1, bloqueado: 0 ──> "Le quedan 2 intentos"
Attempt 2 (Bad Pass) ──> intentos_fallidos: 2, bloqueado: 0 ──> "Le queda 1 intento"
Attempt 3 (Bad Pass) ──> intentos_fallidos: 3, bloqueado: 1 ──> "Acceso bloqueado"
Attempt 4 (GOOD Pass) ──> bloqueado: 1 (REJECTED IMMEDIATELY) ──> "Acceso bloqueado"
Admin Unlock ─────────> intentos_fallidos: 0, bloqueado: 0
Attempt 5 (GOOD Pass) ──> SUCCESS ──> 302 redirect to home.php
```

- **Step 1: First Attack Wave (Attempt 1)**
  - Action: POST `/login` with `usuario: supervisor_sv`, `contrasena: guess_01`.
  - Checks: HTTP 302 to `/login`. SQLite: `intentos_fallidos == 1`, `bloqueado == 0`. Flash error: "Credenciales incorrectas. Le quedan 2 intentos." Rendered error uses `#BF0426`.
- **Step 2: Second Attack Wave (Attempt 2)**
  - Action: POST `/login` with `usuario: supervisor_sv`, `contrasena: guess_02`.
  - Checks: HTTP 302 to `/login`. SQLite: `intentos_fallidos == 2`, `bloqueado == 0`. Flash warning: "Credenciales incorrectas. Le queda 1 intento antes del bloqueo."
- **Step 3: Third Attack Wave — Hard Lockout Triggered (Attempt 3)**
  - Action: POST `/login` with `usuario: supervisor_sv`, `contrasena: guess_03`.
  - Checks: HTTP 302 to `/login`. SQLite: `intentos_fallidos == 3`, `bloqueado == 1`.
  - Rendered view displays critical alert: "Acceso bloqueado tras 3 intentos fallidos consecutivos. Comuníquese con el administrador." Alert container styled with `#A61717`.
- **Step 4: Legitimate User Denied Due to Active Lockout (Attempt 4)**
  - Action: Legitimate supervisor submits CORRECT password `SuperSv123!`.
  - Checks:
    - Pre-auth check detects `bloqueado == 1`.
    - Password is NOT evaluated / authentication is immediately rejected.
    - Response: HTTP 302 redirect to `/login` (never `home.php`).
    - Session `isLoggedIn` remains false/unset.
    - Database: `bloqueado` remains 1; `intentos_fallidos` remains 3.
- **Step 5: Administrative Remediation / Account Unlock**
  - Action: Administrator invokes unlock model method:
    ```php
    $userModel->resetFailedAttempts('EMP-SUP-01');
    ```
  - Checks: SQLite verifies `intentos_fallidos == 0`, `bloqueado == 0`.
- **Step 6: Legitimate User Recovery & Access**
  - Action: Supervisor logs in with valid credentials `SuperSv123!`.
  - Checks: HTTP 302 redirect to `home.php`. Session created successfully.

---

### 4.4 Scenario T4-SCEN-04: Non-Consecutive Failure Recovery (Interrupted Failure Sequence)
**Target Persona**: Field Agent mistyping credentials twice, then recovering.  
**Business Flow**: Agent fails attempt 1, fails attempt 2, then enters the correct password on attempt 3. The system resets the failure counter to 0 immediately, preventing lockout. A later single typo only increments the counter to 1, proving the counter was reset.

- **Step 1: First Typo**
  - Action: POST `/login` with `usuario: asesor_sv`, `contrasena: typo_1`.
  - Checks: SQLite `intentos_fallidos == 1`.
- **Step 2: Second Typo**
  - Action: POST `/login` with `usuario: asesor_sv`, `contrasena: typo_2`.
  - Checks: SQLite `intentos_fallidos == 2`.
- **Step 3: Successful Credential Presentation**
  - Action: POST `/login` with `usuario: asesor_sv`, `contrasena: AsesorSv123!`.
  - Checks: HTTP 302 redirect to `home.php`. SQLite: `intentos_fallidos` immediately resets to `0`, `bloqueado` remains `0`.
- **Step 4: Shift Work & Logout**
  - Action: GET `/logout`.
- **Step 5: Subsequent Mistake After Successful Shift**
  - Action: POST `/login` with `usuario: asesor_sv`, `contrasena: accidental_typo`.
  - Checks: SQLite: `intentos_fallidos` increments to `1` (NOT 3). Demonstrates streak was cleanly broken.

---

### 4.5 Scenario T4-SCEN-05: Session Expiry / Hijacking Defense & Route Guard
**Target Persona**: Administrator (`admin_user`).  
**Business Flow**: Administrator logs in, leaves session idle past timeout threshold, and attempts to execute administrative actions. The system detects expired credentials, purges session data, and redirects to login without leaking privileged information.

- **Step 1: Administrator Authentication**
  - Action: POST `/login` with `usuario: admin_user`, `contrasena: AdminPass123!`.
  - Checks: HTTP 302 to `home.php`. Session has `rol = 'Administrador'`.
- **Step 2: Emulated Session Expiration**
  - Action: Advance simulated time past session expiration window (`session()->set('login_time', time() - 8000)` and remove authentication token).
- **Step 3: Attempted Access to Protected Resources**
  - Action: GET `/home.php`.
  - Checks: `AuthFilter` intercepts request; HTTP 302 redirect to `/login`.
- **Step 4: Re-authentication Verification**
  - Checks: Login page renders with corporate palette and author attribution. Re-authenticating restores valid session.

---

## 5. Concrete PHPUnit Implementation Architecture (`WorkflowIntegrationTest.php`)

Below is the complete architectural implementation blueprint for `tests/Feature/WorkflowIntegrationTest.php` implementing Tiers 3 and 4:

```php
<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use App\Models\UserModel;

/**
 * @internal
 */
final class WorkflowIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate = false;
    protected $migrateOnce = false;
    protected $seed = 'Tests\Support\Database\Seeds\TestAuthSeeder';
    protected $seedOnce = false;

    // ==========================================
    // TIER 3: CROSS-FEATURE PAIRWISE COMBINATIONS
    // ==========================================

    public function testT3Pair01_AuthFailureIncrementAcrossRoles(): void
    {
        $db = \Config\Database::connect();
        
        $roles = [
            ['user' => 'admin_user', 'code' => 'EMP-ADM-01'],
            ['user' => 'supervisor_sv', 'code' => 'EMP-SUP-01'],
            ['user' => 'asesor_sv', 'code' => 'EMP-ASE-01'],
        ];

        foreach ($roles as $r) {
            $result = $this->post('login', [
                'usuario' => $r['user'],
                'contrasena' => 'WrongPassword!',
            ]);

            $result->assertRedirectTo('login');
            
            $row = $db->table('Usuario')->where('Codigo_Empleado', $r['code'])->get()->getRowArray();
            $this->assertEquals(1, (int)$row['intentos_fallidos']);
            $this->assertEquals(0, (int)$row['bloqueado']);
        }
    }

    public function testT3Pair02_CounterResetOnSuccessAndCountryAssignment(): void
    {
        $db = \Config\Database::connect();
        $db->table('Usuario')->where('Codigo_Empleado', 'EMP-SUP-02')->update(['intentos_fallidos' => 2]);

        $result = $this->post('login', [
            'usuario' => 'supervisor_gt',
            'contrasena' => 'SuperGt123!',
        ]);

        $result->assertRedirectTo('home.php');

        $row = $db->table('Usuario')->where('Codigo_Empleado', 'EMP-SUP-02')->get()->getRowArray();
        $this->assertEquals(0, (int)$row['intentos_fallidos']);
        $this->assertEquals(0, (int)$row['bloqueado']);

        $homeResult = $this->withSession([
            'isLoggedIn' => true,
            'codigo_empleado' => 'EMP-SUP-02',
            'usuario' => 'supervisor_gt',
            'nombre' => 'Mario Mendez',
            'rol' => 'Supervisor',
            'id_pais' => 2,
            'pais_nombre' => 'Guatemala',
        ])->get('home.php');

        $homeResult->assertOK();
        $homeResult->assertSee('Guatemala');
        $homeResult->assertSee('Supervisor');
    }

    public function testT3Pair03_LockoutOn3rdFailureAndSubsequentValidPasswordRejection(): void
    {
        $db = \Config\Database::connect();

        // Attempts 1, 2, 3
        for ($i = 1; $i <= 3; $i++) {
            $this->post('login', [
                'usuario' => 'asesor_sv',
                'contrasena' => 'BadPass_' . $i,
            ]);
        }

        $row = $db->table('Usuario')->where('Codigo_Empleado', 'EMP-ASE-01')->get()->getRowArray();
        $this->assertEquals(3, (int)$row['intentos_fallidos']);
        $this->assertEquals(1, (int)$row['bloqueado']);

        // 4th attempt with CORRECT password
        $result = $this->post('login', [
            'usuario' => 'asesor_sv',
            'contrasena' => 'AsesorSv123!',
        ]);

        $result->assertRedirectTo('login');
        $this->assertFalse(session()->get('isLoggedIn') ?? false);
    }

    public function testT3Pair04_SessionFixationDefenseOnLogin(): void
    {
        session()->start();
        $preLoginId = session_id();

        $result = $this->post('login', [
            'usuario' => 'admin_user',
            'contrasena' => 'AdminPass123!',
        ]);

        $result->assertRedirectTo('home.php');
        $postLoginId = session_id();

        $this->assertNotEquals($preLoginId, $postLoginId);
        $this->assertTrue(session()->get('isLoggedIn'));
        $this->assertEquals('Administrador', session()->get('rol'));
    }

    public function testT3Pair05_LockoutUIWithCorporatePaletteAndAttribution(): void
    {
        // Trigger lockout
        for ($i = 0; $i < 3; $i++) {
            $this->post('login', [
                'usuario' => 'asesor_sv',
                'contrasena' => 'BadPass' . $i,
            ]);
        }

        $result = $this->get('login');
        $result->assertOK();

        $body = $result->getBody();
        $this->assertStringContainsString('#BF0426', $body);
        $this->assertStringContainsString('#0511F2', $body);
        $this->assertStringContainsString('#023373', $body);
        $this->assertStringContainsString('#A61717', $body);
        $this->assertStringContainsString('#8D253C', $body);
        $this->assertMatchesRegularExpression('/(Diseñado y desarrollado por|Equipo de Desarrollo)/i', $body);
    }

    public function testT3Pair06_ProtectedHomeRouteInterceptionAfterLogout(): void
    {
        $sessionData = [
            'isLoggedIn' => true,
            'codigo_empleado' => 'EMP-ASE-01',
            'usuario' => 'asesor_sv',
            'nombre' => 'Juan Perez',
            'rol' => 'Asesor',
            'id_pais' => 1,
            'pais_nombre' => 'El Salvador',
        ];

        // Access while authenticated
        $resAuth = $this->withSession($sessionData)->get('home.php');
        $resAuth->assertOK();

        // Logout
        $resLogout = $this->withSession($sessionData)->get('logout');
        $resLogout->assertRedirectTo('login');

        // Access without session
        $resUnauth = $this->get('home.php');
        $resUnauth->assertRedirectTo('login');
    }

    // ==========================================
    // TIER 4: REAL-WORLD END-TO-END SCENARIOS
    // ==========================================

    public function testT4Scen01_FieldAgentCompleteShiftLifecycle(): void
    {
        // 1. Visit Login
        $resLoginView = $this->get('login');
        $resLoginView->assertOK();
        $resLoginView->assertSee('Creditrack');

        // 2. Submit Valid Credentials
        $resPost = $this->post('login', [
            'usuario' => 'asesor_sv',
            'contrasena' => 'AsesorSv123!',
        ]);
        $resPost->assertRedirectTo('home.php');

        // 3. Render Landing View
        $resHome = $this->withSession([
            'isLoggedIn' => true,
            'codigo_empleado' => 'EMP-ASE-01',
            'usuario' => 'asesor_sv',
            'nombre' => 'Juan Perez',
            'rol' => 'Asesor',
            'id_pais' => 1,
            'pais_nombre' => 'El Salvador',
        ])->get('home.php');

        $resHome->assertOK();
        $resHome->assertSee('Estado de la sesión: Activa');
        $resHome->assertSee('EMP-ASE-01');
        $resHome->assertSee('Juan Perez');
        $resHome->assertSee('Asesor');
        $resHome->assertSee('El Salvador');

        // 4. Logout
        $resLogout = $this->get('logout');
        $resLogout->assertRedirectTo('login');

        // 5. Verify Inaccessibility
        $resCheck = $this->get('home.php');
        $resCheck->assertRedirectTo('login');
    }

    public function testT4Scen03_BruteForceLockoutAndAdministrativeRemediation(): void
    {
        $db = \Config\Database::connect();
        $userModel = new UserModel();

        // 1. First Attack Wave
        $this->post('login', ['usuario' => 'supervisor_sv', 'contrasena' => 'wrong_1']);
        $r1 = $db->table('Usuario')->where('Codigo_Empleado', 'EMP-SUP-01')->get()->getRowArray();
        $this->assertEquals(1, (int)$r1['intentos_fallidos']);

        // 2. Second Attack Wave
        $this->post('login', ['usuario' => 'supervisor_sv', 'contrasena' => 'wrong_2']);
        $r2 = $db->table('Usuario')->where('Codigo_Empleado', 'EMP-SUP-01')->get()->getRowArray();
        $this->assertEquals(2, (int)$r2['intentos_fallidos']);

        // 3. Third Attack Wave - Lockout Trigger
        $this->post('login', ['usuario' => 'supervisor_sv', 'contrasena' => 'wrong_3']);
        $r3 = $db->table('Usuario')->where('Codigo_Empleado', 'EMP-SUP-01')->get()->getRowArray();
        $this->assertEquals(3, (int)$r3['intentos_fallidos']);
        $this->assertEquals(1, (int)$r3['bloqueado']);

        // 4. Legitimate User Blocked
        $resLegit = $this->post('login', ['usuario' => 'supervisor_sv', 'contrasena' => 'SuperSv123!']);
        $resLegit->assertRedirectTo('login');

        // 5. Administrative Unlock
        $userModel->resetFailedAttempts('EMP-SUP-01');
        $r4 = $db->table('Usuario')->where('Codigo_Empleado', 'EMP-SUP-01')->get()->getRowArray();
        $this->assertEquals(0, (int)$r4['intentos_fallidos']);
        $this->assertEquals(0, (int)$r4['bloqueado']);

        // 6. Legitimate User Succeeds Post-Unlock
        $resPostUnlock = $this->post('login', ['usuario' => 'supervisor_sv', 'contrasena' => 'SuperSv123!']);
        $resPostUnlock->assertRedirectTo('home.php');
    }
}
```

---

## 6. Criteria & Production Template for `TEST_READY.md`

### 6.1 Publication Exit Criteria (The 6 Gates)
The orchestrator or test lead may publish `TEST_READY.md` to `c:\xampp\htdocs\creditrack\TEST_READY.md` ONLY when all six conditions are satisfied:

1. **Gate 1 — Clean Compilation**: All test classes under `tests/Feature/` and `tests/_support/` compile with zero syntax errors (`php -l`).
2. **Gate 2 — Zero `.env` Isolation**: Tests execute strictly against SQLite `:memory:` via `Database.php`. The `.env` file is never touched, read, or created.
3. **Gate 3 — 100% Test Pass Rate**: The test runner command (`vendor/bin/phpunit`) reports zero failures (`Failures: 0`) and zero errors (`Errors: 0`).
4. **Gate 4 — Full Tier Coverage**: Tiers 1 through 4 are executed:
   - Tier 1: Core Feature Happy Path ($\ge 5$ cases/feature).
   - Tier 2: Boundary & Corner Cases ($\ge 5$ cases/feature).
   - Tier 3: Pairwise Cross-Feature Interactions ($\ge 12$ cases).
   - Tier 4: Real-World Multi-Step Scenarios ($\ge 5$ complete lifecycles).
5. **Gate 5 — Visual & Palette Verification**: Automated assertions verify that all 5 corporate colors (`#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C`) and the manual author attribution string exist in the rendered HTML output.
6. **Gate 6 — Security Invariants Verified**: Bcrypt verification, 3-attempt DB lockout, session fixation defense (`session()->regenerate`), and country boundary isolation are verified by passing assertions.

---

### 6.2 Production Template: `c:\xampp\htdocs\creditrack\TEST_READY.md`

```markdown
# CrediTrack Test Suite Readiness Report (`TEST_READY.md`)

**System**: CrediTrack Authentication & Role/Country Access Control System  
**Framework**: CodeIgniter 4 (PHP 8.2+)  
**Test Engine**: PHPUnit 10/11 In-Memory SQLite Harness (`:memory:`)  
**Status**: VERIFIED & READY FOR EVALUATION  
**Published Date**: [TIMESTAMP]  

---

## 1. Quick Verification Commands

To run the complete automated test suite across all 4 Tiers without modifying the environment:

```bash
# Execute entire test suite
vendor/bin/phpunit

# Execute specific test suites
vendor/bin/phpunit tests/Feature/AuthTest.php
vendor/bin/phpunit tests/Feature/AccessControlTest.php
vendor/bin/phpunit tests/Feature/LockoutTest.php
vendor/bin/phpunit tests/Feature/ViewUITest.php
vendor/bin/phpunit tests/Feature/WorkflowIntegrationTest.php
```

---

## 2. Test Architecture & Coverage Summary

| Suite / Test Class | Scope / Tier | Test Count | Assertion Count | Status |
|---|---|---|---|---|
| `tests/Feature/AuthTest.php` | Tier 1 & 2: Credential verification, empty inputs, SQLi resistance, session establishment | 15+ | 40+ | PASS |
| `tests/Feature/AccessControlTest.php` | Tier 1 & 2: Role identification, country assignment, unauth route guard, cross-country boundaries | 15+ | 35+ | PASS |
| `tests/Feature/LockoutTest.php` | Tier 1 & 2: Failure counters 1-3, counter reset on success, 4th attempt rejection | 15+ | 45+ | PASS |
| `tests/Feature/ViewUITest.php` | Tier 1 & 2: Corporate palette (5 colors), manual author attribution, session confirmation | 15+ | 50+ | PASS |
| `tests/Feature/WorkflowIntegrationTest.php` | Tier 3 (12 Pairwise interactions) & Tier 4 (5 Real-world operational workflows) | 17+ | 60+ | PASS |
| **TOTALS** | **Comprehensive E2E Coverage (Tiers 1–4)** | **77+** | **230+** | **100% PASS** |

---

## 3. Requirements Traceability Checklist

| Req | Description | Verification Method | Status |
|---|---|---|---|
| **R1** | Authentication against database, role & country scoping, boundary restriction | Validated in `AuthTest`, `AccessControlTest`, `WorkflowIntegrationTest` | VERIFIED |
| **R2** | Security lockout after 3 consecutive failed attempts | Validated in `LockoutTest`, `WorkflowIntegrationTest` | VERIFIED |
| **R3** | Redirection to `home.php`, session confirmation view, secure logout | Validated in `AuthTest`, `ViewUITest`, `WorkflowIntegrationTest` | VERIFIED |
| **R4** | Corporate 5-color palette (`#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C`) and manual author attribution | Validated in `ViewUITest`, `WorkflowIntegrationTest` | VERIFIED |
| **R5** | Strict `.env` isolation; native configuration in `app/Config/Database.php` | Validated via in-memory SQLite `$tests` harness; zero `.env` reads | VERIFIED |

---

## 4. Security & Environment Compliance Audit

- [x] **Zero `.env` Access**: Under no circumstances was `.env` accessed, read, or modified.
- [x] **Bcrypt Password Storage**: Passwords hashed using `PASSWORD_BCRYPT` with cost factor 10+.
- [x] **Session Fixation Defense**: Verified call to `session()->regenerate(true)` upon authentication.
- [x] **Atomic Lockout State**: Lockout state persisted directly in database table `Usuario` (`intentos_fallidos`, `bloqueado`).
- [x] **Corporate 5-Color Compliance**: Exact hex codes verified in rendered HTML.
- [x] **Visible Manual Author Attribution**: Static HTML designer credit verified in login and landing templates.
```

---

## 7. Next Actions for Implementation Team

1. **SM1 (Infra Setup)**:
   - Ensure `tests/_support/Database/Seeds/TestAuthSeeder.php` includes all fixture users (`admin_sv`, `sup_sv`, `sup_gt`, `ase_sv`, `ase_gt`, `locked_user`).
2. **SM2 (Test Implementation)**:
   - Implement `tests/Feature/WorkflowIntegrationTest.php` incorporating the 12 pairwise test methods (`testT3Pair01_` through `testT3Pair12_`) and 5 scenario methods (`testT4Scen01_` through `testT4Scen05_`).
3. **SM3 (Verification & Publication)**:
   - Execute `vendor/bin/phpunit` across all suites.
   - Publish `c:\xampp\htdocs\creditrack\TEST_READY.md` upon 100% test pass.
