<?php
namespace App\Controllers;

use App\Libraries\AccountCredentials;
use App\Models\RepartidorModel;

class RepartidoresController extends BaseController
{
    public function index()
    {
        if (session()->get('auth_role') === 'repartidor') {
            return $this->portalRepartidor();
        }

        $model    = new RepartidorModel();
        $busqueda = trim($this->request->getVar('buscar') ?? '');

        $repartidores = $model->getRepartidores($busqueda)->paginate(9, 'group1');

        $data = [
            'title'        => 'Catálogo de Repartidores',
            'repartidores' => $repartidores,
            'pager'        => $model->pager,
            'busqueda'     => $busqueda,
            'isAjax'       => $this->request->isAJAX(),
        ];

        return view('repartidores', $data);
    }

    /**
     * Vista operativa para el rol Repartidor (Funcionalidades 7 y 8).
     */
    private function portalRepartidor()
    {
        $idRepartidor = (int) session()->get('repartidor_id');
        $db           = db_connect();

        $repartidor = $db->table('repartidor')
            ->where('id_repartidor', $idRepartidor)
            ->get()
            ->getRowArray();

        $datos = $this->obtenerDatosEntregas($idRepartidor);

        return view('repartidor_entregas', [
            'repartidor'   => $repartidor,
            'asignados'    => $datos['asignados'],
            'disponibles'  => $datos['disponibles'],
            'entregados'   => $datos['entregados'],
            'stats'        => $datos['stats'],
            'firmaEstado'  => $datos['firma'],
            'vistaInicial' => $this->request->getGet('vista') ?? 'asignados',
        ]);
    }

    /**
     * Endpoint JSON ligero para avistamiento en tiempo real de nuevos pedidos (Funcionalidad 8 y RNF-08).
     */
    public function actualizaciones()
    {
        if (session()->get('auth_role') !== 'repartidor') {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false]);
        }

        $idRepartidor = (int) session()->get('repartidor_id');
        $datos        = $this->obtenerDatosEntregas($idRepartidor);

        return $this->response->setJSON([
            'ok'          => true,
            'stats'       => $datos['stats'],
            'asignados'   => $datos['asignados'],
            'disponibles' => $datos['disponibles'],
            'entregados'  => $datos['entregados'],
            'firma'       => $datos['firma'],
            'timestamp'   => date('H:i:s'),
        ]);
    }

    /**
     * Permite a un repartidor tomar un pedido confirmado que aún no tiene repartidor asignado.
     */
    public function tomarPedido()
    {
        if (session()->get('auth_role') !== 'repartidor') {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'mensaje' => 'No autorizado.']);
        }

        $idPedido     = (int) $this->request->getPost('id_pedido');
        $idRepartidor = (int) session()->get('repartidor_id');

        if ($idPedido <= 0 || $idRepartidor <= 0) {
            return $this->response->setJSON(['ok' => false, 'mensaje' => 'Pedido no válido.']);
        }

        $db     = db_connect();
        $pedido = $db->table('pedido')->where('id_pedido', $idPedido)->get()->getRowArray();

        if (! $pedido) {
            return $this->response->setJSON(['ok' => false, 'mensaje' => 'El pedido no existe.']);
        }

        if (! empty($pedido['id_repartidor']) && (int) $pedido['id_repartidor'] !== $idRepartidor) {
            return $this->response->setJSON(['ok' => false, 'mensaje' => 'Este pedido ya fue tomado por otro repartidor.']);
        }

        $estadoActual = $this->obtenerEstadoLogistico($idPedido);
        if (! in_array($estadoActual, ['confirmado', 'en_transito'], true)) {
            return $this->response->setJSON(['ok' => false, 'mensaje' => 'Solo se pueden tomar pedidos confirmados.']);
        }

        $db->table('pedido')
            ->where('id_pedido', $idPedido)
            ->update(['id_repartidor' => $idRepartidor]);

        return $this->response->setJSON([
            'ok'      => true,
            'mensaje' => 'Pedido #' . $idPedido . ' asignado a tu ruta exitosamente.',
        ]);
    }

    /**
     * Avanza el estado del pedido: confirmado -> en_transito -> entregado.
     */
    public function cambiarEstado()
    {
        if (session()->get('auth_role') !== 'repartidor') {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'mensaje' => 'No autorizado.']);
        }

        $idPedido     = (int) $this->request->getPost('id_pedido');
        $nuevoEstado  = trim((string) $this->request->getPost('nuevo_estado'));
        $idRepartidor = (int) session()->get('repartidor_id');

        if ($idPedido <= 0 || ! in_array($nuevoEstado, ['en_transito', 'entregado'], true)) {
            return $this->response->setJSON(['ok' => false, 'mensaje' => 'Parámetros no válidos.']);
        }

        $db     = db_connect();
        $pedido = $db->table('pedido')
            ->where('id_pedido', $idPedido)
            ->where('id_repartidor', $idRepartidor)
            ->get()
            ->getRowArray();

        if (! $pedido) {
            return $this->response->setJSON(['ok' => false, 'mensaje' => 'Este pedido no está asignado a tu cuenta.']);
        }

        $estadoActual = $this->obtenerEstadoLogistico($idPedido);

        if ($estadoActual === 'cancelado') {
            return $this->response->setJSON(['ok' => false, 'mensaje' => 'No se puede actualizar un pedido cancelado.']);
        }

        if ($nuevoEstado === 'en_transito' && $estadoActual !== 'confirmado') {
            return $this->response->setJSON(['ok' => false, 'mensaje' => 'El pedido debe estar Confirmado para iniciar ruta.']);
        }

        if ($nuevoEstado === 'entregado' && ! in_array($estadoActual, ['confirmado', 'en_transito'], true)) {
            return $this->response->setJSON(['ok' => false, 'mensaje' => 'El pedido ya fue marcado como entregado.']);
        }

        $db->transBegin();

        $db->table('status')->insert([
            'estado'     => $nuevoEstado,
            'fecha'      => date('Y-m-d H:i:s'),
            'id_pedido2' => $idPedido,
        ]);

        if ($nuevoEstado === 'entregado') {
            $db->table('pedido')
                ->where('id_pedido', $idPedido)
                ->update(['fecha_entrega' => date('Y-m-d H:i:s')]);

            // Si es venta a crédito y aún tiene deuda restante, registrar también estado 'credito'
            if (($pedido['modalidad_pago'] ?? '') === 'credito' && (float) ($pedido['deuda_restante'] ?? 0) > 0) {
                $db->table('status')->insert([
                    'estado'     => 'credito',
                    'fecha'      => date('Y-m-d H:i:s'),
                    'id_pedido2' => $idPedido,
                ]);
            }
        }

        if ($db->transStatus() === false) {
            $db->transRollback();

            return $this->response->setJSON(['ok' => false, 'mensaje' => 'No se pudo actualizar el estado del pedido.']);
        }

        $db->transCommit();

        $texto = $nuevoEstado === 'en_transito'
            ? 'Pedido #' . $idPedido . ' marcado En Camino.'
            : '¡Pedido #' . $idPedido . ' entregado con éxito!';

        return $this->response->setJSON([
            'ok'      => true,
            'mensaje' => $texto,
        ]);
    }

    private function obtenerEstadoLogistico(int $idPedido): string
    {
        $row = db_connect()->query(
            "SELECT estado FROM status 
             WHERE id_pedido2 = ? 
               AND estado IN ('pedido','confirmado','en_transito','entregado','cancelado')
             ORDER BY id_status DESC LIMIT 1",
            [$idPedido]
        )->getRowArray();

        return $row['estado'] ?? 'pedido';
    }

    private function obtenerDatosEntregas(int $idRepartidor): array
    {
        $db = db_connect();

        // Consulta optimizada de pedidos relevantes para el rol Repartidor
        $sql = "
            SELECT 
                p.id_pedido,
                p.fecha,
                COALESCE(p.total, 0) AS total,
                COALESCE(p.modalidad_pago, 'contado') AS modalidad_pago,
                COALESCE(p.deuda_restante, 0) AS deuda_restante,
                COALESCE(p.pago_inicial, 0) AS pago_inicial,
                p.fecha_entrega,
                p.fecha_entrega_programada,
                p.horario_entrega,
                p.id_repartidor,
                c.nombre AS cli_nombre,
                c.ap_paterno AS cli_paterno,
                c.ap_materno AS cli_materno,
                c.telefono AS cli_telefono,
                d.calle,
                d.numero,
                d.colonia,
                d.municipio,
                d.estado AS dir_estado,
                (
                    SELECT s.estado 
                    FROM status s 
                    WHERE s.id_pedido2 = p.id_pedido 
                      AND s.estado IN ('pedido','confirmado','en_transito','entregado','cancelado')
                    ORDER BY s.id_status DESC 
                    LIMIT 1
                ) AS estado_logistico
            FROM pedido p
            INNER JOIN cliente c ON c.id_cliente = p.id_cliente1
            LEFT JOIN direccion d ON d.id_cliente = c.id_cliente
            WHERE p.id_repartidor = ? 
               OR p.id_repartidor IS NULL
            ORDER BY p.id_pedido DESC
        ";

        $rows = $db->query($sql, [$idRepartidor])->getResultArray();

        // Evitar duplicados si un cliente tiene más de una fila en dirección
        $pedidosUnicos = [];
        foreach ($rows as $r) {
            $id = (int) $r['id_pedido'];
            if (! isset($pedidosUnicos[$id])) {
                $pedidosUnicos[$id] = $r;
            }
        }

        $ids = array_keys($pedidosUnicos);
        $productosPorPedido = [];

        if (! empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $prods = $db->query(
                "SELECT pp.id_pedido1, pp.cantidad, pp.precio_venta, pp.unidad_venta, pp.total, pr.nombre
                 FROM producto_pedido pp
                 INNER JOIN producto pr ON pr.id_producto = pp.id_producto3
                 WHERE pp.id_pedido1 IN ($placeholders)",
                $ids
            )->getResultArray();

            foreach ($prods as $pr) {
                $productosPorPedido[(int) $pr['id_pedido1']][] = [
                    'nombre'       => $pr['nombre'],
                    'cantidad'     => (float) $pr['cantidad'],
                    'unidad_venta' => $pr['unidad_venta'],
                    'precio_venta' => (float) $pr['precio_venta'],
                    'total'        => (float) $pr['total'],
                ];
            }
        }

        $asignados   = [];
        $disponibles = [];
        $entregados  = [];

        $porRecoger   = 0;
        $enCamino     = 0;
        $montoEnRuta  = 0.0;

        foreach ($pedidosUnicos as $id => $p) {
            $estado = $p['estado_logistico'] ?: 'pedido';
            $esMio  = (int) ($p['id_repartidor'] ?? 0) === $idRepartidor;

            $nombreCliente = trim(($p['cli_nombre'] ?? '') . ' ' . ($p['cli_paterno'] ?? '') . ' ' . ($p['cli_materno'] ?? ''));
            $calleLimpia   = (! empty($p['calle']) && strtolower($p['calle']) !== 'null') ? $p['calle'] : '';
            $coloniaLimpia = (! empty($p['colonia']) && strtolower($p['colonia']) !== 'null') ? $p['colonia'] : '';
            $munLimpio     = (! empty($p['municipio']) && strtolower($p['municipio']) !== 'null') ? $p['municipio'] : '';
            $estLimpio     = (! empty($p['dir_estado']) && ! in_array(strtolower($p['dir_estado']), ['null', 'nnn'], true)) ? $p['dir_estado'] : '';

            $partesDir = [];
            if ($calleLimpia !== '') {
                $partesDir[] = $calleLimpia . (! empty($p['numero']) ? ' #' . $p['numero'] : '');
            }
            if ($coloniaLimpia !== '') {
                $partesDir[] = 'Col. ' . $coloniaLimpia;
            }
            if ($munLimpio !== '') {
                $partesDir[] = $munLimpio;
            }
            if ($estLimpio !== '') {
                $partesDir[] = $estLimpio;
            }

            $direccionCompleta = ! empty($partesDir) ? implode(', ', $partesDir) : 'Dirección pendiente de confirmar en tienda';

            $total         = (float) $p['total'];
            $modalidad     = $p['modalidad_pago'] ?: 'contado';
            $deudaRestante = (float) $p['deuda_restante'];
            $pagoInicial   = (float) $p['pago_inicial'];
            $montoCobrar   = $modalidad === 'credito' ? $deudaRestante : $total;

            $item = [
                'id_pedido'                => $id,
                'fecha'                    => $p['fecha'],
                'estado'                   => $estado,
                'cliente_nombre'           => $nombreCliente ?: 'Cliente Fruver',
                'cliente_telefono'         => preg_replace('/[^0-9]/', '', (string) ($p['cli_telefono'] ?? '')),
                'direccion'                => $direccionCompleta,
                'tiene_mapa'               => ! empty($partesDir),
                'total'                    => $total,
                'modalidad_pago'           => $modalidad,
                'deuda_restante'           => $deudaRestante,
                'pago_inicial'             => $pagoInicial,
                'monto_cobrar'             => $montoCobrar,
                'fecha_entrega'            => ($p['fecha_entrega'] && $p['fecha_entrega'] !== '0000-00-00 00:00:00') ? $p['fecha_entrega'] : null,
                'fecha_entrega_programada' => ($p['fecha_entrega_programada'] && $p['fecha_entrega_programada'] !== '0000-00-00') ? $p['fecha_entrega_programada'] : null,
                'horario_entrega'          => ($p['horario_entrega'] && $p['horario_entrega'] !== '00:00:00') ? substr($p['horario_entrega'], 0, 5) : null,
                'productos'                => $productosPorPedido[$id] ?? [],
            ];

            if ($esMio && in_array($estado, ['confirmado', 'en_transito'], true)) {
                $asignados[] = $item;
                if ($estado === 'confirmado') {
                    $porRecoger++;
                } else {
                    $enCamino++;
                }
                $montoEnRuta += $montoCobrar;
            } elseif (! $esMio && empty($p['id_repartidor']) && in_array($estado, ['confirmado', 'pedido'], true)) {
                $disponibles[] = $item;
            } elseif ($esMio && $estado === 'entregado') {
                $entregados[] = $item;
            }
        }

        $stats = [
            'activos'      => count($asignados),
            'por_recoger'  => $porRecoger,
            'en_camino'    => $enCamino,
            'disponibles'  => count($disponibles),
            'entregados'   => count($entregados),
            'monto_ruta'   => round($montoEnRuta, 2),
        ];

        $firma = md5(json_encode([
            array_column($asignados, 'estado', 'id_pedido'),
            array_column($disponibles, 'estado', 'id_pedido'),
            count($entregados),
        ]));

        return [
            'asignados'   => $asignados,
            'disponibles' => $disponibles,
            'entregados'  => $entregados,
            'stats'       => $stats,
            'firma'       => $firma,
        ];
    }

    public function guardar()
    {
        $input = [
            'nombre'     => trim((string) $this->request->getPost('nombre')),
            'ap_paterno' => trim((string) $this->request->getPost('ap_paterno')),
            'ap_materno' => trim((string) $this->request->getPost('ap_materno')),
            'telefono'   => trim((string) $this->request->getPost('telefono')),
            'direccion'  => trim((string) $this->request->getPost('direccion')),
            'notas'      => trim((string) $this->request->getPost('notas')),
            'email'      => strtolower(trim((string) $this->request->getPost('email'))),
        ];
        $rules = [
            'nombre'     => 'required|max_length[100]',
            'ap_paterno' => 'required|max_length[100]',
            'ap_materno' => 'permit_empty|max_length[100]',
            'telefono'   => 'required|max_length[12]',
            'direccion'  => 'permit_empty|max_length[500]',
            'notas'      => 'permit_empty|max_length[500]',
            'email'      => 'required|valid_email|max_length[50]|is_unique[repartidor.email]',
        ];

        if (! $this->validate($rules, $input)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $emailConfigurationError = AccountCredentials::configurationError();
        if ($emailConfigurationError !== null) {
            return redirect()->back()->withInput()->with('error', $emailConfigurationError);
        }

        $password = AccountCredentials::generatePassword();
        $model = new RepartidorModel();
        $db    = db_connect();
        $db->transBegin();

        $repartidorId = $model->insert([
            ...$input,
            'hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        if ($repartidorId === false) {
            $db->transRollback();

            return redirect()->back()->withInput()->with('error', 'No se pudo guardar el repartidor.');
        }

        $nombre = trim($input['nombre'] . ' ' . $input['ap_paterno'] . ' ' . $input['ap_materno']);
        if (! $db->transStatus() || ! AccountCredentials::sendPassword($input['email'], $nombre, 'repartidor', $password)) {
            $db->transRollback();

            return redirect()->back()->withInput()->with('error', 'No se pudo enviar el correo de acceso. Verifica los datos del servidor SMTP y vuelve a intentarlo.');
        }

        $db->transCommit();
        if (! $db->transStatus()) {
            return redirect()->back()->with('error', 'No se pudo completar el registro del repartidor.');
        }

        return redirect()->to(base_url('repartidores'))->with('credential', [
            'nombre'    => $nombre,
            'email'     => $input['email'],
            'password'  => $password,
            'tipo'      => 'Repartidor',
        ]);
    }

    public function actualizar()
    {
        $model = new RepartidorModel();
        $id    = (int) $this->request->getPost('id_repartidor');
        $email = strtolower(trim((string) $this->request->getPost('email')));

        if (! $this->validate([
            'email' => "required|valid_email|max_length[50]|is_unique[repartidor.email,id_repartidor,{$id}]",
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $model->update($id, [
            'nombre'     => $this->request->getPost('nombre'),
            'ap_paterno' => $this->request->getPost('ap_paterno'),
            'ap_materno' => $this->request->getPost('ap_materno'),
            'telefono'   => $this->request->getPost('telefono'),
            'direccion'  => $this->request->getPost('direccion'),
            'notas'      => $this->request->getPost('notas'),
            'email'      => $email,
        ]);

        return redirect()->to(base_url('repartidores'));
    }

    public function verificarEliminar($id = null)
    {
        if (!$id) {
            return $this->response->setJSON(['puede' => false, 'mensaje' => 'ID no válido']);
        }

        $model = new RepartidorModel();

        if ($model->tienePedidos((int) $id)) {
            return $this->response->setJSON([
                'puede'   => false,
                'mensaje' => 'Este repartidor tiene pedidos asignados y no puede ser eliminado. Si deseas eliminarlo, primero reasigna o elimina todos sus pedidos.',
            ]);
        }

        return $this->response->setJSON(['puede' => true]);
    }

    public function eliminar($id = null)
    {
        if ($id) {
            $model = new RepartidorModel();
            $model->delete($id);
        }
        return redirect()->to(base_url('repartidores'));
    }
}