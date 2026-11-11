# CrediTrack Codebase Architecture Survey Report (Phase 0)

**Date**: 2026-11-10  
**Author**: Explorer 1 (Codebase Architecture Explorer)  
**Target Repository**: `c:\xampp\htdocs\creditrack`  
**Working State Directory**: `c:\xampp\htdocs\creditrack\.agents\teamwork\explorer_survey_1`

---

## 1. Executive Summary

The CrediTrack application is built on top of **CodeIgniter 4** (`codeigniter4/framework`, requiring PHP `^8.2`), adhering to a standard MVC architecture.
Currently, the codebase is in a clean baseline state:
- Framework structure is in place, dependencies are installed in `vendor/`.
- Only a single default route (`/` -> `Home::index`) is configured in `app/Config/Routes.php`.
- Auto-routing is explicitly disabled (`Routing.php:$autoRoute = false`).
- A placeholder empty view `app/Views/login.php` (0 bytes) exists.
- The default database configuration in `app/Config/Database.php` targets `MySQLi` on `localhost:3306`, but database name, user, and password are currently empty strings `""`.
- An SQL schema dump `Base_Datos.sql` exists in the repository root containing 12 tables, including `Usuario` and `Pais`, written in PostgreSQL-flavoured DDL.
- Automated tests are configured via `phpunit.dist.xml` with an in-memory SQLite database (`:memory:`) configured under the `'tests'` database group.
- **Strict Compliance Notice**: `.env` was strictly NOT accessed, read, or modified in accordance with Requirement R5.

---

## 2. Codebase & Directory Structure

| Directory / File | Description & Current State |
|---|---|
| `app/Config/` | Application configuration classes (`App.php`, `Database.php`, `Routes.php`, `Routing.php`, `Session.php`, `Security.php`, `Filters.php`, etc.). |
| `app/Controllers/` | Controller layer. Contains `BaseController.php` and `Home.php`. No auth or user controllers exist yet. |
| `app/Database/` | Database migrations and seeds (`Migrations/` and `Seeds/` currently contain only `.gitkeep`). |
| `app/Filters/` | HTTP request/response filters. Contains only `.gitkeep`. No authentication or role/country guard filters exist yet. |
| `app/Models/` | Model layer. Contains only `.gitkeep`. No user or business models exist yet. |
| `app/Views/` | View templates. Contains `welcome_message.php` and an empty file `login.php` (0 bytes). No `home.php` view exists. |
| `public/` | Public web root. Contains `index.php` (front controller), `.htaccess`, `favicon.ico`, `robots.txt`. No custom CSS/JS assets yet. |
| `system/` | CodeIgniter 4 core system codebase. |
| `tests/` | PHPUnit test suite. Contains `phpunit.dist.xml`, `tests/_support/`, `tests/unit/HealthTest.php`. |
| `vendor/` | Composer vendor packages and autoloader. |
| `writable/` | Runtime cache, logs, session data (`writable/session`), debug bar. |
| `Base_Datos.sql` | SQL schema script defining 12 tables (4,072 bytes). |
| `composer.json` | Project package manifest (`codeigniter4/framework`, PHP `^8.2`, `phpunit/phpunit`). |
| `spark` | CodeIgniter 4 CLI executable. |
| `phpunit.dist.xml` | PHPUnit 10/11 configuration file. |

---

## 3. Routing Architecture

- **Primary Route File**: `app/Config/Routes.php`
  - Current configuration:
    ```php
    $routes->get('/', 'Home::index');
    ```
- **Auto-Routing Setting**: `app/Config/Routing.php:97`
  - `public bool $autoRoute = false;`
  - **Implication**: Any endpoint (e.g. `/login`, `/auth/process`, `/logout`, `/home.php`, `/home`) must be explicitly declared in `app/Config/Routes.php`.

---

## 4. Authentication, Session & User Model Findings

### 4.1 Authentication Status
- There is currently **no existing authentication controller, model, or helper**.
- `app/Controllers/Home.php` only renders the default CI4 `welcome_message` view.
- `app/Views/login.php` exists as a 0-byte placeholder.

### 4.2 Session Management
- **Configuration File**: `app/Config/Session.php`
  - Driver: `CodeIgniter\Session\Handlers\FileHandler::class` (Line 25)
  - Cookie Name: `'ci_session'` (Line 34)
  - Expiration: `7200` seconds (2 hours) (Line 44)
  - Save Path: `WRITEPATH . 'session'` (Line 61)
- CodeIgniter provides `session()` helper and `service('session')` for storing and retrieving session variables (e.g., `isLoggedIn`, `user_id`, `username`, `rol`, `id_pais`, `pais_nombre`).

### 4.3 User Model & Role/Country Access
- `app/Models/` is empty. A model extending `CodeIgniter\Model` (e.g., `UserModel`) must be implemented to query credentials, role, and country association.
- `Base_Datos.sql` defines the user table as:
  ```sql
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
- Related country table:
  ```sql
  CREATE TABLE Pais (
      Id_pais SERIAL PRIMARY KEY,
      Pais VARCHAR(100) NOT NULL
  );
  ```

---

## 5. Database Connection Mechanism & Critical Constraints

### 5.1 Critical Constraint R5 (.env Handling)
- **Constraint**: Strict prohibition against reading, touching, accessing, or modifying `.env`.
- **Database Configuration File**: `app/Config/Database.php`
  - The `$default` array (lines 27-52) has:
    - Driver: `'MySQLi'`
    - Hostname: `'localhost'`
    - Port: `3306`
    - Database, Username, Password: empty strings `""`
  - In CodeIgniter 4, database settings can be defined directly in `app/Config/Database.php` without relying on `.env`.
  - In testing environments (`ENVIRONMENT === 'testing'`), `Database.php:200-202` automatically switches `$defaultGroup` to `'tests'`, which is configured with SQLite3 in-memory (`:memory:`).

### 5.2 Schema Analysis (`Base_Datos.sql`)
- Contains 12 relational tables:
  1. `Pais`
  2. `Marca`
  3. `Departamento`
  4. `Municipio`
  5. `Distrito`
  6. `Agencias`
  7. `Usuario` (contains `Codigo_Empleado`, `Nombre`, `Usuario`, `Contrasena`, `Id_Pais`, `telefono`, `rol`)
  8. `Notificaciones`
  9. `Ruta`
  10. `Ubicaciones_Ruta`
  11. `Asignacion`
  12. `Tareas`
- **Dialect Consideration**: `Base_Datos.sql` uses PostgreSQL keywords (`SERIAL`, `point`, `gen_random_uuid()`). For MySQL/MariaDB and SQLite testing, appropriate DDL / migrations or seeders must map `SERIAL` to `AUTO_INCREMENT` / `INTEGER PRIMARY KEY`.

---

## 6. Requirements Mapping & Gap Analysis

| Requirement | Current Status | Required Implementation |
|---|---|---|
| **R1. Authentication & Role/Country Access Control** | Not implemented. | `UserModel`, `AuthController` (or `Auth.php`), login validation against `Usuario` + `Pais`, session payload holding `rol` and `id_pais` / `pais_nombre`, access control filter/guard. |
| **R2. Lockout on 3 Failed Attempts** | Not implemented. | Tracking consecutive failed login attempts (per user/session/database counter), locking further attempts after 3 failures with informative error message. |
| **R3. Redirection to `home.php`** | Not implemented. Empty / missing view. | Route for `home.php` (and/or direct `/home.php` redirect), controller handling `home.php`, view template displaying login confirmation, user identity, role, and country data. |
| **R4. UI Palette & Designer Attribution** | `app/Views/login.php` is empty (0 bytes). | Responsive login view implementing hex palette: `#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C`. Visible manual attribution of system designers. |
| **R5. No `.env` Modification** | Fully respected during survey. | DB config managed strictly via `app/Config/Database.php` / programmatic test config. Zero `.env` interaction. |

---

## 7. Recommendations for Implementation Phases

1. **Database & Seeding Layer**:
   - Create migrations or programmatic schema setup in `app/Database/Migrations` or database seeders in `app/Database/Seeds` for `Pais` and `Usuario` tables that work across MySQL and SQLite3 testing.
2. **Model Layer**:
   - Implement `UserModel.php` in `app/Models/` with methods to find user by username, join with `Pais`, update failed login attempt counters/lockout timestamps, and verify password hashes.
3. **Controller & Filter Layer**:
   - Create `AuthController.php` (login form, authenticate POST, logout).
   - Create `Home.php` (or adapt existing) handling `home.php` route with role and country view rendering.
   - Implement `AuthFilter.php` in `app/Filters/` and register in `app/Config/Filters.php` to prevent unauthenticated access.
4. **View Layer**:
   - Build `app/Views/login.php` using the 5 specified colors (`#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C`) and prominent manual designer attribution.
   - Build `app/Views/home.php` confirming authenticated state, user name, role, country, and security constraints.
5. **Testing & Verification**:
   - Add automated test cases in `tests/` covering:
     - Successful login and session state.
     - Invalid credentials rejection.
     - Account lockout after 3 consecutive failed attempts.
     - Role and country access control checks.
