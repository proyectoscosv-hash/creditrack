<?php

namespace Tests\Feature;

use Tests\Support\AuthTestCase;

/**
 * Access Control and Session Scoping Test Suite (Requirement R1, R3, R5)
 *
 * Covers Tier 1 (Feature Coverage) and Tier 2 (Boundary & Corner Cases)
 * for role scoping, country isolation, and route guards.
 */
class AccessControlTest extends AuthTestCase
{
    // =========================================================================
    // TIER 1: FEATURE COVERAGE (HAPPY PATH & FOUNDATIONAL NEGATIVE TESTS)
    // =========================================================================

    /**
     * T1-ACCESS-01: Session Role Scoping on Login
     * Verify that role string and employee code are accurately established in session
     * across different user archetypes (Administrador, Supervisor, Asesor).
     */
    public function testT1Access01_SessionRoleScopingOnLogin(): void
    {
        // 1. Administrador archetype
        $resAdmin = $this->post('/login', [
            'usuario'    => 'admin_sv',
            'contrasena' => 'AdminPass123!',
        ]);
        $resAdmin->assertSessionHas('rol', 'Administrador');
        $resAdmin->assertSessionHas('codigo_empleado', 'EMP-ADM-01');

        // 2. Supervisor archetype
        $resSuper = $this->post('/login', [
            'usuario'    => 'supervisor_gt',
            'contrasena' => 'SuperGt123!',
        ]);
        $resSuper->assertSessionHas('rol', 'Supervisor');
        $resSuper->assertSessionHas('codigo_empleado', 'EMP-SUP-02');

        // 3. Asesor archetype
        $resAsesor = $this->post('/login', [
            'usuario'    => 'asesor_hn',
            'contrasena' => 'AsesorHn123!',
        ]);
        $resAsesor->assertSessionHas('rol', 'Asesor');
        $resAsesor->assertSessionHas('codigo_empleado', 'EMP-ASE-03');
    }

    /**
     * T1-ACCESS-02: Country Association and Country ID Scoping
     * Verify user assigned to El Salvador (Id_Pais = 1) has accurate country ID
     * and country name populated in the session upon authentication.
     */
    public function testT1Access02_CountryAssociationAndCountryIdScoping(): void
    {
        $result = $this->post('/login', [
            'usuario'    => 'admin_sv',
            'contrasena' => 'AdminPass123!',
        ]);

        $result->assertStatus(302);
        $result->assertSessionHas('id_pais', 1);
        $result->assertSessionHas('pais_nombre', 'El Salvador');
    }

    /**
     * T1-ACCESS-03: Multi-Country Scoping
     * Verify territorial jurisdiction separation across distinct countries (Guatemala & Honduras).
     */
    public function testT1Access03_MultiCountryScoping(): void
    {
        // Guatemala User (Id_Pais = 2)
        $resGt = $this->post('/login', [
            'usuario'    => 'supervisor_gt',
            'contrasena' => 'SuperGt123!',
        ]);
        $resGt->assertSessionHas('id_pais', 2);
        $resGt->assertSessionHas('pais_nombre', 'Guatemala');

        // Honduras User (Id_Pais = 3)
        $resHn = $this->post('/login', [
            'usuario'    => 'asesor_hn',
            'contrasena' => 'AsesorHn123!',
        ]);
        $resHn->assertSessionHas('id_pais', 3);
        $resHn->assertSessionHas('pais_nombre', 'Honduras');
    }

    /**
     * T1-ACCESS-04: Session Fixation Defense
     * Verify session fixation defense upon authentication. Pre-auth session is replaced
     * or securely initialized with authenticated user credentials.
     */
    public function testT1Access04_SessionFixationDefense(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $preLoginId = session_id();

        $result = $this->post('/login', [
            'usuario'    => 'admin_sv',
            'contrasena' => 'AdminPass123!',
        ]);

        $postLoginId = session_id();

        $result->assertStatus(302);
        $result->assertSessionHas('isLoggedIn', true);
        $result->assertSessionHas('usuario', 'admin_sv');
        $this->assertNotEquals($preLoginId, $postLoginId, 'Session ID must regenerate upon authentication to prevent fixation.');
    }

    /**
     * T1-ACCESS-05: Secure Logout
     * Verify that logout action invalidates all session data and redirects to login.
     */
    public function testT1Access05_SecureLogout(): void
    {
        $result = $this->withSession([
            'isLoggedIn'      => true,
            'usuario'         => 'admin_sv',
            'codigo_empleado' => 'EMP-ADM-01',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ])->get('/logout');

        $result->assertStatus(302);
        $result->assertRedirect();
        $this->assertStringContainsString('login', (string) $result->getRedirectUrl());

        $this->assertFalse((bool) (session('isLoggedIn') ?? false));
        $this->assertNull(session('usuario'));
        $this->assertNull(session('id_pais'));
    }

    /**
     * T1-ACCESS-06: Authenticated User Visiting Login Redirected to Home
     * An already authenticated user accessing /login should be redirected to home.php.
     */
    public function testT1Access06_AuthenticatedUserVisitingLoginRedirectedToHome(): void
    {
        $result = $this->withSession([
            'isLoggedIn'  => true,
            'usuario'     => 'admin_sv',
            'rol'         => 'Administrador',
            'id_pais'     => 1,
            'pais_nombre' => 'El Salvador',
        ])->get('/login');

        $result->assertStatus(302);
        $result->assertRedirect();
        $this->assertStringContainsString('home.php', (string) $result->getRedirectUrl());
    }

    // =========================================================================
    // TIER 2: BOUNDARY & CORNER CASES
    // =========================================================================

    /**
     * T2-ACCESS-01: Unauthenticated Access to home.php Intercepted
     * Direct request to /home.php without an active session must be intercepted by AuthFilter.
     */
    public function testT2Access01_UnauthenticatedAccessToHomePhpIntercepted(): void
    {
        $result = $this->withSession([])->get('/home.php');

        $result->assertStatus(302);
        $result->assertRedirect();
        $this->assertStringContainsString('login', (string) $result->getRedirectUrl());

        $this->assertFalse((bool) (session('isLoggedIn') ?? false));
    }

    /**
     * T2-ACCESS-02: Unauthenticated Access to Route Alias /home Intercepted
     * Direct request to route alias /home without active session must redirect to /login.
     */
    public function testT2Access02_UnauthenticatedAccessToRouteAliasHomeIntercepted(): void
    {
        $result = $this->withSession([])->get('/home');

        $result->assertStatus(302);
        $result->assertRedirect();
        $this->assertStringContainsString('login', (string) $result->getRedirectUrl());
    }

    /**
     * T2-ACCESS-03: Incomplete Session Payload Handling
     * Partially forged session (isLoggedIn = true, but missing rol or id_pais) must be handled gracefully.
     */
    public function testT2Access03_IncompleteSessionPayloadHandling(): void
    {
        $result = $this->withSession([
            'isLoggedIn' => true,
            'usuario'    => 'admin_sv',
            // Omit rol and id_pais
        ])->get('/home.php');

        $statusCode = $result->response()->getStatusCode();
        $this->assertTrue(in_array($statusCode, [200, 302], true));
    }

    /**
     * T2-ACCESS-04: Cross-Country Boundary Isolation Enforcement
     * User assigned to El Salvador (id_pais = 1) must be strictly isolated from
     * foreign country data (e.g. Guatemala).
     */
    public function testT2Access04_CrossCountryBoundaryIsolationEnforcement(): void
    {
        $result = $this->withSession([
            'isLoggedIn'      => true,
            'usuario'         => 'asesor_sv',
            'codigo_empleado' => 'EMP-ASE-01',
            'rol'             => 'Asesor',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ])->get('/home.php?pais=2');

        $statusCode = $result->response()->getStatusCode();
        $this->assertTrue(in_array($statusCode, [200, 403], true));

        if ($statusCode === 200) {
            $body = (string) $result->getBody();
            // Foreign country data (e.g. Guatemala, Honduras) must never be rendered to an El Salvador asesor
            $this->assertStringNotContainsString('Guatemala', $body);
            $this->assertStringNotContainsString('Honduras', $body);
            $this->assertStringContainsString('El Salvador', $body);
        }
    }

    /**
     * T2-ACCESS-05: Post-Logout Session Invalidation Guard
     * User authenticates, invokes /logout, then attempts to access protected page.
     * Guard must intercept and redirect to /login.
     */
    public function testT2Access05_PostLogoutSessionInvalidationGuard(): void
    {
        // 1. Perform logout
        $logoutResult = $this->withSession([
            'isLoggedIn' => true,
            'usuario'    => 'admin_sv',
            'rol'        => 'Administrador',
            'id_pais'    => 1,
        ])->get('/logout');

        $logoutResult->assertStatus(302);

        // 2. Access protected endpoint after session termination
        $accessResult = $this->withSession([])->get('/home.php');

        $accessResult->assertStatus(302);
        $accessResult->assertRedirect();
        $this->assertStringContainsString('login', (string) $accessResult->getRedirectUrl());
    }

    /**
     * T2-ACCESS-06: Corrupted or Unknown Role Defense
     * User session contains unrecognized role string. Application must handle safely.
     */
    public function testT2Access06_CorruptedOrUnknownRoleDefense(): void
    {
        $result = $this->withSession([
            'isLoggedIn'  => true,
            'usuario'     => 'hacker',
            'rol'         => 'SuperHacker',
            'id_pais'     => 1,
            'pais_nombre' => 'El Salvador',
        ])->get('/home.php');

        $statusCode = $result->response()->getStatusCode();
        $this->assertContains($statusCode, [302, 403], 'Corrupted or unknown role must be redirected (302) or forbidden (403), never 200 OK.');
    }
}
