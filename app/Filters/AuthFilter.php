<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    /** Primer segmento de las rutas exclusivas del cliente. */
    private const RUTAS_CLIENTE = ['carrito', 'mis-pedidos', 'perfil'];
    private const RUTAS_ADMIN = ['vendedores'];

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
        if (! in_array($rol, ['admin', 'cliente', 'vendedor', 'repartidor'], true)) {
            $session->destroy();

            return redirect()->to(base_url('login'));
        }

        if ($rol === 'repartidor') {
            $repartidor = db_connect()->table('repartidor')
                ->where('id_repartidor', (int) $session->get('repartidor_id'))
                ->get()
                ->getRowArray();

            if ($repartidor === null || empty($repartidor['email_verified_at'])) {
                $session->destroy();

                return redirect()->to(base_url('login'));
            }

            $esLogout          = $segments === ['logout'];
            $esPortal          = strtoupper($request->getMethod()) === 'GET' && $segments === ['repartidores'];
            $esActualizaciones = strtoupper($request->getMethod()) === 'GET' && $segments === ['repartidores', 'actualizaciones'];
            $esCambiarEstado   = strtoupper($request->getMethod()) === 'POST' && $segments === ['repartidores', 'cambiar-estado'];
            $esTomarPedido     = strtoupper($request->getMethod()) === 'POST' && $segments === ['repartidores', 'tomar-pedido'];

            if (! $esLogout && ! $esPortal && ! $esActualizaciones && ! $esCambiarEstado && ! $esTomarPedido) {
                return redirect()->to(base_url('repartidores'));
            }

            return null;
        }

        if ($rol === 'vendedor') {
            $vendedor = db_connect()->table('vendedor')
                ->where('id', (int) $session->get('vendedor_id'))
                ->get()
                ->getRowArray();

            if ($vendedor === null || empty($vendedor['email_verified_at'])) {
                $session->destroy();

                return redirect()->to(base_url('login'));
            }

            $esLogout = $segments === ['logout'];
            $esCatalogo = strtoupper($request->getMethod()) === 'GET'
                && $segments === ['clientes'];
            $esHistorial = strtoupper($request->getMethod()) === 'GET'
                && count($segments) === 3
                && $segments[0] === 'clientes'
                && $segments[1] === 'historial'
                && ctype_digit((string) $segments[2]);
            $esRecarga = strtoupper($request->getMethod()) === 'POST'
                && $segments === ['clientes', 'recargar'];
            $esCatalogoPedido = strtoupper($request->getMethod()) === 'GET'
                && count($segments) === 3
                && $segments[0] === 'clientes'
                && $segments[1] === 'pedido'
                && ctype_digit((string) $segments[2]);
            $esNuevoPedido = strtoupper($request->getMethod()) === 'GET'
                && count($segments) === 4
                && $segments[0] === 'clientes'
                && $segments[1] === 'pedido'
                && $segments[2] === 'nuevo'
                && ctype_digit((string) $segments[3]);
            $esAccionPedido = strtoupper($request->getMethod()) === 'POST'
                && $segments === ['clientes', 'pedido', 'agregar'];
            $esActualizarPedido = strtoupper($request->getMethod()) === 'POST'
                && $segments === ['clientes', 'pedido', 'actualizar'];
            $esQuitarPedido = strtoupper($request->getMethod()) === 'POST'
                && $segments === ['clientes', 'pedido', 'quitar'];
            $esEnviarPedido = strtoupper($request->getMethod()) === 'POST'
                && $segments === ['clientes', 'pedido', 'enviar'];

            if (! $esLogout && ! $esCatalogo && ! $esHistorial && ! $esRecarga
                && ! $esCatalogoPedido && ! $esNuevoPedido && ! $esAccionPedido && ! $esActualizarPedido && ! $esQuitarPedido && ! $esEnviarPedido) {
                return redirect()->to(base_url('clientes'));
            }

            return null;
        }

        if ($rol !== 'admin' && in_array($primero, self::RUTAS_ADMIN, true)) {
            return redirect()->to(base_url('productos'));
        }

        if ($rol === 'admin') {
            $admin = db_connect()->table('admin')
                ->where('email', (string) $session->get('admin_email'))
                ->get()
                ->getRowArray();

            if ($admin === null || empty($admin['email_verified_at'])) {
                $session->destroy();

                return redirect()->to(base_url('login'));
            }
        }

        // El administrador no usa las rutas del cliente.
        if ($rol === 'admin') {
            return $esRutaCliente ? redirect()->to(base_url('dashboard')) : null;
        }

        // RNF-04: si el cliente fue dado de baja (o eliminado) con la sesión abierta, se cierra al instante.
        $cliente = db_connect()->table('cliente')
            ->select('baja, email_verified_at')
            ->where('id_cliente', (int) $session->get('cliente_id'))
            ->get()
            ->getRowArray();

        if ($cliente === null || (int) $cliente['baja'] !== 0 || empty($cliente['email_verified_at'])) {
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