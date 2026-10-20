<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        if (! $session->get('isLoggedIn')) {
            return redirect()->to(base_url('login'));
        }

        if ($session->get('auth_role') !== 'cliente') {
            return null;
        }

        $segments  = service('uri')->getSegments();
        $isCatalog = strtoupper($request->getMethod()) === 'GET'
            && $segments === ['productos'];
        $isLogout = $segments === ['logout'];

        if (! $isCatalog && ! $isLogout) {
            return redirect()->to(base_url('productos'));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
