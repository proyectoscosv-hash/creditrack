<?php

namespace Tests\Unit;

use App\Filters\AuthFilter;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FilterTestTrait;
use Config\Services;

/**
 * Unit Test Suite for AuthFilter and Route Access Protection.
 *
 * Verifies route filter bindings, unauthenticated interception,
 * authenticated passthrough, and role/country boundary scoping.
 *
 * @internal
 */
final class AuthFilterTest extends CIUnitTestCase
{
    use FilterTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Feature 11: Verify 'auth' filter is bound to protected routes.
     */
    public function testProtectedRoutesHaveAuthFilter(): void
    {
        $this->assertFilter('home.php', 'before', 'auth');
        $this->assertFilter('home', 'before', 'auth');
    }

    /**
     * Feature 11: Verify public routes do NOT have 'auth' filter.
     */
    public function testPublicRoutesDoNotHaveAuthFilter(): void
    {
        $this->assertNotFilter('login', 'before', 'auth');
        $this->assertNotFilter('/', 'before', 'auth');
        $this->assertNotFilter('logout', 'before', 'auth');
    }

    /**
     * Feature 11: Unauthenticated request to protected route is redirected to /login.
     */
    public function testFilterInterceptsUnauthenticatedRequest(): void
    {
        $filter  = new AuthFilter();
        $request = Services::incomingrequest(null, false);

        $_SESSION = [];

        $response = $filter->before($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(302, $response->getStatusCode());
    }

    /**
     * Feature 11: Authenticated request with isLoggedIn = true passes through (returns null).
     */
    public function testFilterPermitsAuthenticatedRequest(): void
    {
        $filter  = new AuthFilter();
        $request = Services::incomingrequest(null, false);

        $_SESSION = [
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-SV-001',
            'usuario'         => 'admin_sv',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ];

        $response = $filter->before($request);

        $this->assertNull($response, 'Filter must return null when request is authorized.');
    }

    /**
     * Feature 11: Incomplete session payload (missing rol or id_pais) is redirected to /login.
     */
    public function testFilterRejectsIncompleteSession(): void
    {
        $filter  = new AuthFilter();
        $request = Services::incomingrequest(null, false);

        $_SESSION = [
            'isLoggedIn' => true,
            'usuario'    => 'admin_sv',
            // Missing rol and id_pais
        ];

        $response = $filter->before($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(302, $response->getStatusCode());
    }

    /**
     * Feature 12: Unrecognized role is rejected with HTTP 403 Forbidden.
     */
    public function testFilterRejectsUnauthorizedRole(): void
    {
        $filter  = new AuthFilter();
        $request = Services::incomingrequest(null, false);

        $_SESSION = [
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-HCK-01',
            'usuario'         => 'intruder',
            'rol'             => 'IntruderRole',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ];

        $response = $filter->before($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(403, $response->getStatusCode());
    }

    /**
     * Feature 12: Role & Country Boundary Scoping Helper.
     */
    public function testCountryAccessBoundaryHelper(): void
    {
        $_SESSION = [
            'isLoggedIn'  => true,
            'id_pais'     => 1, // El Salvador
            'pais_nombre' => 'El Salvador',
            'rol'         => 'Asesor',
        ];

        // Accessing own country is allowed (numeric ID)
        $this->assertTrue(AuthFilter::checkCountryAccess(1));
        // Accessing own country is allowed (name string)
        $this->assertTrue(AuthFilter::checkCountryAccess('El Salvador'));

        // Accessing different country is strictly forbidden
        $this->assertFalse(AuthFilter::checkCountryAccess(2));
        $this->assertFalse(AuthFilter::checkCountryAccess(3));
        $this->assertFalse(AuthFilter::checkCountryAccess('Guatemala'));
    }
}
