<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    /**
     * Recognized system roles
     */
    protected const ALLOWED_ROLES = [
        'Administrador',
        'Supervisor',
        'Asesor',
    ];

    /**
     * Inspect incoming request for authentication, session completeness,
     * role validation, and country boundary restrictions.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        // 1. Check active session
        if (! $session->get('isLoggedIn')) {
            return redirect()->to(site_url('login'))->with('error', 'Debe iniciar sesión para acceder al sistema');
        }

        // 2. Validate complete session payload
        $userRole    = $session->get('rol');
        $userCountry = $session->get('id_pais');

        if (empty($userRole) || empty($userCountry)) {
            return redirect()->to(site_url('login'))->with('error', 'Sesión incompleta o inválida');
        }

        // 3. Validate recognized system role
        if (! in_array($userRole, self::ALLOWED_ROLES, true)) {
            $response = service('response');
            return $response->setStatusCode(403)->setBody('Acceso denegado: Rol no autorizado.');
        }

        // 4. Validate country territorial access boundary
        $requestedCountry = null;
        if (method_exists($request, 'getGet')) {
            $requestedCountry = $request->getGet('pais') ?? $request->getGet('id_pais');
        }
        if ($requestedCountry === null && method_exists($request, 'getPost')) {
            $requestedCountry = $request->getPost('pais') ?? $request->getPost('id_pais');
        }

        if ($requestedCountry !== null && $requestedCountry !== '') {
            $userCountryId   = (int) $session->get('id_pais');
            $userCountryName = (string) $session->get('pais_nombre');

            $isPermitted = is_numeric($requestedCountry)
                ? ((int) $requestedCountry === $userCountryId)
                : (strcasecmp((string) $requestedCountry, $userCountryName) === 0);

            if (! $isPermitted) {
                $response = service('response');
                return $response->setStatusCode(403)->setBody('Acceso denegado: Restricción de jurisdicción territorial por país.');
            }
        }

        return null;
    }

    /**
     * Post-processing hook
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    /**
     * Helper to verify if the currently authenticated user has access to the given country.
     *
     * @param int|string $country Country ID (numeric) or Country Name (string)
     * @return bool True if permitted, false otherwise
     */
    public static function checkCountryAccess($country): bool
    {
        $session = session();
        if (! $session->get('isLoggedIn')) {
            return false;
        }

        $userCountryId   = (int) $session->get('id_pais');
        $userCountryName = (string) $session->get('pais_nombre');

        return is_numeric($country)
            ? ((int) $country === $userCountryId)
            : (strcasecmp((string) $country, $userCountryName) === 0);
    }
}
