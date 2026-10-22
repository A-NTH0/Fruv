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

        $rol           = $session->get('auth_role');
        $segments      = service('uri')->getSegments();
        $primero       = $segments[0] ?? '';
        $esRutaCliente = in_array($primero, self::RUTAS_CLIENTE, true);

        // Falla cerrado: un rol desconocido nunca hereda permisos de administrador.
        // Cuando se agreguen los roles repartidor / vendedor, hay que declararlos aquí con sus rutas.
        if (! in_array($rol, ['admin', 'cliente'], true)) {
            $session->destroy();

            return redirect()->to(base_url('login'));
        }

        // El administrador no usa las rutas del cliente.
        if ($rol === 'admin') {
            return $esRutaCliente ? redirect()->to(base_url('dashboard')) : null;
        }

        // RNF-04: si el cliente fue dado de baja (o eliminado) con la sesión abierta, se cierra al instante.
        $vigente = db_connect()->table('cliente')
            ->where('id_cliente', (int) $session->get('cliente_id'))
            ->where('baja', 0)
            ->countAllResults() > 0;

        if (! $vigente) {
            $session->destroy();

            return redirect()->to(base_url('login'));
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