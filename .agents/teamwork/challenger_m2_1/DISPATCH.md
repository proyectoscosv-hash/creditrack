# Task Assignment: Challenger 1 (Adversarial Stress Testing: Lockout & Attempts)

Working directory: c:\xampp\htdocs\creditrack
State folder: c:\xampp\htdocs\creditrack\.agents\teamwork\challenger_m2_1

## Objective
Empirically stress-test the authentication, lockout mechanism, attempt incrementation, counter reset, and pre-locked rejection logic in Milestone 2.

## Files to Read
- `c:\xampp\htdocs\creditrack\.agents\teamwork\ORIGINAL_REQUEST.md`
- `c:\xampp\htdocs\creditrack\PROJECT.md`
- `c:\xampp\htdocs\creditrack\.agents\teamwork\sub_orch_m2\SCOPE.md`
- `c:\xampp\htdocs\creditrack\.agents\teamwork\worker_m2_1\handoff.md`
- `c:\xampp\htdocs\creditrack\app\Controllers\AuthController.php`
- `c:\xampp\htdocs\creditrack\app\Models\UserModel.php`

## Verification Instructions
1. Write a temporary standalone empirical test script in your state folder (e.g., `tests_stress_lockout.php` or unit test harness in tests/) or execute via CLI to stress-test:
   - 3 consecutive failed attempts trigger lockout (`bloqueado = 1`, `intentos_fallidos = 3`).
   - Attempt 4 and 5 on locked account continue to be rejected immediately without calling `password_verify`.
   - Valid password on attempt 1 or 2 resets counter to 0.
   - Non-existent username does not alter database counters.
   - Whitespace and empty inputs do not alter database counters.
   - Special characters, SQL injection payloads, long buffers.
2. Run standard tests:
   `php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage`

## ABSOLUTE PROHIBITION
DO NOT touch, read, or modify `.env` under any circumstances!

## Expected Output
Write your adversarial report to `c:\xampp\htdocs\creditrack\.agents\teamwork\challenger_m2_1\handoff.md`.
End with a clear, explicit verdict: `APPROVE` or `REJECT`. Message the orchestrator when finished.


## 2026-11-10T22:03:41Z
Read your task assignment in c:\xampp\htdocs\creditrack\.agents\teamwork\challenger_m2_1\DISPATCH.md.
Also read:
- c:\xampp\htdocs\creditrack\.agents\teamwork\ORIGINAL_REQUEST.md
- c:\xampp\htdocs\creditrack\PROJECT.md
- c:\xampp\htdocs\creditrack\.agents\teamwork\sub_orch_m2\SCOPE.md
- c:\xampp\htdocs\creditrack\.agents\teamwork\worker_m2_1\handoff.md
- c:\xampp\htdocs\creditrack\app\Controllers\AuthController.php
- c:\xampp\htdocs\creditrack\app\Models\UserModel.php

Empirically stress-test lockout logic, 3 attempts, pre-locked rejection without password verify, reset on success, and input handling.
Run tests: php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage
Verify strict isolation: zero access/modification to .env.
Deliver your adversarial report to c:\xampp\htdocs\creditrack\.agents\teamwork\challenger_m2_1\handoff.md with explicit verdict APPROVE or REJECT, and message me.
