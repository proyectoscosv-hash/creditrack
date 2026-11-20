<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class UserAdminGuardController extends BaseController
{
    public function notFound(): ResponseInterface
    {
        if (service('agencyAccess')->isApiRequest($this->request)) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'status'  => 'error',
                    'error'   => 'not_found',
                    'message' => 'Recurso no encontrado.',
                ]);
        }

        return $this->response
            ->setStatusCode(404)
            ->setContentType('text/html', 'UTF-8')
            ->setBody(view('errors/html/error_404', ['message' => 'Página no encontrada.']));
    }
}
