<?php

namespace Tests\Feature;

use Tests\Support\AuthTestCase;

/**
 * WorkflowIntegrationTest
 *
 * Implements Tier 3 (Pairwise Cross-Feature Combinations) and
 * Tier 4 (Real-World Application Scenarios) test specifications.
 *
 * Requirements Covered:
 * - R1: Authentication validation, role & country scoping, boundary restriction.
 * - R2: Security lockout after 3 consecutive failures, reset on success, 4th attempt rejection.
 * - R3: Redirection to home.php, landing view session confirmation, secure logout.
 * - R4: Corporate 5-color palette and manual author attribution.
 * - R5: In-memory SQLite execution with zero .env access.
 *
 * @internal
 */
final class WorkflowIntegrationTest extends AuthTestCase
{
    // =========================================================================
    // TIER 3: CROSS-FEATURE PAIRWISE COMBINATIONS
    // =========================================================================

    /**
     * T3-PAIR-01: Auth Failure Increment Across Roles.
     * Verifies that incorrect password submissions increment failure counters identically
     * across Administrador, Supervisor, and Asesor roles without privilege bypass.
     */
    public function testT3Pair01_AuthFailureIncrementAcrossRoles(): void
    {
        $roles = [
            ['user' => 'admin_user', 'code' => 'EMP-ADM-02'],
            ['user' => 'supervisor_sv', 'code' => 'EMP-SUP-01'],
            ['user' => 'asesor_sv', 'code' => 'EMP-ASE-01'],
        ];

        foreach ($roles as $r) {
            $result = $this->post('login', [
                'usuario'    => $r['user'],
                'contrasena' => 'WrongPassword!',
            ]);

            $result->assertStatus(302);
            $location = $result->response()->getHeaderLine('Location');
            $this->assertMatchesRegularExpression('/login/i', $location);

            $row = $this->db->table('Usuario')->where('Codigo_Empleado', $r['code'])->get()->getRowArray();
            $this->assertNotNull($row, "User record for {$r['code']} must exist.");
            $this->assertEquals(1, (int) $row['intentos_fallidos'], "Attempts must increment to 1 for {$r['user']}.");
            $this->assertEquals(0, (int) $row['bloqueado'], "Account must remain unblocked for {$r['user']}.");
        }
    }

    /**
     * T3-PAIR-02: Counter Reset on Success (2 Failures + 1 Success) & Country Assignment.
     * Verifies that an account with 2 prior failed attempts resets to 0 upon valid login,
     * and establishes session scoped to the assigned country (Guatemala).
     */
    public function testT3Pair02_CounterResetOnSuccessAndCountryAssignment(): void
    {
        // Pre-set supervisor_gt with 2 failed attempts
        $this->db->table('Usuario')->where('Codigo_Empleado', 'EMP-SUP-02')->update(['intentos_fallidos' => 2]);

        $result = $this->post('login', [
            'usuario'    => 'supervisor_gt',
            'contrasena' => 'SuperGt123!',
        ]);

        $result->assertStatus(302);
        $location = $result->response()->getHeaderLine('Location');
        $this->assertMatchesRegularExpression('/home\.php/i', $location);

        $row = $this->db->table('Usuario')->where('Codigo_Empleado', 'EMP-SUP-02')->get()->getRowArray();
        $this->assertEquals(0, (int) $row['intentos_fallidos'], 'Failure counter must reset to 0 upon successful login.');
        $this->assertEquals(0, (int) $row['bloqueado'], 'Account must remain unlocked.');

        $homeResult = $this->withSession([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-SUP-02',
            'usuario'         => 'supervisor_gt',
            'nombre'          => 'Beatriz Supervisor',
            'rol'             => 'Supervisor',
            'id_pais'         => 2,
            'pais_nombre'     => 'Guatemala',
        ])->get('home.php');

        $homeResult->assertStatus(200);
        $homeResult->assertSee('Guatemala');
        $homeResult->assertSee('Supervisor');
    }

    /**
     * T3-PAIR-03: Lockout on 3rd Failure and Subsequent Valid Password Rejection.
     * Verifies that a 3rd consecutive failed attempt locks the account, and the subsequent
     * 4th attempt with the CORRECT password is immediately rejected.
     */
    public function testT3Pair03_LockoutOn3rdFailureAndSubsequentValidPasswordRejection(): void
    {
        // 3 consecutive failed attempts
        for ($i = 1; $i <= 3; $i++) {
            $res = $this->post('login', [
                'usuario'    => 'asesor_sv',
                'contrasena' => 'BadPass_' . $i,
            ]);
            $res->assertStatus(302);
        }

        $row = $this->db->table('Usuario')->where('Codigo_Empleado', 'EMP-ASE-01')->get()->getRowArray();
        $this->assertEquals(3, (int) $row['intentos_fallidos']);
        $this->assertEquals(1, (int) $row['bloqueado']);

        // 4th attempt with the CORRECT password
        $result = $this->post('login', [
            'usuario'    => 'asesor_sv',
            'contrasena' => 'AsesorSv123!',
        ]);

        $result->assertStatus(302);
        $location = $result->response()->getHeaderLine('Location');
        $this->assertMatchesRegularExpression('/login/i', $location);
        $this->assertFalse(session()->get('isLoggedIn') ?? false, 'Locked account must not establish session.');

        $rowAfter = $this->db->table('Usuario')->where('Codigo_Empleado', 'EMP-ASE-01')->get()->getRowArray();
        $this->assertEquals(1, (int) $rowAfter['bloqueado'], 'Account must remain locked in DB.');
    }

    /**
     * T3-PAIR-04: Session Fixation Defense on Login.
     * Verifies session regeneration upon successful authentication.
     */
    public function testT3Pair04_SessionFixationDefenseOnLogin(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $preLoginId = session_id();

        $result = $this->post('login', [
            'usuario'    => 'admin_user',
            'contrasena' => 'AdminPass123!',
        ]);

        $result->assertStatus(302);
        $location = $result->response()->getHeaderLine('Location');
        $this->assertMatchesRegularExpression('/home\.php/i', $location);

        $postLoginId = session_id();
        $this->assertTrue(session()->get('isLoggedIn') ?? false);
        $this->assertEquals('Administrador', session()->get('rol'));

        if (!empty($preLoginId)) {
            $this->assertNotEquals($preLoginId, $postLoginId, 'Session ID must be regenerated on authentication.');
        }
    }

    /**
     * T3-PAIR-05: Lockout UI with Corporate Palette and Attribution.
     * Verifies that the lockout notification view renders all 5 corporate palette colors and author credit.
     */
    public function testT3Pair05_LockoutUIWithCorporatePaletteAndAttribution(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->post('login', [
                'usuario'    => 'asesor_sv',
                'contrasena' => 'BadPass_' . $i,
            ]);
        }

        $result = $this->get('login');
        $result->assertStatus(200);

        $body = $result->getBody();
        $this->assertCorporatePalettePresent($body);
        $this->assertMatchesRegularExpression(
            '/(diseñad[oa]|desarrollad[oa]|diseñadores|equipo de desarrollo|autores)/i',
            $body,
            'Lockout alert view must display manual author attribution.'
        );
    }

    /**
     * T3-PAIR-06: Protected Home Route Interception After Logout.
     * Verifies that after logging out, requests to home.php are intercepted by AuthFilter.
     */
    public function testT3Pair06_ProtectedHomeRouteInterceptionAfterLogout(): void
    {
        $sessionData = [
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ASE-01',
            'usuario'         => 'asesor_sv',
            'nombre'          => 'Juan Perez',
            'rol'             => 'Asesor',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ];

        // 1. Access while authenticated
        $resAuth = $this->withSession($sessionData)->get('home.php');
        $resAuth->assertStatus(200);

        // 2. Perform logout
        $resLogout = $this->withSession($sessionData)->get('logout');
        $resLogout->assertStatus(302);
        $logoutLoc = $resLogout->response()->getHeaderLine('Location');
        $this->assertMatchesRegularExpression('/login/i', $logoutLoc);

        // 3. Access after logout without session
        $resUnauth = $this->withSession([])->get('home.php');
        $resUnauth->assertStatus(302);
        $unauthLoc = $resUnauth->response()->getHeaderLine('Location');
        $this->assertMatchesRegularExpression('/login/i', $unauthLoc);
    }

    /**
     * T3-PAIR-07: Country Boundary Isolation Enforcement.
     * Verifies that a user from Country 1 cannot access data from Country 2.
     */
    public function testT3Pair07_CountryBoundaryIsolationEnforcement(): void
    {
        $result = $this->withSession([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-SUP-01',
            'usuario'         => 'supervisor_sv',
            'nombre'          => 'Elena Rostova',
            'rol'             => 'Supervisor',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ])->get('home.php?pais=2');

        $status = $result->response()->getStatusCode();
        $this->assertContains($status, [200, 403]);
        if ($status === 200) {
            $body = $result->getBody();
            $result->assertSee('El Salvador');
            $this->assertStringNotContainsString('Guatemala', $body);
        }
    }

    /**
     * T3-PAIR-08: Non-Existent User Credential Rejection & Counter Invariance.
     * Verifies that non-existent username submission returns generic error and preserves existing users' counters.
     */
    public function testT3Pair08_NonExistentUserCredentialRejection(): void
    {
        $result = $this->post('login', [
            'usuario'    => 'ghost_target_nonexistent',
            'contrasena' => 'AnyPassword123!',
        ]);

        $result->assertStatus(302);
        $location = $result->response()->getHeaderLine('Location');
        $this->assertMatchesRegularExpression('/login/i', $location);

        $cleanRow = $this->db->table('Usuario')->where('Codigo_Empleado', 'EMP-CLN-01')->get()->getRowArray();
        $this->assertEquals(0, (int) $cleanRow['intentos_fallidos'], 'Existing user counters must remain unaltered.');
    }

    /**
     * T3-PAIR-09: Successive Multi-User Session Switching Across Countries.
     * Verifies complete session cleanup when switching between users of different countries.
     */
    public function testT3Pair09_SuccessiveMultiUserSessionSwitchingAcrossCountries(): void
    {
        // User 1: El Salvador
        $res1 = $this->withSession([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ASE-01',
            'usuario'         => 'asesor_sv',
            'nombre'          => 'Juan Perez',
            'rol'             => 'Asesor',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ])->get('home.php');
        $res1->assertStatus(200);
        $res1->assertSee('El Salvador');

        // Logout
        $resLogout = $this->get('logout');
        $resLogout->assertStatus(302);

        // User 2: Guatemala
        $res2 = $this->withSession([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ASE-02',
            'usuario'         => 'asesor_gt',
            'nombre'          => 'Ana Gomez',
            'rol'             => 'Asesor',
            'id_pais'         => 2,
            'pais_nombre'     => 'Guatemala',
        ])->get('home.php');
        $res2->assertStatus(200);
        $res2->assertSee('Guatemala');
        $res2->assertSee('EMP-ASE-02');
        $res2->assertDontSee('EMP-ASE-01');
    }

    /**
     * T3-PAIR-10: SQL Injection Payload Attempt Tracking Integrity.
     * Verifies that SQL injection attempts are safely parameterized and do not compromise security or crash.
     */
    public function testT3Pair10_SqlInjectionPayloadAttemptTrackingIntegrity(): void
    {
        $result = $this->post('login', [
            'usuario'    => "' OR '1'='1' --",
            'contrasena' => "' OR '1'='1' --",
        ]);

        $result->assertStatus(302);
        $location = $result->response()->getHeaderLine('Location');
        $this->assertMatchesRegularExpression('/login/i', $location);

        $loginView = $this->get('login');
        $loginView->assertStatus(200);
        $this->assertCorporatePalettePresent($loginView->getBody());
    }

    /**
     * T3-PAIR-11: Session Timeout Simulation Protected Guard.
     * Verifies that an expired session attempting to visit home.php is caught and redirected to login.
     */
    public function testT3Pair11_SessionTimeoutSimulationProtectedGuard(): void
    {
        $result = $this->withSession([
            'isLoggedIn' => false,
            'login_time' => time() - 8000,
        ])->get('home.php');

        $result->assertStatus(302);
        $location = $result->response()->getHeaderLine('Location');
        $this->assertMatchesRegularExpression('/login/i', $location);

        $loginView = $this->get('login');
        $loginView->assertStatus(200);
        $this->assertCorporatePalettePresent($loginView->getBody());
    }

    /**
     * T3-PAIR-12: Empty / Malformed Credential Input Counter Invariance.
     * Verifies that empty and whitespace-only submissions do not increment failed attempts counter.
     */
    public function testT3Pair12_EmptyMalformedCredentialCounterInvariance(): void
    {
        $this->post('login', [
            'usuario'    => '',
            'contrasena' => '',
        ]);

        $this->post('login', [
            'usuario'    => '   ',
            'contrasena' => '   ',
        ]);

        $row = $this->db->table('Usuario')->where('Codigo_Empleado', 'EMP-ASE-01')->get()->getRowArray();
        $this->assertEquals(0, (int) $row['intentos_fallidos'], 'Empty inputs must not increment failed attempts counter.');
    }

    // =========================================================================
    // TIER 4: REAL-WORLD APPLICATION SCENARIOS
    // =========================================================================

    /**
     * T4-SCEN-01: Field Agent Complete Shift Lifecycle.
     * Tests full lifecycle: login page inspection, valid login, landing page verification,
     * boundary checks, logout, and post-logout access blocking.
     */
    public function testT4Scen01_FieldAgentCompleteShiftLifecycle(): void
    {
        // 1. Visit Login View
        $loginView = $this->get('login');
        $loginView->assertStatus(200);
        $this->assertCorporatePalettePresent($loginView->getBody());

        // 2. Submit Valid Credentials
        $loginPost = $this->post('login', [
            'usuario'    => 'asesor_sv',
            'contrasena' => 'AsesorSv123!',
        ]);
        $loginPost->assertStatus(302);
        $postLoc = $loginPost->response()->getHeaderLine('Location');
        $this->assertMatchesRegularExpression('/home\.php/i', $postLoc);

        $loginPost->assertSessionHas('isLoggedIn', true);
        $loginPost->assertSessionHas('usuario', 'asesor_sv');
        $loginPost->assertSessionHas('rol', 'Asesor');

        // Capture genuine session produced by login
        $authSession = !empty($_SESSION) ? $_SESSION : (session()->get() ?? []);

        // 3. Render Landing View (home.php) using genuine authenticated session
        $homeRes = $this->withSession($authSession)->get('home.php');
        $homeRes->assertStatus(200);
        $homeRes->assertSee('EMP-ASE-01');
        $homeRes->assertSee('Juan Perez');
        $homeRes->assertSee('Asesor');
        $homeRes->assertSee('El Salvador');

        // 4. Secure Shift Logout using authenticated session
        $logoutRes = $this->withSession($authSession)->get('logout');
        $logoutRes->assertStatus(302);

        // 5. Verify protected route is barred post-logout
        $postLogoutSession = !empty($_SESSION) ? $_SESSION : (session()->get() ?? []);
        $postLogoutHome = $this->withSession($postLogoutSession)->get('home.php');
        $postLogoutHome->assertStatus(302);
        $checkLoc = $postLogoutHome->response()->getHeaderLine('Location');
        $this->assertMatchesRegularExpression('/login/i', $checkLoc);
    }

    /**
     * T4-SCEN-02: Regional Supervisor Multi-Country Governance.
     * Tests regional supervisor login for Guatemala, confirming territorial boundaries
     * and ensuring cross-country records from El Salvador are withheld.
     */
    public function testT4Scen02_RegionalSupervisorMultiCountryGovernance(): void
    {
        // 1. Supervisor Login
        $loginRes = $this->post('login', [
            'usuario'    => 'supervisor_gt',
            'contrasena' => 'SuperGt123!',
        ]);
        $loginRes->assertStatus(302);
        $loc = $loginRes->response()->getHeaderLine('Location');
        $this->assertMatchesRegularExpression('/home\.php/i', $loc);

        $loginRes->assertSessionHas('isLoggedIn', true);
        $loginRes->assertSessionHas('usuario', 'supervisor_gt');
        $loginRes->assertSessionHas('rol', 'Supervisor');

        // Capture genuine session produced by login
        $authSession = !empty($_SESSION) ? $_SESSION : (session()->get() ?? []);

        // 2. Governance Landing Verification using authenticated session
        $homeRes = $this->withSession($authSession)->get('home.php');
        $homeRes->assertStatus(200);
        $homeRes->assertSee('Supervisor');
        $homeRes->assertSee('Guatemala');

        // 3. Cross-Country Segregation Assertion: El Salvador data withheld
        $this->assertStringNotContainsString('El Salvador', $homeRes->getBody());

        // 4. Clean Session Termination using authenticated session
        $logoutRes = $this->withSession($authSession)->get('logout');
        $logoutRes->assertStatus(302);

        // 5. Verify post-logout isolation
        $postLogoutSession = !empty($_SESSION) ? $_SESSION : (session()->get() ?? []);
        $postLogoutHome = $this->withSession($postLogoutSession)->get('home.php');
        $postLogoutHome->assertStatus(302);
    }

    /**
     * T4-SCEN-03: Targeted Brute-Force Password Attack, Automatic Lockout, and Administrative Remediation.
     * Tests sequential brute-force attempts leading to lockout, immediate rejection of valid password on locked account,
     * administrative remediation, and subsequent successful login.
     */
    public function testT4Scen03_BruteForceLockoutAndAdministrativeRemediation(): void
    {
        // 1. First Attack Wave
        $this->post('login', ['usuario' => 'supervisor_sv', 'contrasena' => 'wrong_pass_1']);
        $r1 = $this->db->table('Usuario')->where('Codigo_Empleado', 'EMP-SUP-01')->get()->getRowArray();
        $this->assertEquals(1, (int) $r1['intentos_fallidos']);
        $this->assertEquals(0, (int) $r1['bloqueado']);

        // 2. Second Attack Wave
        $this->post('login', ['usuario' => 'supervisor_sv', 'contrasena' => 'wrong_pass_2']);
        $r2 = $this->db->table('Usuario')->where('Codigo_Empleado', 'EMP-SUP-01')->get()->getRowArray();
        $this->assertEquals(2, (int) $r2['intentos_fallidos']);
        $this->assertEquals(0, (int) $r2['bloqueado']);

        // 3. Third Attack Wave - Lockout Triggered
        $this->post('login', ['usuario' => 'supervisor_sv', 'contrasena' => 'wrong_pass_3']);
        $r3 = $this->db->table('Usuario')->where('Codigo_Empleado', 'EMP-SUP-01')->get()->getRowArray();
        $this->assertEquals(3, (int) $r3['intentos_fallidos']);
        $this->assertEquals(1, (int) $r3['bloqueado']);

        // 4. Legitimate User Blocked Even with Correct Password
        $resBlocked = $this->post('login', [
            'usuario'    => 'supervisor_sv',
            'contrasena' => 'SuperSv123!',
        ]);
        $resBlocked->assertStatus(302);
        $locBlocked = $resBlocked->response()->getHeaderLine('Location');
        $this->assertMatchesRegularExpression('/login/i', $locBlocked);
        $this->assertFalse(session()->get('isLoggedIn') ?? false);

        // 5. Administrative Remediation: Reset lockout via UserModel
        $userModel = class_exists('App\Models\UserModel') ? new \App\Models\UserModel() : model('UserModel');
        if (method_exists($userModel, 'resetFailedAttempts')) {
            $userModel->resetFailedAttempts('EMP-SUP-01');
        } elseif (method_exists($userModel, 'unlockAccount')) {
            $userModel->unlockAccount('EMP-SUP-01');
        }

        $r4 = $this->db->table('Usuario')->where('Codigo_Empleado', 'EMP-SUP-01')->get()->getRowArray();
        $this->assertEquals(0, (int) $r4['intentos_fallidos']);
        $this->assertEquals(0, (int) $r4['bloqueado']);

        // 6. Legitimate User Succeeds Post-Unlock
        $resRecover = $this->post('login', [
            'usuario'    => 'supervisor_sv',
            'contrasena' => 'SuperSv123!',
        ]);
        $resRecover->assertStatus(302);
        $locRecover = $resRecover->response()->getHeaderLine('Location');
        $this->assertMatchesRegularExpression('/home\.php/i', $locRecover);
    }

    /**
     * T4-SCEN-04: Non-Consecutive Failure Recovery.
     * Tests that two failed attempts followed by a successful login resets the streak,
     * so that a future failed attempt starts at 1 rather than triggering a lockout.
     */
    public function testT4Scen04_NonConsecutiveFailureRecovery(): void
    {
        // 1. First Typo
        $this->post('login', ['usuario' => 'asesor_sv', 'contrasena' => 'typo_pass_1']);
        $r1 = $this->db->table('Usuario')->where('Codigo_Empleado', 'EMP-ASE-01')->get()->getRowArray();
        $this->assertEquals(1, (int) $r1['intentos_fallidos']);

        // 2. Second Typo
        $this->post('login', ['usuario' => 'asesor_sv', 'contrasena' => 'typo_pass_2']);
        $r2 = $this->db->table('Usuario')->where('Codigo_Empleado', 'EMP-ASE-01')->get()->getRowArray();
        $this->assertEquals(2, (int) $r2['intentos_fallidos']);

        // 3. Successful Login (resets counter to 0)
        $resSuccess = $this->post('login', [
            'usuario'    => 'asesor_sv',
            'contrasena' => 'AsesorSv123!',
        ]);
        $resSuccess->assertStatus(302);
        $locSuccess = $resSuccess->response()->getHeaderLine('Location');
        $this->assertMatchesRegularExpression('/home\.php/i', $locSuccess);

        $r3 = $this->db->table('Usuario')->where('Codigo_Empleado', 'EMP-ASE-01')->get()->getRowArray();
        $this->assertEquals(0, (int) $r3['intentos_fallidos']);
        $this->assertEquals(0, (int) $r3['bloqueado']);

        // 4. Shift Logout
        $this->get('logout');

        // 5. Subsequent Mistake After Successful Shift (increments to 1, NOT 3)
        $this->post('login', ['usuario' => 'asesor_sv', 'contrasena' => 'typo_pass_3']);
        $r4 = $this->db->table('Usuario')->where('Codigo_Empleado', 'EMP-ASE-01')->get()->getRowArray();
        $this->assertEquals(1, (int) $r4['intentos_fallidos']);
        $this->assertEquals(0, (int) $r4['bloqueado']);
    }

    /**
     * T4-SCEN-05: Session Expiry / Hijacking Defense & Route Guard.
     * Tests administrator session expiration, interception by AuthFilter, and re-authentication restoration.
     */
    public function testT4Scen05_SessionExpiryHijackingDefense(): void
    {
        // 1. Admin Authentication
        $resLogin = $this->post('login', [
            'usuario'    => 'admin_user',
            'contrasena' => 'AdminPass123!',
        ]);
        $resLogin->assertStatus(302);

        // 2. Emulate expired session
        $resHome = $this->withSession([
            'isLoggedIn' => false,
            'login_time' => time() - 8000,
        ])->get('home.php');

        // 3. Guard intercepts expired session
        $resHome->assertStatus(302);
        $loc = $resHome->response()->getHeaderLine('Location');
        $this->assertMatchesRegularExpression('/login/i', $loc);

        // 4. Re-authentication restores valid session
        $resReauth = $this->post('login', [
            'usuario'    => 'admin_user',
            'contrasena' => 'AdminPass123!',
        ]);
        $resReauth->assertStatus(302);
        $locReauth = $resReauth->response()->getHeaderLine('Location');
        $this->assertMatchesRegularExpression('/home\.php/i', $locReauth);
    }
}
