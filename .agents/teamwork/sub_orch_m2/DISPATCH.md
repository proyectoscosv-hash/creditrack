## 2026-11-10T21:41:05Z
You are the Sub-Orchestrator for Milestone 2: M2_auth_controllers_filters.
Working directory: c:\xampp\htdocs\creditrack
State folder: c:\xampp\htdocs\creditrack\.agents\teamwork\sub_orch_m2
Parent conversation ID: b25171ea-ff1a-422d-9bcf-9384b619e780

Input files to read before doing anything:
- c:\xampp\htdocs\creditrack\.agents\teamwork\ORIGINAL_REQUEST.md
- c:\xampp\htdocs\creditrack\PROJECT.md
- c:\xampp\htdocs\creditrack\.agents\teamwork\sub_orch_m1\handoff.md
- c:\xampp\htdocs\creditrack\app\Models\UserModel.php
- c:\xampp\htdocs\creditrack\app\Config\Database.php

YOUR SCOPE (Milestone 2: M2_auth_controllers_filters):
Features 8-14 in PROJECT.md:
1. `app/Controllers/AuthController.php`:
   - `login()`: renders login view (with flash data for errors/warnings/lockout/remaining attempts). If already logged in, redirect to `home.php`.
   - `loginSubmit()`: validates input (`usuario`, `contrasena`). Queries user via `UserModel::findByUsernameWithCountry`.
     - If user locked (`bloqueado == 1` or `intentos_fallidos >= 3`), immediately rejects with lockout message ("Acceso bloqueado tras 3 intentos fallidos consecutivos de inicio de sesión erróneos") without verifying password.
     - Verifies password with `password_verify($contrasena, $user['Contrasena'])`.
     - If password fails: calls `UserModel::recordFailedAttempt`. If new attempts >= 3, sets lockout alert. Else shows remaining attempts warning ("Credenciales incorrectas. Intentos fallidos: X de 3"). Redirects back to `/login`.
     - If password succeeds: calls `UserModel::resetFailedAttempts`, regenerates session ID (`session()->regenerate(true)`), sets session data: `['isLoggedIn' => true, 'codigo_empleado' => ..., 'usuario' => ..., 'nombre' => ..., 'rol' => ..., 'id_pais' => ..., 'pais_nombre' => ..., 'login_time' => ...]`, and redirects to `home.php` (HTTP 302).
   - `logout()`: destroys session (`session()->destroy()`), redirects to `/login`.
2. `app/Controllers/Home.php`:
   - `index()`: renders `home.php` passing session data (`nombre`, `codigo_empleado`, `usuario`, `rol`, `id_pais`, `pais_nombre`).
3. `app/Filters/AuthFilter.php`:
   - `before()`: checks `session()->get('isLoggedIn')`. If not true, redirects to `/login` with flash error ("Debe iniciar sesión para acceder al sistema"). Also provides helper/validation for role and country access boundaries (blocking any request targeting a country outside `session('id_pais')`).
4. `app/Config/Filters.php`:
   - Register `'auth' => \App\Filters\AuthFilter::class` under `$aliases`.
   - Apply `'auth'` to `home.php`, `home`, and any other protected endpoints.
5. `app/Config/Routes.php`:
   - Define explicit routes:
     `$routes->get('/', 'AuthController::login');`
     `$routes->get('login', 'AuthController::login');`
     `$routes->post('login', 'AuthController::loginSubmit');`
     `$routes->get('logout', 'AuthController::logout');`
     `$routes->get('home.php', 'Home::index', ['filter' => 'auth']);`
     `$routes->get('home', 'Home::index', ['filter' => 'auth']);`
6. Automated Unit/Feature verification:
   - Create unit/feature tests for AuthController, AuthFilter, and Route protection.

FILE WRITE OWNERSHIP:
You and your workers exclusively own:
- app/Controllers/AuthController.php
- app/Controllers/Home.php
- app/Filters/AuthFilter.php
- app/Config/Filters.php
- app/Config/Routes.php
- tests/unit/ (new auth/controller/filter unit tests)
DO NOT modify `app/Views/` (owned by M3) and NEVER touch `.env`.

CRITICAL CONSTRAINTS:
- NEVER write source code directly yourself as an orchestrator — follow the Project Pattern iteration loop (Explorer -> Worker -> Reviewers -> Challengers -> Auditor -> Gate).
- ABSOLUTE PROHIBITION: DO NOT touch, read, or modify `.env` under any circumstances!
- MANDATORY INTEGRITY WARNING to Workers: DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task.
- Follow Gate criteria: worker builds/tests pass, reviewers APPROVE, challengers confirm, auditor CLEAN.
- When milestone passes, update GATE_STATUS.md and deliver handoff.md in your state folder, then message parent.
