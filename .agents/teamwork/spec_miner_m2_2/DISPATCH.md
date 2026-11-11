# Task Assignment: Spec Miner (Requirements & Security Specifications)

Working directory: c:\xampp\htdocs\creditrack
State folder: c:\xampp\htdocs\creditrack\.agents\teamwork\spec_miner_m2_2

## Objective
Extract the exact specifications, edge cases, session structure, flash message strings, and security rules for Milestone 2 (Auth, Controllers, and Filters).

## Files to Read
- `c:\xampp\htdocs\creditrack\.agents\teamwork\ORIGINAL_REQUEST.md`
- `c:\xampp\htdocs\creditrack\PROJECT.md`
- `c:\xampp\htdocs\creditrack\.agents\teamwork\sub_orch_m2\SCOPE.md`
- `c:\xampp\htdocs\creditrack\.agents\teamwork\sub_orch_m1\handoff.md`
- `c:\xampp\htdocs\creditrack\app\Models/UserModel.php`

## ABSOLUTE CONSTRAINTS
- DO NOT access, touch, read, or modify `.env`!
- DO NOT modify any code — this is a READ-ONLY specification extraction task.

## Expected Output
Write your findings to `c:\xampp\htdocs\creditrack\.agents\teamwork\spec_miner_m2_2\handoff.md`.
Include:
1. Exact login flow logic:
   - What happens when username is not found?
   - What happens when user is already locked (`bloqueado == 1` or `intentos_fallidos >= 3`)?
   - What happens when password verification fails (attempts < 3 vs attempts >= 3)?
   - What happens on successful login (resetting attempts, session fixation defense `session()->regenerate(true)`, session payload)?
2. Exact session keys and data types: `isLoggedIn`, `codigo_empleado`, `usuario`, `nombre`, `rol`, `id_pais`, `pais_nombre`, `login_time`.
3. Flash message strings and redirect targets (`home.php`, `/login`, etc.).
4. AuthFilter behavior: unauthenticated redirection, role and country access boundary validation.
5. Logout behavior: destroying session and redirecting.


## 2026-11-10T21:42:58Z
Read your task assignment in c:\xampp\htdocs\creditrack\.agents\teamwork\spec_miner_m2_2\DISPATCH.md.
Also read:
- c:\xampp\htdocs\creditrack\.agents\teamwork\ORIGINAL_REQUEST.md
- c:\xampp\htdocs\creditrack\PROJECT.md
- c:\xampp\htdocs\creditrack\.agents\teamwork\sub_orch_m2\SCOPE.md
- c:\xampp\htdocs\creditrack\.agents\teamwork\sub_orch_m1\handoff.md
- c:\xampp\htdocs\creditrack\app\Models\UserModel.php

Extract exact specifications, lockout logic (3 consecutive failures), password hashing & verification, session data keys & values, country boundary rules, flash message strings, and redirects.
CRITICAL: DO NOT access, touch, read, or modify .env under any circumstances!
Write your detailed report to c:\xampp\htdocs\creditrack\.agents\teamwork\spec_miner_m2_2\handoff.md and notify me when complete.
