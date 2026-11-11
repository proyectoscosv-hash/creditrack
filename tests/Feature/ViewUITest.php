<?php

namespace Tests\Feature;

use Tests\Support\AuthTestCase;

/**
 * ViewUITest
 *
 * Implements Tier 1 and Tier 2 test specifications for View UI,
 * Post-Authentication Redirection, Corporate 5-Color Palette,
 * Manual Author Attribution, and Landing View Session Confirmations.
 *
 * Requirements Covered:
 * - R3: Redirection and destination page (home.php), session status confirmation.
 * - R4: Visual corporate palette (#BF0426, #0511F2, #023373, #A61717, #8D253C)
 *      and visible manual author attribution markup.
 * - R5: In-memory SQLite execution with zero .env access.
 *
 * @internal
 */
final class ViewUITest extends AuthTestCase
{
    // =========================================================================
    // TIER 1: FEATURE COVERAGE (HAPPY PATH & CORE REQUIREMENTS)
    // =========================================================================

    /**
     * T1-REDIR-01: HTTP 302 Redirection to home.php Post-Authentication.
     * Verifies that valid login credentials result in an immediate HTTP 302 redirect to home.php.
     */
    public function testT1Redir01_Http302RedirectionToHomePhpPostAuth(): void
    {
        $result = $this->post('login', [
            'usuario'    => 'admin_sv',
            'contrasena' => 'AdminPass123!',
        ]);

        $result->assertStatus(302);
        $this->assertTrue($result->isRedirect(), 'Response must be an HTTP redirect.');

        $location = $result->response()->getHeaderLine('Location');
        $this->assertMatchesRegularExpression(
            '/home\.php/i',
            $location,
            'Successful authentication must issue HTTP 302 redirecting to home.php.'
        );
    }

    /**
     * T1-REDIR-02: Successful Authentication Confirmation Banner on home.php.
     * Verifies that an authenticated user landing on home.php sees a success confirmation banner.
     */
    public function testT1Redir02_SuccessfulAuthConfirmationBannerOnHomePhp(): void
    {
        $result = $this->withSession([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ADM-01',
            'usuario'         => 'admin_sv',
            'nombre'          => 'Carlos Admin',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ])->get('home.php');

        $result->assertStatus(200);
        $body = $result->getBody();
        $this->assertMatchesRegularExpression(
            '/(¡?inicio de sesi[oó]n exitoso!?|bienvenido|sesi[oó]n iniciada)/i',
            $body,
            'home.php must render a prominent successful authentication confirmation banner.'
        );
    }

    /**
     * T1-REDIR-03: Active Session Health Status Badge on home.php.
     * Verifies that home.php renders an explicit session health status confirmation badge.
     */
    public function testT1Redir03_ActiveSessionHealthStatusBadgeOnHomePhp(): void
    {
        $result = $this->withSession([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ADM-01',
            'usuario'         => 'admin_sv',
            'nombre'          => 'Carlos Admin',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ])->get('home.php');

        $result->assertStatus(200);
        $body = $result->getBody();
        $this->assertMatchesRegularExpression(
            '/(estado de la sesi[oó]n:\s*activa|sesi[oó]n\s*activa)/i',
            $body,
            'home.php must render an explicit active session status confirmation badge.'
        );
    }

    /**
     * T1-REDIR-04: User Identity Rendering on home.php.
     * Verifies that home.php renders employee code, full user name, and username.
     */
    public function testT1Redir04_UserIdentityRenderingOnHomePhp(): void
    {
        $result = $this->withSession([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ADM-01',
            'usuario'         => 'admin_sv',
            'nombre'          => 'Carlos Admin',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ])->get('home.php');

        $result->assertStatus(200);
        $result->assertSee('EMP-ADM-01');
        $result->assertSee('Carlos Admin');
        $result->assertSee('admin_sv');
    }

    /**
     * T1-REDIR-05: Assigned Role and Country Confirmation Badges.
     * Verifies that home.php explicitly displays assigned role and country badges.
     */
    public function testT1Redir05_AssignedRoleAndCountryConfirmationBadges(): void
    {
        $result = $this->withSession([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-SUP-02',
            'usuario'         => 'supervisor_gt',
            'nombre'          => 'Beatriz Supervisor',
            'rol'             => 'Supervisor',
            'id_pais'         => 2,
            'pais_nombre'     => 'Guatemala',
        ])->get('home.php');

        $result->assertStatus(200);
        $body = $result->getBody();
        $this->assertMatchesRegularExpression(
            '/(rol|rol asignado):?\s*supervisor/i',
            $body,
            'home.php must display the assigned role badge.'
        );
        $this->assertMatchesRegularExpression(
            '/(pa[ií]s|pa[ií]s asignado):?\s*guatemala/i',
            $body,
            'home.php must display the assigned country badge.'
        );
    }

    /**
     * T1-REDIR-06: Access Boundary Declaration Statement on home.php.
     * Verifies that home.php renders the explicit geographical/operational access boundary statement.
     */
    public function testT1Redir06_AccessBoundaryDeclarationStatementOnHomePhp(): void
    {
        $result = $this->withSession([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ASE-03',
            'usuario'         => 'asesor_hn',
            'nombre'          => 'Alejandro Asesor',
            'rol'             => 'Asesor',
            'id_pais'         => 3,
            'pais_nombre'     => 'Honduras',
        ])->get('home.php');

        $result->assertStatus(200);
        $body = $result->getBody();
        $this->assertMatchesRegularExpression(
            '/(acceso restringido.*honduras|l[ií]mite territorial.*honduras|operaciones de honduras)/i',
            $body,
            'home.php must declare the geographical / role access boundary statement.'
        );
    }

    /**
     * T1-UI-01: Corporate Color #BF0426 Presence in Views.
     * Verifies hex color #BF0426 is present in stylesheets or markup of login and home views.
     */
    public function testT1Ui01_CorporateColorBF0426PresenceInViews(): void
    {
        $loginRes = $this->get('login');
        $loginRes->assertStatus(200);
        $this->assertMatchesRegularExpression(
            '/#BF0426/i',
            $loginRes->getBody(),
            'Corporate color #BF0426 must be present in login view.'
        );

        $homeRes = $this->withSession([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ADM-01',
            'usuario'         => 'admin_sv',
            'nombre'          => 'Carlos Admin',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ])->get('home.php');
        $homeRes->assertStatus(200);
        $this->assertMatchesRegularExpression(
            '/#BF0426/i',
            $homeRes->getBody(),
            'Corporate color #BF0426 must be present in home.php view.'
        );
    }

    /**
     * T1-UI-02: Corporate Color #0511F2 Presence in Views.
     * Verifies hex color #0511F2 is present in stylesheets or markup of login and home views.
     */
    public function testT1Ui02_CorporateColor0511F2PresenceInViews(): void
    {
        $loginRes = $this->get('login');
        $loginRes->assertStatus(200);
        $this->assertMatchesRegularExpression(
            '/#0511F2/i',
            $loginRes->getBody(),
            'Corporate color #0511F2 must be present in login view.'
        );

        $homeRes = $this->withSession([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ADM-01',
            'usuario'         => 'admin_sv',
            'nombre'          => 'Carlos Admin',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ])->get('home.php');
        $homeRes->assertStatus(200);
        $this->assertMatchesRegularExpression(
            '/#0511F2/i',
            $homeRes->getBody(),
            'Corporate color #0511F2 must be present in home.php view.'
        );
    }

    /**
     * T1-UI-03: Corporate Color #023373 Presence in Views.
     * Verifies hex color #023373 is present in stylesheets or markup of login and home views.
     */
    public function testT1Ui03_CorporateColor023373PresenceInViews(): void
    {
        $loginRes = $this->get('login');
        $loginRes->assertStatus(200);
        $this->assertMatchesRegularExpression(
            '/#023373/i',
            $loginRes->getBody(),
            'Corporate color #023373 must be present in login view.'
        );

        $homeRes = $this->withSession([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ADM-01',
            'usuario'         => 'admin_sv',
            'nombre'          => 'Carlos Admin',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ])->get('home.php');
        $homeRes->assertStatus(200);
        $this->assertMatchesRegularExpression(
            '/#023373/i',
            $homeRes->getBody(),
            'Corporate color #023373 must be present in home.php view.'
        );
    }

    /**
     * T1-UI-04: Corporate Color #A61717 Presence in Views.
     * Verifies hex color #A61717 is present in stylesheets or markup of login and home views.
     */
    public function testT1Ui04_CorporateColorA61717PresenceInViews(): void
    {
        $loginRes = $this->get('login');
        $loginRes->assertStatus(200);
        $this->assertMatchesRegularExpression(
            '/#A61717/i',
            $loginRes->getBody(),
            'Corporate color #A61717 must be present in login view.'
        );

        $homeRes = $this->withSession([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ADM-01',
            'usuario'         => 'admin_sv',
            'nombre'          => 'Carlos Admin',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ])->get('home.php');
        $homeRes->assertStatus(200);
        $this->assertMatchesRegularExpression(
            '/#A61717/i',
            $homeRes->getBody(),
            'Corporate color #A61717 must be present in home.php view.'
        );
    }

    /**
     * T1-UI-05: Corporate Color #8D253C Presence in Views.
     * Verifies hex color #8D253C is present in stylesheets or markup of login and home views.
     */
    public function testT1Ui05_CorporateColor8D253CPresenceInViews(): void
    {
        $loginRes = $this->get('login');
        $loginRes->assertStatus(200);
        $this->assertMatchesRegularExpression(
            '/#8D253C/i',
            $loginRes->getBody(),
            'Corporate color #8D253C must be present in login view.'
        );

        $homeRes = $this->withSession([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ADM-01',
            'usuario'         => 'admin_sv',
            'nombre'          => 'Carlos Admin',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ])->get('home.php');
        $homeRes->assertStatus(200);
        $this->assertMatchesRegularExpression(
            '/#8D253C/i',
            $homeRes->getBody(),
            'Corporate color #8D253C must be present in home.php view.'
        );
    }

    /**
     * T1-UI-06: Manual Author Attribution Markup on Login View.
     * Verifies static manual designer attribution markup in login.php.
     */
    public function testT1Ui06_ManualAuthorAttributionMarkupOnLoginView(): void
    {
        $result = $this->get('login');
        $result->assertStatus(200);
        $body = $result->getBody();
        $this->assertMatchesRegularExpression(
            '/(diseñad[oa]|desarrollad[oa]|diseñadores|equipo de desarrollo|autores)/i',
            $body,
            'Login view must include manual author/designer attribution markup.'
        );
    }

    /**
     * T1-UI-07: Manual Author Attribution Markup on Landing View.
     * Verifies static manual designer attribution markup in home.php.
     */
    public function testT1Ui07_ManualAuthorAttributionMarkupOnLandingView(): void
    {
        $result = $this->withSession([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ADM-01',
            'usuario'         => 'admin_sv',
            'nombre'          => 'Carlos Admin',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ])->get('home.php');

        $result->assertStatus(200);
        $body = $result->getBody();
        $this->assertMatchesRegularExpression(
            '/(diseñad[oa]|desarrollad[oa]|diseñadores|equipo de desarrollo|autores)/i',
            $body,
            'home.php landing view must include manual author/designer attribution markup.'
        );
    }

    // =========================================================================
    // TIER 2: BOUNDARY & CORNER CASES
    // =========================================================================

    /**
     * T2-REDIR-01: Direct Route Parity home.php vs home.
     * Verifies both /home.php and /home routes render identically with active session.
     */
    public function testT2Redir01_DirectRouteParityHomePhpVsHome(): void
    {
        $session = [
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ADM-01',
            'usuario'         => 'admin_sv',
            'nombre'          => 'Carlos Admin',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ];

        $resHomePhp = $this->withSession($session)->get('home.php');
        $resHomePhp->assertStatus(200);

        $resHome = $this->withSession($session)->get('home');
        $resHome->assertStatus(200);

        $resHomePhp->assertSee('Carlos Admin');
        $resHome->assertSee('Carlos Admin');
        $resHomePhp->assertSee('Administrador');
        $resHome->assertSee('Administrador');
    }

    /**
     * T2-REDIR-02: HTML Escaping / XSS Prevention in Profile.
     * Verifies that profile attributes containing XSS vectors are HTML-escaped and not rendered raw.
     */
    public function testT2Redir02_HtmlEscapingAndXssPreventionInProfile(): void
    {
        $result = $this->withSession([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-XSS-01',
            'usuario'         => 'xss_user',
            'nombre'          => 'Juan <script>alert(1)</script>',
            'rol'             => 'Asesor',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ])->get('home.php');

        $result->assertStatus(200);
        $body = $result->getBody();

        $this->assertStringNotContainsString(
            '<script>alert(1)</script>',
            $body,
            'Raw unescaped script tag must never be rendered in home.php.'
        );
        $this->assertTrue(
            str_contains($body, '&lt;script&gt;alert(1)&lt;/script&gt;') || !str_contains($body, '<script>'),
            'XSS payload in user profile must be HTML-escaped or sanitized.'
        );
    }

    /**
     * T2-REDIR-03: Direct Access to home.php with Expired Session.
     * Verifies that an unauthenticated or expired session attempting to visit home.php is redirected to login.
     */
    public function testT2Redir03_DirectAccessToHomePhpWithExpiredSession(): void
    {
        $result = $this->withSession([
            'isLoggedIn' => false,
            'login_time' => time() - 8000,
        ])->get('home.php');

        $result->assertStatus(302);
        $this->assertTrue($result->isRedirect());
        $location = $result->response()->getHeaderLine('Location');
        $this->assertMatchesRegularExpression('/login/i', $location);
    }

    /**
     * T2-REDIR-04: Logout Action Functional Link Presence.
     * Verifies that home.php renders a visible and actionable logout link or form.
     */
    public function testT2Redir04_LogoutActionFunctionalLinkPresence(): void
    {
        $result = $this->withSession([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ADM-01',
            'usuario'         => 'admin_sv',
            'nombre'          => 'Carlos Admin',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ])->get('home.php');

        $result->assertStatus(200);
        $body = $result->getBody();
        $this->assertMatchesRegularExpression(
            '/(href="[^"]*logout[^"]*"|action="[^"]*logout[^"]*")/i',
            $body,
            'home.php must provide a functional logout link or form.'
        );
        $this->assertMatchesRegularExpression(
            '/(cerrar sesi[oó]n|salir|logout)/i',
            $body,
            'home.php must provide a visible logout label.'
        );
    }

    /**
     * T2-REDIR-05: Circular Redirection Loop Prevention.
     * Verifies unauthenticated access to home.php redirects to login without entering an infinite redirect chain.
     */
    public function testT2Redir05_CircularRedirectionLoopPrevention(): void
    {
        $homeRes = $this->get('home.php');
        $homeRes->assertStatus(302);
        $location = $homeRes->response()->getHeaderLine('Location');
        $this->assertMatchesRegularExpression('/login/i', $location);

        $loginRes = $this->get('login');
        $loginRes->assertStatus(200);
    }

    /**
     * T2-REDIR-06: Missing Session Metadata Fallback Gracefulness.
     * Verifies that missing non-critical session display fields (like pais_nombre) do not trigger fatal errors.
     */
    public function testT2Redir06_MissingSessionMetadataFallbackGracefulness(): void
    {
        $result = $this->withSession([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ADM-01',
            'usuario'         => 'admin_sv',
            'nombre'          => 'Carlos Admin',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
            'pais_nombre'     => null,
        ])->get('home.php');

        $result->assertStatus(200);
        $result->assertSee('Carlos Admin');
    }

    /**
     * T2-UI-01: Complete 5-Color Corporate Palette Assertion.
     * Verifies that ALL five corporate colors are simultaneously present in login and home views.
     */
    public function testT2Ui01_Complete5ColorCorporatePaletteAssertion(): void
    {
        $loginRes = $this->get('login');
        $loginRes->assertStatus(200);
        $this->assertCorporatePalettePresent($loginRes->getBody());

        $homeRes = $this->withSession([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ADM-01',
            'usuario'         => 'admin_sv',
            'nombre'          => 'Carlos Admin',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ])->get('home.php');
        $homeRes->assertStatus(200);
        $this->assertCorporatePalettePresent($homeRes->getBody());
    }

    /**
     * T2-UI-02: Author Attribution Semantic Visibility.
     * Verifies that author attribution markup is semantically visible and not hidden with CSS.
     */
    public function testT2Ui02_AuthorAttributionSemanticVisibility(): void
    {
        $loginRes = $this->get('login');
        $loginRes->assertStatus(200);
        $body = $loginRes->getBody();

        $this->assertDoesNotMatchRegularExpression(
            '/style\s*=\s*"[^"]*(display\s*:\s*none|visibility\s*:\s*hidden)[^"]*"(?:[^>]*)(?:diseñad|desarrollad|equipo de desarrollo)/i',
            $body,
            'Author attribution must not be hidden via inline CSS display:none or visibility:hidden.'
        );
        $this->assertMatchesRegularExpression(
            '/(footer|<div[^>]*(attribution|autor|creditos|designer)[^>]*>|diseñad|desarrollad)/i',
            $body,
            'Author attribution container must be semantically present.'
        );
    }

    /**
     * T2-UI-03: Static Manual Attribution Verification.
     * Verifies that author attribution is statically embedded in the raw HTTP response body.
     */
    public function testT2Ui03_StaticManualAttributionVerification(): void
    {
        $loginRes = $this->get('login');
        $loginRes->assertStatus(200);
        $rawHtml = $loginRes->getBody();

        $this->assertMatchesRegularExpression(
            '/(diseñad[oa]|desarrollad[oa]|diseñadores|equipo de desarrollo|autores)/i',
            $rawHtml,
            'Author attribution must be present directly in raw server HTML response body.'
        );
    }

    /**
     * T2-UI-04: Flash Error Message Visual Styling Compliance.
     * Verifies that flash error messages rendered on login view use corporate red / brick colors.
     */
    public function testT2Ui04_FlashErrorMessageVisualStylingCompliance(): void
    {
        $postRes = $this->post('login', [
            'usuario'    => 'admin_sv',
            'contrasena' => 'WrongPass!',
        ]);
        $postRes->assertStatus(302);

        $loginRes = $this->get('login');
        $loginRes->assertStatus(200);
        $body = $loginRes->getBody();

        $this->assertMatchesRegularExpression(
            '/(#BF0426|#A61717)/i',
            $body,
            'Flash error message alert container must incorporate corporate colors #BF0426 or #A61717.'
        );
    }

    /**
     * T2-UI-05: Viewport Meta and Mobile Responsiveness Markup.
     * Verifies that login and home views declare responsive viewport metadata.
     */
    public function testT2Ui05_ViewportMetaAndMobileResponsivenessMarkup(): void
    {
        $loginRes = $this->get('login');
        $loginRes->assertStatus(200);
        $this->assertMatchesRegularExpression(
            '/<meta[^>]+name=["\']viewport["\'][^>]+content=["\'][^"\']*width=device-width/i',
            $loginRes->getBody(),
            'Login view must include responsive viewport meta tag.'
        );

        $homeRes = $this->withSession([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-ADM-01',
            'usuario'         => 'admin_sv',
            'nombre'          => 'Carlos Admin',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ])->get('home.php');
        $homeRes->assertStatus(200);
        $this->assertMatchesRegularExpression(
            '/<meta[^>]+name=["\']viewport["\'][^>]+content=["\'][^"\']*width=device-width/i',
            $homeRes->getBody(),
            'home.php view must include responsive viewport meta tag.'
        );
    }

    /**
     * T2-UI-06: Visual Color Contrast and Accessible Hierarchy.
     * Verifies that dark corporate backgrounds are paired with high-contrast text.
     */
    public function testT2Ui06_VisualColorContrastAndAccessibleHierarchy(): void
    {
        $loginRes = $this->get('login');
        $loginRes->assertStatus(200);
        $body = $loginRes->getBody();

        $this->assertMatchesRegularExpression(
            '/(color:\s*(#fff(fff)?|white)|background[^;]+(#023373|#8D253C|#BF0426|#A61717))/i',
            $body,
            'UI elements must maintain accessible visual contrast hierarchy with light text on dark backgrounds.'
        );
    }
}
