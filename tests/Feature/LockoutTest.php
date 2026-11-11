<?php

namespace Tests\Feature;

use Tests\Support\AuthTestCase;

/**
 * Account Security Lockout Test Suite (Requirement R2, R5)
 *
 * Covers Tier 1 (Feature Coverage) and Tier 2 (Boundary & Corner Cases)
 * for 3-consecutive-failed-attempt lockout, counter reset, locked account rejection,
 * and state persistence against in-memory SQLite3.
 */
class LockoutTest extends AuthTestCase
{
    // =========================================================================
    // TIER 1: FEATURE COVERAGE (HAPPY PATH & FOUNDATIONAL NEGATIVE TESTS)
    // =========================================================================

    /**
     * T1-LOCK-01: First Failed Attempt Increments Counter to 1
     * Submit single incorrect password on clean user (user_clean).
     * Verifies HTTP 302 to login, intentos_fallidos = 1, and bloqueado = 0.
     */
    public function testT1Lock01_FirstFailedAttemptIncrementsCounterToOne(): void
    {
        $result = $this->post('/login', [
            'usuario'    => 'user_clean',
            'contrasena' => 'WrongPassword1!',
        ]);

        $result->assertStatus(302);
        $result->assertRedirect();
        $this->assertStringContainsString('login', (string) $result->getRedirectUrl());

        $this->assertFalse((bool) (session('isLoggedIn') ?? false));

        $this->seeInDatabase('Usuario', [
            'Usuario'           => 'user_clean',
            'intentos_fallidos' => 1,
            'bloqueado'         => 0,
        ]);

        $user = $this->db->table('Usuario')->where('Usuario', 'user_clean')->get()->getRowArray();
        $this->assertNotNull($user['ultimo_fallo'], 'ultimo_fallo timestamp must be recorded on failed attempt.');
    }

    /**
     * T1-LOCK-02: Second Consecutive Failed Attempt Increments Counter to 2
     * Submit two consecutive incorrect passwords on clean user (user_clean).
     * Verifies counter increments to 2 and account remains unblocked.
     */
    public function testT1Lock02_SecondConsecutiveFailedAttemptIncrementsCounterToTwo(): void
    {
        // First failed attempt
        $this->post('/login', [
            'usuario'    => 'user_clean',
            'contrasena' => 'WrongPassword1!',
        ]);

        // Second failed attempt
        $result2 = $this->post('/login', [
            'usuario'    => 'user_clean',
            'contrasena' => 'WrongPassword2!',
        ]);

        $result2->assertStatus(302);
        $result2->assertRedirect();
        $this->assertStringContainsString('login', (string) $result2->getRedirectUrl());

        $this->seeInDatabase('Usuario', [
            'Usuario'           => 'user_clean',
            'intentos_fallidos' => 2,
            'bloqueado'         => 0,
        ]);

        $user = $this->db->table('Usuario')->where('Usuario', 'user_clean')->get()->getRowArray();
        $this->assertNotNull($user['ultimo_fallo'], 'ultimo_fallo timestamp must be recorded on failed attempt.');
    }

    /**
     * T1-LOCK-03: Third Consecutive Failed Attempt Triggers Account Lockout
     * Submit three consecutive incorrect passwords on clean user (user_clean).
     * Verifies counter reaches 3, bloqueado is set to 1, and account is locked.
     */
    public function testT1Lock03_ThirdConsecutiveFailedAttemptTriggersAccountLockout(): void
    {
        // First failed attempt
        $this->post('/login', [
            'usuario'    => 'user_clean',
            'contrasena' => 'WrongPassword1!',
        ]);

        // Second failed attempt
        $this->post('/login', [
            'usuario'    => 'user_clean',
            'contrasena' => 'WrongPassword2!',
        ]);

        // Third failed attempt
        $result3 = $this->post('/login', [
            'usuario'    => 'user_clean',
            'contrasena' => 'WrongPassword3!',
        ]);

        $result3->assertStatus(302);
        $result3->assertRedirect();
        $this->assertStringContainsString('login', (string) $result3->getRedirectUrl());

        $this->seeInDatabase('Usuario', [
            'Usuario'           => 'user_clean',
            'intentos_fallidos' => 3,
            'bloqueado'         => 1,
        ]);

        $user = $this->db->table('Usuario')->where('Usuario', 'user_clean')->get()->getRowArray();
        $this->assertNotNull($user['ultimo_fallo'], 'ultimo_fallo timestamp must be recorded on failed attempt.');
    }

    /**
     * T1-LOCK-04: Counter Reset on Successful Login after 1 Failure
     * User fails once (intentos_fallidos = 1), then enters correct password.
     * Verifies redirect to home.php, session establishment, and counter reset to 0.
     */
    public function testT1Lock04_CounterResetOnSuccessfulLoginAfterOneFailure(): void
    {
        // 1. First attempt fails
        $this->post('/login', [
            'usuario'    => 'user_clean',
            'contrasena' => 'WrongPassword1!',
        ]);

        $this->seeInDatabase('Usuario', [
            'Usuario'           => 'user_clean',
            'intentos_fallidos' => 1,
            'bloqueado'         => 0,
        ]);

        // 2. Second attempt succeeds with valid password
        $resultSuccess = $this->post('/login', [
            'usuario'    => 'user_clean',
            'contrasena' => 'AdminPass123!',
        ]);

        $resultSuccess->assertStatus(302);
        $resultSuccess->assertRedirect();
        $this->assertStringContainsString('home.php', (string) $resultSuccess->getRedirectUrl());

        $resultSuccess->assertSessionHas('isLoggedIn', true);

        $this->seeInDatabase('Usuario', [
            'Usuario'           => 'user_clean',
            'intentos_fallidos' => 0,
            'bloqueado'         => 0,
        ]);

        $user = $this->db->table('Usuario')->where('Usuario', 'user_clean')->get()->getRowArray();
        $this->assertNotNull($user['ultimo_login'], 'ultimo_login timestamp must be recorded on successful login.');
    }

    /**
     * T1-LOCK-05: Counter Reset on Successful Login after 2 Failures
     * User with 2 previous failures (user_warning, seeded with intentos_fallidos = 2)
     * enters correct credentials. Verifies reset to 0 and successful login.
     */
    public function testT1Lock05_CounterResetOnSuccessfulLoginAfterTwoFailures(): void
    {
        // Verify seeded state
        $this->seeInDatabase('Usuario', [
            'Usuario'           => 'user_warning',
            'intentos_fallidos' => 2,
            'bloqueado'         => 0,
        ]);

        // Login with correct password
        $result = $this->post('/login', [
            'usuario'    => 'user_warning',
            'contrasena' => 'WarnPass123!',
        ]);

        $result->assertStatus(302);
        $result->assertRedirect();
        $this->assertStringContainsString('home.php', (string) $result->getRedirectUrl());

        $result->assertSessionHas('isLoggedIn', true);

        $this->seeInDatabase('Usuario', [
            'Usuario'           => 'user_warning',
            'intentos_fallidos' => 0,
            'bloqueado'         => 0,
        ]);

        $user = $this->db->table('Usuario')->where('Usuario', 'user_warning')->get()->getRowArray();
        $this->assertNotNull($user['ultimo_login'], 'ultimo_login timestamp must be recorded on successful login.');
    }

    /**
     * T1-LOCK-06: Locked Account Immediate Rejection on 4th Attempt with Valid Password
     * User pre-locked (locked_user, seeded with intentos_fallidos = 3, bloqueado = 1)
     * attempts authentication with the correct password.
     * Verifies immediate rejection without establishing a session.
     */
    public function testT1Lock06_LockedAccountImmediateRejectionOnFourthAttemptWithValidPassword(): void
    {
        $result = $this->post('/login', [
            'usuario'    => 'locked_user',
            'contrasena' => 'LockedPass123!', // Correct password
        ]);

        $result->assertStatus(302);
        $result->assertRedirect();
        $this->assertStringContainsString('login', (string) $result->getRedirectUrl());

        $this->assertFalse((bool) (session('isLoggedIn') ?? false));

        $this->seeInDatabase('Usuario', [
            'Usuario'   => 'locked_user',
            'bloqueado' => 1,
        ]);
    }

    // =========================================================================
    // TIER 2: BOUNDARY & CORNER CASES
    // =========================================================================

    /**
     * T2-LOCK-01: Multi-Session / Cookie Clearing Resilience
     * Attacker fails 2 attempts, resets session (clears cookies), and fails attempt 3.
     * Verifies database persistence locks the account on the 3rd attempt.
     */
    public function testT2Lock01_MultiSessionCookieClearingResilience(): void
    {
        // Session 1: Attempt 1
        $this->post('/login', [
            'usuario'    => 'user_clean',
            'contrasena' => 'Wrong1',
        ]);

        // Session 1: Attempt 2
        $this->post('/login', [
            'usuario'    => 'user_clean',
            'contrasena' => 'Wrong2',
        ]);

        // Simulate cookie clear / new browser session
        $result3 = $this->withSession([])->post('/login', [
            'usuario'    => 'user_clean',
            'contrasena' => 'Wrong3',
        ]);

        $result3->assertStatus(302);

        $this->seeInDatabase('Usuario', [
            'Usuario'           => 'user_clean',
            'intentos_fallidos' => 3,
            'bloqueado'         => 1,
        ]);

        $user = $this->db->table('Usuario')->where('Usuario', 'user_clean')->get()->getRowArray();
        $this->assertNotNull($user['ultimo_fallo'], 'ultimo_fallo timestamp must be recorded on failed attempt.');

        // Attempt 4: Even in a new session (cleared cookies), correct password on locked account is rejected
        $result4 = $this->withSession([])->post('/login', [
            'usuario'    => 'user_clean',
            'contrasena' => 'AdminPass123!',
        ]);

        $result4->assertStatus(302);
        $result4->assertRedirect();
        $this->assertStringContainsString('login', (string) $result4->getRedirectUrl());
        $this->assertFalse((bool) (session('isLoggedIn') ?? false));

        $this->seeInDatabase('Usuario', [
            'Usuario'           => 'user_clean',
            'intentos_fallidos' => 3,
            'bloqueado'         => 1,
        ]);
    }

    /**
     * T2-LOCK-02: Lockout Isolation Between Different User Accounts
     * Locking user_clean must not affect legitimate authentication of admin_sv.
     */
    public function testT2Lock02_LockoutIsolationBetweenDifferentUserAccounts(): void
    {
        // 1. Lock user_clean with 3 failed attempts
        $this->post('/login', ['usuario' => 'user_clean', 'contrasena' => 'Bad1']);
        $this->post('/login', ['usuario' => 'user_clean', 'contrasena' => 'Bad2']);
        $this->post('/login', ['usuario' => 'user_clean', 'contrasena' => 'Bad3']);

        $this->seeInDatabase('Usuario', [
            'Usuario'   => 'user_clean',
            'bloqueado' => 1,
        ]);

        // 2. Distinct legitimate user logs in
        $adminResult = $this->post('/login', [
            'usuario'    => 'admin_sv',
            'contrasena' => 'AdminPass123!',
        ]);

        $adminResult->assertStatus(302);
        $adminResult->assertSessionHas('isLoggedIn', true);

        $this->seeInDatabase('Usuario', [
            'Usuario'   => 'admin_sv',
            'bloqueado' => 0,
        ]);

        $userAdmin = $this->db->table('Usuario')->where('Usuario', 'admin_sv')->get()->getRowArray();
        $this->assertNotNull($userAdmin['ultimo_login'], 'ultimo_login timestamp must be recorded on successful login.');
    }

    /**
     * T2-LOCK-03: Failed Attempts on Non-Existent Users Do Not Leak State
     * Repeated invalid logins with unknown usernames must not corrupt database
     * or impact legitimate accounts.
     */
    public function testT2Lock03_FailedAttemptsOnNonExistentUsersDoNotLeakState(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $result = $this->post('/login', [
                'usuario'    => 'phantom_target',
                'contrasena' => 'bad_password_' . $i,
            ]);
            $result->assertStatus(302);
            $this->assertFalse((bool) (session('isLoggedIn') ?? false));
        }

        $this->seeInDatabase('Usuario', [
            'Usuario'           => 'admin_sv',
            'intentos_fallidos' => 0,
            'bloqueado'         => 0,
        ]);
    }

    /**
     * T2-LOCK-04: Pre-Locked Account Bypass of Password Verify
     * When account is locked, both correct and incorrect passwords must be rejected.
     */
    public function testT2Lock04_PreLockedAccountBypassOfPasswordVerify(): void
    {
        // Attempt with wrong password
        $resWrong = $this->post('/login', [
            'usuario'    => 'locked_user',
            'contrasena' => 'WrongPass!',
        ]);
        $resWrong->assertStatus(302);
        $this->assertFalse((bool) (session('isLoggedIn') ?? false));

        // Attempt with valid password
        $resValid = $this->post('/login', [
            'usuario'    => 'locked_user',
            'contrasena' => 'LockedPass123!',
        ]);
        $resValid->assertStatus(302);
        $this->assertFalse((bool) (session('isLoggedIn') ?? false));

        $this->seeInDatabase('Usuario', [
            'Usuario'   => 'locked_user',
            'bloqueado' => 1,
        ]);
    }

    /**
     * T2-LOCK-05: Lockout Stability Under Continued Failed Attempts
     * Flooding locked account with additional failed attempts must maintain locked state safely.
     */
    public function testT2Lock05_LockoutStabilityUnderContinuedFailedAttempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $result = $this->post('/login', [
                'usuario'    => 'locked_user',
                'contrasena' => 'WrongFloodPass' . $i,
            ]);
            $result->assertStatus(302);
            $this->assertFalse((bool) (session('isLoggedIn') ?? false));
        }

        $this->seeInDatabase('Usuario', [
            'Usuario'   => 'locked_user',
            'bloqueado' => 1,
        ]);
    }

    /**
     * T2-LOCK-06: Administrative Unlock Re-Enables Authentication
     * Resetting bloqueado = 0 and intentos_fallidos = 0 allows user to log in again.
     */
    public function testT2Lock06_AdministrativeUnlockReEnablesAuthentication(): void
    {
        // 1. Perform administrative unlock in DB
        $this->db->table('Usuario')
            ->where('Usuario', 'locked_user')
            ->update([
                'bloqueado'         => 0,
                'intentos_fallidos' => 0,
            ]);

        $this->seeInDatabase('Usuario', [
            'Usuario'           => 'locked_user',
            'bloqueado'         => 0,
            'intentos_fallidos' => 0,
        ]);

        // 2. User successfully authenticates
        $result = $this->post('/login', [
            'usuario'    => 'locked_user',
            'contrasena' => 'LockedPass123!',
        ]);

        $result->assertStatus(302);
        $result->assertRedirect();
        $this->assertStringContainsString('home.php', (string) $result->getRedirectUrl());

        $result->assertSessionHas('isLoggedIn', true);

        $userUnlocked = $this->db->table('Usuario')->where('Usuario', 'locked_user')->get()->getRowArray();
        $this->assertNotNull($userUnlocked['ultimo_login'], 'ultimo_login timestamp must be recorded on successful login.');
    }
}
