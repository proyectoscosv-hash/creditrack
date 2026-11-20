<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class SupervisorFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        return service('agencyAccess')->authorizeSupervisorRequest($request);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
