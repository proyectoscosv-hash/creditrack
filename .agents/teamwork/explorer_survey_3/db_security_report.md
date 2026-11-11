# Database & Security Architecture Report — CrediTrack System

**Phase**: Phase 0 — Survey & Technical Investigation  
**Author**: Explorer 3 (Database & Security Explorer)  
**Target Repository**: `c:\xampp\htdocs\creditrack`  
**Working State Directory**: `c:\xampp\htdocs\creditrack\.agents\teamwork\explorer_survey_3`  
**Date**: 2026-11-10  
**Status**: Authoritative Reference Report  

---

## 1. Executive Summary

This investigation delivers a comprehensive architectural assessment of database connectivity, schema modeling, and security controls for the CrediTrack system, specifically resolving requirements **R1** (Authentication & Role/Country Access Control), **R2** (Account Lockout after 3 Consecutive Failed Attempts), and **R5** (Strict Prohibition against accessing or modifying `.env`).

### Key Discoveries & Architectural Determinations
1. **Database Service in Environment**:
   - The runtime environment is a Windows XAMPP installation (`C:\xampp\`).
   - The local database daemon is **MariaDB 10.4.32** running on `127.0.0.1:3306` with default user `root` and password `""` (verified via `C:\xampp\mysql\bin\my.ini` and `C:\xampp\phpMyAdmin\config.inc.php`).
2. **Schema Incompatibility in `Base_Datos.sql`**:
   - The existing `Base_Datos.sql` script (111 lines, 12 tables) was written using PostgreSQL syntax (`SERIAL PRIMARY KEY`, `gen_random_uuid()`, `point`).
   - In MySQL/MariaDB, `SERIAL` expands to `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE`. Because foreign key columns like `Departamento.Id_pais` and `Usuario.Id_Pais` are declared as `INT NOT NULL`, MySQL/MariaDB rejects table creation with `errno 150` (Foreign Key type mismatch).
   - For proper operation in MariaDB/MySQL and SQLite test environments, clean DDL using compatible integer types (`INT AUTO_INCREMENT PRIMARY KEY`) must be provided.
3. **Database Connectivity Without `.env` (R5 Compliance)**:
   - CodeIgniter 4 natively prioritizes properties in `app/Config/Database.php`. The `.env` file is purely an optional environment overlay.
   - By declaring connection parameters directly in `Config\Database::$default` (`'hostname' => 'localhost'`, `'username' => 'root'`, `'password' => ''`, `'database' => 'creditrack'`, `'DBDriver' => 'MySQLi'`), the application achieves 100% full operational functionality while strictly observing the hard constraint to **never touch, read, or modify `.env`**.
   - Automated testing runs against `:memory:` SQLite3 via `Config\Database::$tests` without any external dependency.
4. **Security & Consecutive Failed Login Tracking (R2 Compliance)**:
   - Consecutive failed attempts must be tracked in the database (columns `intentos_fallidos` and `bloqueado` in `Usuario`) rather than purely in browser cookies or PHP sessions. Tracking in the database prevents attackers from resetting their failure count by deleting cookies, rotating sessions, or switching to incognito windows.
   - Upon entering the 3rd consecutive incorrect password, the account is locked (`bloqueado = 1`).
   - Success resets the counter to 0 immediately. A locked account blocks all subsequent attempts without evaluating passwords.
5. **Role & Country Access Control (R1 Compliance)**:
   - Data partitioning is structured hierarchically by country (`Id_Pais`).
   - User identity, role, and country association (`id_pais`, `pais_nombre`) are captured in the session payload during authentication and guarded by an `AuthFilter` on protected routes.

---

## 2. Database Environment & Setup Investigation

### 2.1 Environmental Infrastructure
The system environment was inspected to determine available database software and configurations without executing any external or unmanaged scripts:

| Component | Path / Location | Configuration / Value |
|---|---|---|
| **DBMS** | MariaDB 10.4.32 | Process active on port 3306 |
| **Config File** | `C:\xampp\mysql\bin\my.ini` | `port = 3306`, `socket = "C:/xampp/mysql/mysql.sock"`, `character-set-server = utf8mb4`, `collation-server = utf8mb4_general_ci` |
| **Default User** | `root` | Standard local XAMPP root account |
| **Default Password** | `""` (empty) | As defined in `C:\xampp\phpMyAdmin\config.inc.php:21` |
| **Default Host** | `127.0.0.1` / `localhost` | Local loopback interface |
| **CI4 Connection Config** | `app/Config/Database.php` | Configures `$default` (MySQLi) and `$tests` (SQLite3) |

### 2.2 Analysis of Existing Schema (`Base_Datos.sql`)
The repository contains `Base_Datos.sql` in the root directory. It defines 12 relational entities:

```
[Pais] (1) ──< [Departamento] (1) ──< [Municipio] (1) ──< [Distrito] (1) ──< [Agencias] (M)
  │                                                                               │
  └──< [Usuario] (1) ──< [Notificaciones]                                         │
          │                                                                       │
          └──< [Asignacion] (M) ──< [Tareas] >── [Agencias]                      │
                   │                                                              │
                   └──> [Ruta] (1) ──< [Ubicaciones_Ruta]                         │
```

#### Detailed Table Definitions
1. **`Pais`**: Master country catalogue.
   - `Id_pais SERIAL PRIMARY KEY`
   - `Pais VARCHAR(100) NOT NULL`
2. **`Usuario`**: Master user credentials, roles, and geographical affiliation.
   - `Codigo_Empleado VARCHAR(50) PRIMARY KEY`
   - `Nombre VARCHAR(150) NOT NULL`
   - `Usuario VARCHAR(50) UNIQUE NOT NULL`
   - `Contrasena VARCHAR(255) NOT NULL` (suitable for bcrypt/argon2)
   - `Id_Pais INT NOT NULL REFERENCES Pais(Id_pais)`
   - `telefono VARCHAR(20)`
   - `rol VARCHAR(50) NOT NULL`
3. **Territorial Hierarchy**: `Departamento`, `Municipio`, `Distrito`.
4. **Operations & Tracking**: `Marca`, `Agencias`, `Notificaciones`, `Ruta`, `Ubicaciones_Ruta`, `Asignacion`, `Tareas`.

### 2.3 Critical Dialect & Data Type Inconsistencies
Direct execution of `Base_Datos.sql` in MySQL/MariaDB fails due to PostgreSQL-specific idioms:
1. **`SERIAL` vs `INT` FK Mismatch**:
   - In PostgreSQL, `SERIAL` is an alias for `INTEGER` with a sequence.
   - In MySQL/MariaDB, `SERIAL` is an alias for `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE`.
   - When table `Usuario` defines `Id_Pais INT NOT NULL` referencing `Pais(Id_pais)`, MariaDB refuses to create the foreign key because `BIGINT UNSIGNED` $\neq$ `INT SIGNED`.
   - **Resolution**: Use `INT AUTO_INCREMENT PRIMARY KEY` for PKs, matching `INT NOT NULL` in referencing foreign keys.
2. **`point` data type**:
   - In MySQL/MariaDB, spatial types must be declared as `POINT` (or `DECIMAL(10,8)` for coordinates).
3. **`UUID DEFAULT gen_random_uuid()`**:
   - `gen_random_uuid()` is Postgres-only. In MySQL/MariaDB, use `VARCHAR(36)` with PHP-generated UUID or `UUID()`.

---

## 3. Database Connection Architecture Without `.env` (Requirement R5)

### 3.1 Strict Prohibition Constraint
- **Rule**: The team has an absolute prohibition against accessing, reading, opening, modifying, or querying the `.env` file under any circumstances.
- **Verification Guarantee**: Any file operation touching `.env` triggers an immediate integrity failure.

### 3.2 How CodeIgniter 4 Connects Without `.env`
In CodeIgniter 4, the configuration files inside `app/Config/` are valid PHP classes that extend `CodeIgniter\Config\BaseConfig` or `CodeIgniter\Database\Config`.
1. The framework instantiates `Config\Database`.
2. If `.env` is absent or untouched, CI4 simply reads the public class properties defined in `app/Config/Database.php`.
3. The `$default` property defines the production / standard database group:

```php
namespace Config;

use CodeIgniter\Database\Config;

class Database extends Config
{
    public string $filesPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR;
    public string $defaultGroup = 'default';

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
        'swapPre'      => '',
        'encrypt'      => false,
        'compress'     => false,
        'strictOn'     => false,
        'failover'     => [],
        'port'         => 3306,
        'numberNative' => false,
        'foundRows'    => false,
        'dateFormat'   => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];
```

### 3.3 Automated Test Environment (`$tests`)
When executing automated tests via `phpunit`, CI4's bootstrap sets `ENVIRONMENT = 'testing'`. In `app/Config/Database.php:200-202`:
```php
if (ENVIRONMENT === 'testing') {
    $this->defaultGroup = 'tests';
}
```
The `$tests` connection group is pre-configured with SQLite3:
```php
public array $tests = [
    'DSN'         => '',
    'hostname'    => '127.0.0.1',
    'username'    => '',
    'password'    => '',
    'database'    => ':memory:',
    'DBDriver'    => 'SQLite3',
    'DBPrefix'    => 'db_',
    'pConnect'    => false,
    'DBDebug'     => true,
    'charset'     => 'utf8',
    'port'        => 3306,
    'foreignKeys' => true,
];
```
This guarantees that unit and feature tests execute lightning-fast entirely in memory without requiring MySQL daemon restarts, external network calls, or `.env` access.

---

## 4. Security Requirements & Threat Modeling

### 4.1 Requirement R2: Account Lockout after 3 Consecutive Failed Attempts

#### Evaluation of Tracking Mechanisms

| Mechanism | Resistance to Cookie Clearing | Resistance to Session Tampering | Multi-Device Persistence | Implementation Complexity | Recommendation |
|---|---|---|---|---|---|
| **Session-Only (`$_SESSION`)** | ❌ Fails (user clears cookies to reset count) | ⚠️ Low (stateless attackers bypass easily) | ❌ No | Low | **Rejected** (Insecure) |
| **Client IP Table (`login_attempts`)** | ✅ High | ✅ High | ⚠️ May block multiple legitimate users behind NAT/proxy | Medium | Recommended for rate limiting |
| **User Account DB Columns (`Usuario`)** | ✅ **100% Immune** | ✅ **100% Immune** | ✅ **Global account lock** | Low / Clean | **Recommended as Primary Lockout** |

#### Recommended Schema Extension on `Usuario`
To guarantee strict compliance with R2 across all attack vectors (private browsing, cookie clearing, cURL scripts), the `Usuario` table must include:
- `intentos_fallidos`: `INT NOT NULL DEFAULT 0`
- `bloqueado`: `TINYINT(1) NOT NULL DEFAULT 0` (0 = Activo, 1 = Bloqueado)
- `ultimo_fallo`: `DATETIME NULL`

#### State Machine & Transition Rules

```
                      ┌──────────────────────────────────────────┐
                      │                 [Active]                 │
                      │   (intentos_fallidos = 0, bloqueado = 0) │
                      └─────────────────────┬────────────────────┘
                                            │
                          Failed Attempt 1  │  Correct Credentials
                                            ▼  (Resets to 0)
                      ┌──────────────────────────────────────────┐
                      │          [Failed 1 Warning]              │
                      │   (intentos_fallidos = 1, bloqueado = 0) │
                      │  "Credenciales incorrectas. Quedan 2"   │
                      └─────────────────────┬────────────────────┘
                                            │
                          Failed Attempt 2  │  Correct Credentials
                                            ▼  (Resets to 0)
                      ┌──────────────────────────────────────────┐
                      │          [Failed 2 Warning]              │
                      │   (intentos_fallidos = 2, bloqueado = 0) │
                      │  "Credenciales incorrectas. Queda 1"    │
                      └─────────────────────┬────────────────────┘
                                            │
                          Failed Attempt 3  │
                                            ▼
                      ┌──────────────────────────────────────────┐
                      │               [LOCKED]                   │
                      │   (intentos_fallidos = 3, bloqueado = 1) │
                      │  "Acceso bloqueado tras 3 intentos..."   │
                      └─────────────────────┬────────────────────┘
                                            │
                          Any Future Attempt│ (Even with correct password)
                                            ▼
                               [Immediate Rejection]
```

#### Detailed Transition Specifications
1. **Pre-Authentication Lock Check**:
   - Before evaluating `password_verify()`, query `Usuario WHERE Usuario = :usuario:`.
   - If `bloqueado == 1` or `intentos_fallidos >= 3`:
     - Abort authentication immediately.
     - Return error message: *"Acceso bloqueado tras 3 intentos fallidos consecutivos de inicio de sesión erróneos. Contacte al administrador."*
     - Do NOT verify password (avoids timing leaks and unnecessary crypto work).
2. **Incorrect Password Evaluation**:
   - If password verification fails:
     - Increment: `intentos_fallidos = intentos_fallidos + 1`.
     - Update: `ultimo_fallo = date('Y-m-d H:i:s')`.
     - If updated `intentos_fallidos >= 3`:
       - Set `bloqueado = 1`.
       - Return error message: *"Acceso bloqueado tras 3 intentos fallidos consecutivos de inicio de sesión erróneos."*
     - Else:
       - Remaining: `$restantes = 3 - $intentos_fallidos`.
       - Return error message: *"Credenciales incorrectas. Intentos fallidos: {$intentos_fallidos} de 3."*
3. **Success Reset Rule**:
   - If password verification succeeds:
     - Check `bloqueado == 0`.
     - Reset: `intentos_fallidos = 0`, `bloqueado = 0`.
     - Update: `ultimo_login = date('Y-m-d H:i:s')`.
     - Establish session and redirect to `home.php`.
4. **Unknown Username Handling (Anti-Enumeration)**:
   - If user does not exist in `Usuario`:
     - Perform a dummy `password_verify` computation to equalize response time and prevent timing analysis.
     - Return generic message: *"Credenciales incorrectas."*

---

### 4.2 Requirement R1: Session Management & Access Control

#### 4.2.1 Session Security Configuration
In `app/Config/Session.php`:
- `driver`: `FileHandler::class`
- `savePath`: `WRITEPATH . 'session'`
- `cookieName`: `'ci_session'`
- `expiration`: `7200` seconds (2 hours)
- `regenerateDestroy`: `false`

#### 4.2.2 Session Fixation Defense
Upon successful credential validation, the authentication controller MUST call:
```php
session()->regenerate(true);
```
This forces the issuance of a new cryptographic session ID and invalidates the pre-authentication session cookie, neutralizing session fixation attacks.

#### 4.2.3 Session Payload Structure
```php
$sessionData = [
    'isLoggedIn'      => true,
    'codigo_empleado' => $usuario['Codigo_Empleado'],
    'usuario'         => $usuario['Usuario'],
    'nombre'          => $usuario['Nombre'],
    'rol'             => $usuario['rol'],
    'id_pais'         => (int)$usuario['Id_Pais'],
    'pais_nombre'     => $pais['Pais'],
    'login_time'      => time(),
];
session()->set($sessionData);
```

#### 4.2.4 Role & Country Authorization Boundary
1. **Route Protection (`AuthFilter`)**:
   - All authenticated routes (`/home.php`, `/dashboard`, `/agencias`, `/rutas`, `/tareas`) are guarded by `AuthFilter`.
   - If `!session()->get('isLoggedIn')`, immediately redirect to `/login` with an unauthorized alert.
2. **Country Boundary Rule**:
   - In accordance with R1: *"identificando y restringiendo accesos según roles y país para garantizar que ningún usuario acceda a información indebida."*
   - All data queries MUST apply an explicit geographical filter based on the authenticated session's `id_pais`:
     ```php
     $agencyModel->where('Id_pais', session()->get('id_pais'))->findAll();
     ```
   - Attempting to pass a foreign `id_pais` in request parameters triggers an immediate HTTP 403 Forbidden.
3. **Role Boundaries**:
   - `Administrador`: Global governance, user administration, security audit, unlock capability.
   - `Supervisor`: Supervisory oversight within their designated `session('id_pais')`.
   - `Asesor` / `Agente`: Field task execution restricted to their assigned route and agency within `session('id_pais')`.

---

## 5. Recommended Implementation Specifications

### 5.1 MySQL / MariaDB Schema (Production & Development)
To be created in the local MariaDB database `creditrack`:

```sql
-- Database creation
CREATE DATABASE IF NOT EXISTS creditrack 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_general_ci;

USE creditrack;

-- 1. Pais Table
CREATE TABLE IF NOT EXISTS Pais (
    Id_pais INT AUTO_INCREMENT PRIMARY KEY,
    Pais VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Usuario Table with Security Extensions
CREATE TABLE IF NOT EXISTS Usuario (
    Codigo_Empleado VARCHAR(50) PRIMARY KEY,
    Nombre VARCHAR(150) NOT NULL,
    Usuario VARCHAR(50) NOT NULL UNIQUE,
    Contrasena VARCHAR(255) NOT NULL,
    Id_Pais INT NOT NULL,
    telefono VARCHAR(20) NULL,
    rol VARCHAR(50) NOT NULL,
    intentos_fallidos INT NOT NULL DEFAULT 0,
    bloqueado TINYINT(1) NOT NULL DEFAULT 0,
    ultimo_fallo DATETIME NULL,
    ultimo_login DATETIME NULL,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuario_pais FOREIGN KEY (Id_Pais) REFERENCES Pais(Id_pais) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Initial Geographic Seed Data
INSERT INTO Pais (Id_pais, Pais) VALUES
(1, 'El Salvador'),
(2, 'Guatemala'),
(3, 'Honduras')
ON DUPLICATE KEY UPDATE Pais=VALUES(Pais);

-- 4. Initial Users Seed Data (Default password: 'Password123!')
-- bcrypt hash for 'Password123!': $2y$12$e8F0s65hA0w0iCjO3W19i.Zl.q8s6x3c7C1GZk1kL5n0M6e4e5m8W
-- Note: Use actual PHP password_hash('Password123!', PASSWORD_BCRYPT) in seeder.
```

### 5.2 SQLite3 Compatible DDL (Automated PHPUnit Testing)
For execution under `Tests\Support\Database\Seeds` or migration:

```sql
CREATE TABLE IF NOT EXISTS Pais (
    Id_pais INTEGER PRIMARY KEY AUTOINCREMENT,
    Pais TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS Usuario (
    Codigo_Empleado TEXT PRIMARY KEY,
    Nombre TEXT NOT NULL,
    Usuario TEXT NOT NULL UNIQUE,
    Contrasena TEXT NOT NULL,
    Id_Pais INTEGER NOT NULL,
    telefono TEXT,
    rol TEXT NOT NULL,
    intentos_fallidos INTEGER NOT NULL DEFAULT 0,
    bloqueado INTEGER NOT NULL DEFAULT 0,
    ultimo_fallo TEXT,
    ultimo_login TEXT,
    creado_en TEXT,
    actualizado_en TEXT,
    FOREIGN KEY (Id_Pais) REFERENCES Pais(Id_pais)
);
```

### 5.3 Model Architecture: `UserModel` Design
Location: `app/Models/UserModel.php`

```php
<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'Usuario';
    protected $primaryKey       = 'Codigo_Empleado';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'Codigo_Empleado',
        'Nombre',
        'Usuario',
        'Contrasena',
        'Id_Pais',
        'telefono',
        'rol',
        'intentos_fallidos',
        'bloqueado',
        'ultimo_fallo',
        'ultimo_login',
    ];

    /**
     * Find user with Country information joined
     */
    public function findByUsernameWithCountry(string $username): ?array
    {
        return $this->select('Usuario.*, Pais.Pais as pais_nombre')
            ->join('Pais', 'Pais.Id_pais = Usuario.Id_Pais')
            ->where('Usuario.Usuario', $username)
            ->first();
    }

    /**
     * Increment failed attempt counter and lock if threshold is reached
     */
    public function recordFailedAttempt(string $codigoEmpleado, int $currentAttempts): int
    {
        $newAttempts = $currentAttempts + 1;
        $isLocked = ($newAttempts >= 3) ? 1 : 0;

        $this->update($codigoEmpleado, [
            'intentos_fallidos' => $newAttempts,
            'bloqueado'         => $isLocked,
            'ultimo_fallo'      => date('Y-m-d H:i:s'),
        ]);

        return $newAttempts;
    }

    /**
     * Reset failed attempts upon successful login
     */
    public function resetFailedAttempts(string $codigoEmpleado): bool
    {
        return $this->update($codigoEmpleado, [
            'intentos_fallidos' => 0,
            'bloqueado'         => 0,
            'ultimo_login'      => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Manually unlock account (Administrator privilege)
     */
    public function unlockAccount(string $codigoEmpleado): bool
    {
        return $this->update($codigoEmpleado, [
            'intentos_fallidos' => 0,
            'bloqueado'         => 0,
        ]);
    }
}
```

---

## 6. Verification & Test Strategy Matrix

| Test ID | Security Requirement | Scenario / Test Case | Expected Result | Verification Command / Method |
|---|---|---|---|---|
| **SEC-01** | **R1** Database Validation | Valid user submitting correct credentials | Session created with `isLoggedIn=true`, `rol`, `id_pais`, HTTP 302 redirect to `home.php`. | PHPUnit Feature Test / HTTP request simulation |
| **SEC-02** | **R1** Invalid Password | Valid username with wrong password | Increment `intentos_fallidos` to 1. Display warning with remaining attempts (2 left). | PHPUnit Feature Test |
| **SEC-03** | **R1** Role & Country Scope | User session holds `rol` and `id_pais` | `home.php` renders role and country names; cross-country queries blocked. | View assertion & Model query assertion |
| **SEC-04** | **R2** Consecutive Lockout (Attempt 1) | 1st failed attempt | `intentos_fallidos = 1`, `bloqueado = 0`. | Database assertion |
| **SEC-05** | **R2** Consecutive Lockout (Attempt 2) | 2nd consecutive failed attempt | `intentos_fallidos = 2`, `bloqueado = 0`. | Database assertion |
| **SEC-06** | **R2** Consecutive Lockout (Attempt 3) | 3rd consecutive failed attempt | `intentos_fallidos = 3`, `bloqueado = 1`. Access blocked. | Database assertion + View alert |
| **SEC-07** | **R2** Lockout Persistence | 4th attempt with CORRECT password on locked account | Rejection message: "Acceso bloqueado". Login disallowed. | HTTP POST to `/login` |
| **SEC-08** | **R2** Success Counter Reset | 2 failed attempts followed by 1 successful login | `intentos_fallidos` reset to 0, `bloqueado = 0`. | Database assertion |
| **SEC-09** | **R3** Route Guarding | Unauthenticated user visits `home.php` | Intercepted by `AuthFilter`, redirected to `/login` with HTTP 302. | HTTP GET `/home.php` |
| **SEC-10** | **R5** Zero `.env` Access | Application execution & test suite | Entire suite executes with SQLite `:memory:` / MySQL without opening `.env`. | File access audit / PHPUnit pass |

---

## 7. Conclusions & Next Steps for Architecture & Implementation
1. **Database Connection**: Hardcode local development settings in `app/Config/Database.php` for MariaDB (`root` / `""` / `creditrack`), and leverage `:memory:` SQLite for testing. This achieves complete independence from `.env`.
2. **Schema Deployment**: Deploy the refined MySQL/MariaDB DDL for `Pais` and `Usuario` with the 3 security columns (`intentos_fallidos`, `bloqueado`, `ultimo_fallo`).
3. **Core Authentication**: Implement `UserModel` with atomic lockout and counter management, `AuthController` with CSRF and session regeneration, and `AuthFilter` for role/country boundary protection.
4. **Verification**: Execute automated test cases validating all 10 security test scenarios above.
