## 2026-11-10T22:12:32Z
You are the Sub-Orchestrator for Milestone 3: M3_views_ui.
Working directory: c:\xampp\htdocs\creditrack
State folder: c:\xampp\htdocs\creditrack\.agents\teamwork\sub_orch_m3
Parent conversation ID: b25171ea-ff1a-422d-9bcf-9384b619e780

Input files to read before doing anything:
- c:\xampp\htdocs\creditrack\.agents\teamwork\ORIGINAL_REQUEST.md
- c:\xampp\htdocs\creditrack\PROJECT.md
- c:\xampp\htdocs\creditrack\.agents\teamwork\sub_orch_m2\handoff.md
- c:\xampp\htdocs\creditrack\TEST_READY.md
- c:\xampp\htdocs\creditrack\tests\Feature\ViewUITest.php

YOUR SCOPE (Milestone 3: M3_views_ui):
Features 15-18 in PROJECT.md:
1. `app/Views/login.php`:
   - Responsive HTML5 login view.
   - Form posting to `/login` with `usuario`, `contrasena`, and CSRF protection (`<?= csrf_field() ?>`).
   - Visual alerts displaying flash messages: error, warning (failed attempt counters 1 of 3, 2 of 3), and lockout alert ("Acceso bloqueado tras 3 intentos fallidos consecutivos de inicio de sesión erróneos").
   - Corporate 5-color palette strictly implemented: `#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C`.
   - Visible manual author attribution in the HTML markup (e.g. footer with system design team credits).
2. `app/Views/home.php`:
   - Responsive landing view for authenticated users.
   - Prominent confirmation banner: "¡Inicio de sesión exitoso! Bienvenido al sistema Creditrack."
   - Active session health badge: "Estado de la sesión: Activa" (with session time).
   - User profile info: Código de Empleado, Nombre Completo, Usuario.
   - Assigned Role confirmation badge (`$rol`).
   - Assigned Country confirmation badge (`$pais_nombre`).
   - Clear access boundary statement: "Acceso restringido exclusivamente a información y operaciones de [pais_nombre] con privilegios de [rol]."
   - Functional secure logout button/link to `/logout`.
   - Corporate 5-color palette strictly implemented: `#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C`.
   - Visible manual author attribution in HTML markup.
3. Verification:
   - Run tests (`tests/Feature/ViewUITest.php` and unit tests) to verify view rendering, palette compliance, and manual attribution assertions.

FILE WRITE OWNERSHIP:
You and your workers exclusively own:
- app/Views/login.php
- app/Views/home.php
- Any view CSS/components under app/Views/
DO NOT modify Database.php, UserModel.php, AuthController.php, Routes.php, or tests/, and NEVER touch `.env`.

CRITICAL CONSTRAINTS:
- NEVER write source code directly yourself as an orchestrator — follow the Project Pattern iteration loop (Explorer -> Worker -> Reviewers -> Challengers -> Auditor -> Gate).
- ABSOLUTE PROHIBITION: DO NOT touch, read, or modify `.env` under any circumstances!
- MANDATORY INTEGRITY WARNING to Workers: DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task.
- Follow Gate criteria: worker builds/tests pass, reviewers APPROVE, challengers confirm, auditor CLEAN.
- When milestone passes, update GATE_STATUS.md and deliver handoff.md in your state folder, then message parent.
