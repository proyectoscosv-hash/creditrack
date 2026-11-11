# Task Assignment: Explorer 3 (Test Strategy & Harness)

Working directory: c:\xampp\htdocs\creditrack
State folder: c:\xampp\htdocs\creditrack\.agents\teamwork\explorer_m2_3

## Objective
Investigate how to write automated unit and controller/feature tests for `AuthController`, `Home`, and `AuthFilter` in CodeIgniter 4 using PHPUnit and SQLite `:memory:`.

## Files to Read
- `c:\xampp\htdocs\creditrack\.agents\teamwork\ORIGINAL_REQUEST.md`
- `c:\xampp\htdocs\creditrack\PROJECT.md`
- `c:\xampp\htdocs\creditrack\.agents\teamwork\sub_orch_m2\SCOPE.md`
- `c:\xampp\htdocs\creditrack\.agents\teamwork\sub_orch_m1\handoff.md`
- `c:\xampp\htdocs\creditrack\tests/unit/UserModelTest.php`
- `c:\xampp\htdocs\creditrack\phpunit.xml.dist`

## ABSOLUTE CONSTRAINTS
- DO NOT access, touch, read, or modify `.env`!
- DO NOT modify any code — this is a READ-ONLY test investigation task.

## Expected Output
Write your findings to `c:\xampp\htdocs\creditrack\.agents\teamwork\explorer_m2_3\handoff.md`.
Include:
1. Recommended test structure: how to write unit tests for `AuthController`, `AuthFilter`, and routes.
2. How to mock or manage session and requests in CodeIgniter 4 unit tests (`Services::session()`, `FeatureTestTrait`, or direct unit testing of controller/filter methods).
3. Database migration & seeding in tests (reusing `AuthSeeder` and SQLite `:memory:`).
4. Command line for running tests: `php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage`.
5. Edge cases that MUST be tested.


## 2026-11-10T21:43:03Z
Read your task assignment in c:\xampp\htdocs\creditrack\.agents\teamwork\explorer_m2_3\DISPATCH.md.
Also read:
- c:\xampp\htdocs\creditrack\.agents\teamwork\ORIGINAL_REQUEST.md
- c:\xampp\htdocs\creditrack\PROJECT.md
- c:\xampp\htdocs\creditrack\.agents\teamwork\sub_orch_m2\SCOPE.md
- c:\xampp\htdocs\creditrack\.agents\teamwork\sub_orch_m1\handoff.md
- c:\xampp\htdocs\creditrack\tests\unit\UserModelTest.php
- c:\xampp\htdocs\creditrack\phpunit.xml.dist

Investigate unit and controller/filter test patterns for CodeIgniter 4 in SQLite :memory: environment.
CRITICAL: DO NOT access, touch, read, or modify .env under any circumstances!
Write your detailed report to c:\xampp\htdocs\creditrack\.agents\teamwork\explorer_m2_3\handoff.md and notify me when complete.
