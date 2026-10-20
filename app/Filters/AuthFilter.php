<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    /** Primer segmento de las rutas exclusivas del cliente. */
    private const RUTAS_CLIENTE = ['carrito', 'mis-pedidos', 'perfil'];

    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        if (! $session->get('isLoggedIn')) {
            return redirect()->to(base_url('login'));
        }

        $segments     = service('uri')->getSegments();
        $primero      = $segments[0] ?? '';
        $esRutaCliente = in_array($primero, self::RUTAS_CLIENTE, true);

        // El administrador no usa las rutas del cliente.
        if ($session->get('auth_role') !== 'cliente') {
            return $esRutaCliente ? redirect()->to(base_url('dashboard')) : null;
        }

        $isCatalog = strtoupper($request->getMethod()) === 'GET'
            && $segments === ['productos'];
        $isLogout = $segments === ['logout'];

        if (! $isCatalog && ! $isLogout && ! $esRutaCliente) {
            return redirect()->to(base_url('productos'));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}