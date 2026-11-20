<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class UserAdminGuardFilter implements FilterInterface
{
    /**
     * @var list<string>
     */
    private const ALLOWED_ROLES = [
        'Administrador',
        'Supervisor',
        'Asesor',
    ];

    public function before(RequestInterface $request, $arguments = null)
    {
        $access  = service('agencyAccess');
        $session = session();

        if ($session->get('isLoggedIn') !== true) {
            return $access->unauthorizedResponse($request, 'Debe iniciar sesión para acceder al sistema');
        }

        $rawRol = $session->get('rol');
        if (! is_string($rawRol)) {
            return $access->forbiddenResponse($request, 'Acceso denegado: Rol no autorizado.');
        }

        if (trim($rawRol) === 'Supervisor') {
            return $access->forbiddenResponse(
                $request,
                'Acceso denegado: El rol Supervisor no tiene permisos para administrar usuarios, roles ni permisos.'
            );
        }

        if (! in_array($rawRol, self::ALLOWED_ROLES, true)) {
            return $access->forbiddenResponse($request, 'Acceso denegado: Rol no autorizado.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
