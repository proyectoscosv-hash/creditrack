## 2026-11-10T21:06:00Z
You are the Sub-Orchestrator for the E2E Testing Track.
Working directory: c:\xampp\htdocs\creditrack
State folder: c:\xampp\htdocs\creditrack\.agents\teamwork\sub_orch_e2e
Parent conversation ID: b25171ea-ff1a-422d-9bcf-9384b619e780

Input files to read before doing anything:
- c:\xampp\htdocs\creditrack\.agents\teamwork\ORIGINAL_REQUEST.md
- c:\xampp\htdocs\creditrack\PROJECT.md
- c:\xampp\htdocs\creditrack\.agents\teamwork\explorer_survey_1\survey_report.md
- c:\xampp\htdocs\creditrack\.agents\teamwork\spec_miner_survey_2\spec_report.md
- c:\xampp\htdocs\creditrack\.agents\teamwork\explorer_survey_3\db_security_report.md

YOUR SCOPE (E2E Testing Track):
1. Design the E2E test infrastructure and write `TEST_INFRA.md` at the project root following the Project Pattern.
2. Design and implement the opaque-box test suite across Tiers 1-4 based on user requirements:
   - Tier 1: Feature Coverage (>=5 per feature) for authentication, session roles & countries, 3-failed attempt lock, redirection to home.php, color palette and author attribution.
   - Tier 2: Boundary & Corner Cases (>=5 per feature) - wrong passwords, unknown users, empty inputs, SQL injection attempts, 2-failures-then-success reset, 3rd failure lockout, 4th attempt with valid password on locked account, cross-country boundaries.
   - Tier 3: Cross-Feature Combinations (pairwise interactions).
   - Tier 4: Real-World Application Scenarios (complete workflows).
3. Place test files in `tests/` (e.g. `tests/Feature/AuthTest.php`, `tests/Feature/AccessControlTest.php`, `tests/Feature/LockoutTest.php`, `tests/Feature/ViewUITest.php`).
4. Ensure tests run via PHPUnit using the SQLite `:memory:` database without touching or depending on `.env`.
5. When the full test suite is written, verified, and complete, create `TEST_READY.md` at project root with runner command, tier coverage counts, and feature checklist.

FILE WRITE OWNERSHIP:
You and your test writers exclusively own:
- TEST_INFRA.md (at project root)
- TEST_READY.md (at project root)
- tests/ (all new test classes, test suites, test migrations/seeds)
- phpunit.dist.xml (if test suites need registration)
DO NOT write or modify application code in `app/`! The test track is strictly opaque-box. And NEVER touch .env.

CRITICAL CONSTRAINTS:
- As an orchestrator, delegate implementation to workers/test writers (e.g. teamwork_test_writer or teamwork_worker).
- ABSOLUTE PROHIBITION: DO NOT touch, read, or modify .env under any circumstances!
- MANDATORY INTEGRITY WARNING: Genuine tests only. No bypasses.
- When test suite is ready and TEST_READY.md is published, message parent.
