<?php

namespace Tests\Feature;

use Tests\Support\AuthTestCase;

/**
 * Authentication and Login Test Suite (Requirement R1, R3, R5)
 *
 * Covers Tier 1 (Feature Coverage) and Tier 2 (Boundary & Corner Cases)
 * for user authentication against in-memory SQLite3.
 */
class AuthTest extends AuthTestCase
{
    // =========================================================================
    // TIER 1: FEATURE COVERAGE (HAPPY PATH & FOUNDATIONAL NEGATIVE TESTS)
    // =========================================================================

    /**
     * T1-AUTH-01: Valid Administrator Login
     * Authenticate with valid credentials for an active Administrador user (admin_sv).
     * Verifies HTTP 302 redirect to home.php, session role and country scoping, and DB audit.
     */
    public function testT1Auth01_ValidAdminLogin(): void
    {
        $result = $this->post('/login', [
            'usuario'    => 'admin_sv',
            'contrasena' => 'AdminPass123!',
        ]);

        $result->assertStatus(302);
        $result->assertRedirect();
        $this->assertStringContainsString('home.php', (string) $result->getRedirectUrl());

        $result->assertSessionHas('isLoggedIn', true);
        $result->assertSessionHas('usuario', 'admin_sv');
        $result->assertSessionHas('codigo_empleado', 'EMP-ADM-01');
        $result->assertSessionHas('rol', 'Administrador');
        $result->assertSessionHas('id_pais', 1);

        $this->seeInDatabase('Usuario', [
            'Usuario'           => 'admin_sv',
            'intentos_fallidos' => 0,
            'bloqueado'         => 0,
        ]);

        $user = $this->db->table('Usuario')->where('Usuario', 'admin_sv')->get()->getRowArray();
        $this->assertNotNull($user['ultimo_login']);
    }

    /**
     * T1-AUTH-02: Valid Supervisor Login
     * Authenticate with valid credentials for an active Supervisor user in Guatemala (supervisor_gt).
     * Verifies HTTP 302 redirect to home.php and proper supervisor role and country 2 scoping.
     */
    public function testT1Auth02_ValidSupervisorLogin(): void
    {
        $result = $this->post('/login', [
            'usuario'    => 'supervisor_gt',
            'contrasena' => 'SuperGt123!',
        ]);

        $result->assertStatus(302);
        $result->assertRedirect();
        $this->assertStringContainsString('home.php', (string) $result->getRedirectUrl());

        $result->assertSessionHas('isLoggedIn', true);
        $result->assertSessionHas('rol', 'Supervisor');
        $result->assertSessionHas('id_pais', 2);
        $result->assertSessionHas('pais_nombre', 'Guatemala');

        $this->seeInDatabase('Usuario', [
            'Usuario'           => 'supervisor_gt',
            'intentos_fallidos' => 0,
            'bloqueado'         => 0,
        ]);

        $user = $this->db->table('Usuario')->where('Usuario', 'supervisor_gt')->get()->getRowArray();
        $this->assertNotNull($user['ultimo_login']);
    }

    /**
     * T1-AUTH-03: Valid Asesor Login
     * Authenticate with valid credentials for an active Asesor user in Honduras (asesor_hn).
     * Verifies HTTP 302 redirect to home.php and proper asesor role and country 3 scoping.
     */
    public function testT1Auth03_ValidAsesorLogin(): void
    {
        $result = $this->post('/login', [
            'usuario'    => 'asesor_hn',
            'contrasena' => 'AsesorHn123!',
        ]);

        $result->assertStatus(302);
        $result->assertRedirect();
        $this->assertStringContainsString('home.php', (string) $result->getRedirectUrl());

        $result->assertSessionHas('isLoggedIn', true);
        $result->assertSessionHas('rol', 'Asesor');
        $result->assertSessionHas('id_pais', 3);
        $result->assertSessionHas('pais_nombre', 'Honduras');

        $this->seeInDatabase('Usuario', [
            'Usuario'           => 'asesor_hn',
            'intentos_fallidos' => 0,
            'bloqueado'         => 0,
        ]);

        $user = $this->db->table('Usuario')->where('Usuario', 'asesor_hn')->get()->getRowArray();
        $this->assertNotNull($user['ultimo_login']);
    }

    /**
     * T1-AUTH-04: Invalid Password Rejection (Single Failure)
     * Attempt login with an existing valid username but an incorrect password.
     * Verifies HTTP 302 redirect to login, session rejection, and DB failure count increment to 1.
     */
    public function testT1Auth04_InvalidPasswordSingleFailure(): void
    {
        $result = $this->post('/login', [
            'usuario'    => 'admin_sv',
            'contrasena' => 'WrongPassword!',
        ]);

        $result->assertStatus(302);
        $result->assertRedirect();
        $this->assertStringContainsString('login', (string) $result->getRedirectUrl());

        $this->assertFalse((bool) (session('isLoggedIn') ?? false));

        $this->seeInDatabase('Usuario', [
            'Usuario'           => 'admin_sv',
            'intentos_fallidos' => 1,
            'bloqueado'         => 0,
        ]);

        $user = $this->db->table('Usuario')->where('Usuario', 'admin_sv')->get()->getRowArray();
        $this->assertNotNull($user['ultimo_fallo']);
    }

    /**
     * T1-AUTH-05: Non-Existent Username Rejection
     * Attempt login with a username not present in the database.
     * Verifies generic error redirect, no active session, and database users untouched.
     */
    public function testT1Auth05_NonExistentUsernameRejection(): void
    {
        $result = $this->post('/login', [
            'usuario'    => 'usuario_fantasma',
            'contrasena' => 'Cualquiera123!',
        ]);

        $result->assertStatus(302);
        $result->assertRedirect();
        $this->assertStringContainsString('login', (string) $result->getRedirectUrl());

        $this->assertFalse((bool) (session('isLoggedIn') ?? false));

        $this->dontSeeInDatabase('Usuario', [
            'Usuario' => 'usuario_fantasma',
        ]);
    }

    /**
     * T1-AUTH-06: Login Form View Rendering
     * Retrieve the login page via HTTP GET.
     * Verifies HTTP 200, form action, input fields for credentials, and submission element.
     */
    public function testT1Auth06_LoginFormViewRendering(): void
    {
        $result = $this->get('/login');

        $result->assertStatus(200);
        $body = (string) $result->getBody();

        $this->assertStringContainsString('<form', $body);
        $this->assertMatchesRegularExpression('/name=[\'"]usuario[\'"]/i', $body);
        $this->assertMatchesRegularExpression('/name=[\'"]contrasena[\'"]/i', $body);
        $this->assertMatchesRegularExpression('/type=[\'"]submit[\'"]|<button/i', $body);
    }

    // =========================================================================
    // TIER 2: BOUNDARY & CORNER CASES
    // =========================================================================

    /**
     * T2-AUTH-01: Empty Form Submission
     * POST to /login with empty strings for both usuario and contrasena.
     * Verifies rejection without incrementing database failure counters.
     */
    public function testT2Auth01_EmptyFormSubmission(): void
    {
        $result = $this->post('/login', [
            'usuario'    => '',
            'contrasena' => '',
        ]);

        $this->assertTrue(in_array($result->response()->getStatusCode(), [200, 302], true));
        $this->assertFalse((bool) (session('isLoggedIn') ?? false));

        $this->seeInDatabase('Usuario', [
            'Usuario'           => 'admin_sv',
            'intentos_fallidos' => 0,
        ]);
    }

    /**
     * T2-AUTH-02: Whitespace-Only Inputs
     * POST to /login with whitespace strings.
     * Verifies trimmed empty inputs are rejected without modifying database state.
     */
    public function testT2Auth02_WhitespaceOnlyInputs(): void
    {
        $result = $this->post('/login', [
            'usuario'    => '   ',
            'contrasena' => '   ',
        ]);

        $this->assertTrue(in_array($result->response()->getStatusCode(), [200, 302], true));
        $this->assertFalse((bool) (session('isLoggedIn') ?? false));

        $this->seeInDatabase('Usuario', [
            'Usuario'           => 'admin_sv',
            'intentos_fallidos' => 0,
        ]);
    }

    /**
     * T2-AUTH-03: SQL Injection in Username Field
     * Submit classic SQL injection vectors in the username field.
     * Verifies prepared statement resilience and generic authentication rejection.
     */
    public function testT2Auth03_SqlInjectionInUsernameField(): void
    {
        $result = $this->post('/login', [
            'usuario'    => "' OR '1'='1' --",
            'contrasena' => 'any_password',
        ]);

        $this->assertTrue(in_array($result->response()->getStatusCode(), [200, 302], true));
        $this->assertFalse((bool) (session('isLoggedIn') ?? false));

        $this->seeInDatabase('Usuario', [
            'Usuario'   => 'admin_sv',
            'bloqueado' => 0,
        ]);
    }

    /**
     * T2-AUTH-04: Extremely Long Input Strings (Buffer / Length Boundary)
     * Submit oversized inputs (500-char username, 10,000-char password).
     * Verifies application handles buffer boundaries gracefully without fatal errors.
     */
    public function testT2Auth04_ExtremelyLongInputStrings(): void
    {
        $result = $this->post('/login', [
            'usuario'    => str_repeat('A', 500),
            'contrasena' => str_repeat('P', 10000),
        ]);

        $this->assertTrue(in_array($result->response()->getStatusCode(), [200, 302], true));
        $this->assertFalse((bool) (session('isLoggedIn') ?? false));
    }

    /**
     * T2-AUTH-05: Case Sensitivity Verification
     * Passwords must be strictly case-sensitive.
     * Verifies that incorrect case in password results in authentication failure.
     */
    public function testT2Auth05_CaseSensitivityVerification(): void
    {
        $result = $this->post('/login', [
            'usuario'    => 'admin_sv',
            'contrasena' => 'adminpass123!', // Lowercase; correct is AdminPass123!
        ]);

        $result->assertStatus(302);
        $result->assertRedirect();
        $this->assertStringContainsString('login', (string) $result->getRedirectUrl());

        $this->assertFalse((bool) (session('isLoggedIn') ?? false));

        $this->seeInDatabase('Usuario', [
            'Usuario'           => 'admin_sv',
            'intentos_fallidos' => 1,
            'bloqueado'         => 0,
        ]);
    }

    /**
     * T2-AUTH-06: Special & Multibyte Characters in Password
     * Authenticate user with complex password containing accents, symbols, and multibyte chars.
     * Verifies correct verification by bcrypt without character set corruption.
     */
    public function testT2Auth06_SpecialAndMultibyteCharactersInPassword(): void
    {
        $result = $this->post('/login', [
            'usuario'    => 'special_pwd',
            'contrasena' => 'P@$$w0rd!#%^&*()_+ñáéíóú',
        ]);

        $result->assertStatus(302);
        $result->assertRedirect();
        $this->assertStringContainsString('home.php', (string) $result->getRedirectUrl());

        $result->assertSessionHas('isLoggedIn', true);
        $result->assertSessionHas('usuario', 'special_pwd');
    }
}
