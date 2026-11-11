## 2026-11-10T22:21:36Z
You are the Worker agent for Milestone 3 (M3_views_ui) of CrediTrack.
Your working directory is: c:\xampp\htdocs\creditrack
Your state folder is: c:\xampp\htdocs\creditrack\.agents\teamwork\worker_m3_1

CRITICAL MANDATORY INSTRUCTIONS:
1. You MUST read ORIGINAL_REQUEST.md before starting work: c:\xampp\htdocs\creditrack\.agents\teamwork\ORIGINAL_REQUEST.md
2. Read PROJECT.md: c:\xampp\htdocs\creditrack\PROJECT.md
3. Read SCOPE.md: c:\xampp\htdocs\creditrack\.agents\teamwork\sub_orch_m3\SCOPE.md
4. Read Explorer findings:
   - c:\xampp\htdocs\creditrack\.agents\teamwork\explorer_m3_1\analysis.md (login.php blueprint)
   - c:\xampp\htdocs\creditrack\.agents\teamwork\explorer_m3_2\analysis.md (home.php blueprint)
   - c:\xampp\htdocs\creditrack\.agents\teamwork\explorer_m3_3\analysis.md (test specifications)
5. Read tests to verify against:
   - c:\xampp\htdocs\creditrack\tests\Feature\ViewUITest.php
   - c:\xampp\htdocs\creditrack\tests\Feature\WorkflowIntegrationTest.php

WRITE OWNERSHIP:
You exclusively own and may write to:
- app/Views/login.php
- app/Views/home.php
- Any view CSS/components under app/Views/
DO NOT touch, read, or modify `.env` under any circumstances! (Absolute Zero-Touch Rule).
DO NOT modify Database.php, UserModel.php, AuthController.php, Home.php, Routes.php, or any file in tests/.

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

CRITICAL ARCHITECTURAL CONSTRAINTS:
1. In `app/Views/home.php`, DO NOT hardcode country names (such as "El Salvador", "Guatemala", or "Honduras") anywhere in comments, copyright, or attribution text! All country text MUST be rendered dynamically via `<?= esc($pais_nombre) ?>` (with safe fallback if empty). Hardcoding "El Salvador" will fail `WorkflowIntegrationTest::testT4Scen02` which asserts that a Guatemala supervisor must never see the string "El Salvador".
2. Both views MUST strictly implement the corporate 5-color palette: `#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C` (embedded in `<style>` and elements).
3. Both views MUST contain visible manual author attribution in HTML markup matching `/(diseñad[oa]|desarrollad[oa]|diseñadores|equipo de desarrollo|autores)/i` without CSS hiding (`display: none` or `visibility: hidden`).
4. Both views MUST have `<meta name="viewport" content="width=device-width, initial-scale=1.0">`.
5. High visual contrast: elements with dark corporate backgrounds (`#023373`, `#8D253C`, etc.) must pair with light text (`color: #ffffff;` or `white`).
6. All user profile data on `home.php` must be escaped with `esc()` to prevent XSS.

YOUR TASKS:
1. Implement `app/Views/login.php` with responsive design, form with CSRF and `usuario`/`contrasena`, flash alerts for errors, warnings (remaining attempts 1/2 of 3), lockout alert ("Acceso bloqueado tras 3 intentos fallidos consecutivos de inicio de sesión erróneos."), corporate 5-color palette, and manual author attribution.
2. Implement `app/Views/home.php` with confirmation banner, active session health badge, user profile information, role and country badges, access boundary statement, secure logout link/button to `/logout`, corporate palette, and author attribution.
3. Run verification tests:
   `php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit tests/Feature/ViewUITest.php`
   `php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit tests/Feature/WorkflowIntegrationTest.php`
   `php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit tests/Feature/AccessControlTest.php`
   `php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit tests/Feature/LockoutTest.php`
   `php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit tests/Feature/AuthTest.php`
4. Document all verification commands and outcomes in `c:\xampp\htdocs\creditrack\.agents\teamwork\worker_m3_1\handoff.md`.
5. Send a completion message to your parent orchestrator with the handoff report path.
