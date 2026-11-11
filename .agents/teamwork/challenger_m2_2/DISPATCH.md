# Task Assignment: Challenger 2 (Adversarial Stress Testing: Session, Fixation & Country Scoping)

Working directory: c:\xampp\htdocs\creditrack
State folder: c:\xampp\htdocs\creditrack\.agents\teamwork\challenger_m2_2

## Objective
Empirically stress-test session fixation defense, session payload types, route filtering, unauthenticated access blocking, and territorial country boundaries (`?pais=X`).

## Files to Read
- `c:\xampp\htdocs\creditrack\.agents\teamwork\ORIGINAL_REQUEST.md`
- `c:\xampp\htdocs\creditrack\PROJECT.md`
- `c:\xampp\htdocs\creditrack\.agents\teamwork\sub_orch_m2\SCOPE.md`
- `c:\xampp\htdocs\creditrack\.agents\teamwork\worker_m2_1\handoff.md`
- `c:\xampp\htdocs\creditrack\app\Filters\AuthFilter.php`
- `c:\xampp\htdocs\creditrack\app\Controllers\AuthController.php`
- `c:\xampp\htdocs\creditrack\app\Controllers\Home.php`
- `c:\xampp\htdocs\creditrack\app\Config\Filters.php`
- `c:\xampp\htdocs\creditrack\app\Config\Routes.php`

## Verification Instructions
1. Empirically verify:
   - Session fixation: does `session()->regenerate(true)` get called? Does `MockSession::$didRegenerate` or `$_SESSION['__ci_last_regenerate']` reflect regeneration?
   - Session payload: verify `isLoggedIn` (bool true), `codigo_empleado` (string), `usuario` (string), `nombre` (string), `rol` (string), `id_pais` (int), `pais_nombre` (string), `login_time` (int).
   - Incomplete sessions (missing `rol` or `id_pais`) are intercepted by `AuthFilter` and redirected to `login`.
   - Unknown roles (e.g. `'Hacker'`) receive HTTP 403.
   - Cross-country parameter attacks (`?pais=2` or `?id_pais=2` when logged in as El Salvador `id_pais=1`) receive HTTP 403 Forbidden.
   - Authorized country parameters (`?pais=1` or `?pais=El%20Salvador`) pass through successfully (HTTP 200).
   - Logout completely purges session data.
2. Run standard tests:
   `php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage`

## ABSOLUTE PROHIBITION
DO NOT touch, read, or modify `.env` under any circumstances!

## Expected Output
Write your adversarial report to `c:\xampp\htdocs\creditrack\.agents\teamwork\challenger_m2_2\handoff.md`.
End with a clear, explicit verdict: `APPROVE` or `REJECT`. Message the orchestrator when finished.


## 2026-11-10T22:03:46Z
Read your task assignment in c:\xampp\htdocs\creditrack\.agents\teamwork\challenger_m2_2\DISPATCH.md.
Also read:
- c:\xampp\htdocs\creditrack\.agents\teamwork\ORIGINAL_REQUEST.md
- c:\xampp\htdocs\creditrack\PROJECT.md
- c:\xampp\htdocs\creditrack\.agents\teamwork\sub_orch_m2\SCOPE.md
- c:\xampp\htdocs\creditrack\.agents\teamwork\worker_m2_1\handoff.md
- c:\xampp\htdocs\creditrack\app\Filters\AuthFilter.php
- c:\xampp\htdocs\creditrack\app\Controllers\AuthController.php
- c:\xampp\htdocs\creditrack\app\Controllers\Home.php
- c:\xampp\htdocs\creditrack\app\Config\Filters.php
- c:\xampp\htdocs\creditrack\app\Config\Routes.php

Empirically stress-test session fixation defense, session payload types, route filtering, unauthenticated access blocking, and territorial country boundaries (?pais=X).
Run tests: php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage
Verify strict isolation: zero access/modification to .env.
Deliver your adversarial report to c:\xampp\htdocs\creditrack\.agents\teamwork\challenger_m2_2\handoff.md with explicit verdict APPROVE or REJECT, and message me.
