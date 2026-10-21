<?php

namespace App\Controllers;

use App\Models\PedidoModel;

class PedidosController extends BaseController
{
    public function index()
    {
        $pedidoModel = new PedidoModel();

        $page    = $this->request->getGet('page') ?? 1;
        $perPage = 10;

        $catalogos = $pedidoModel->getCatalogosIndex();

        $data = [
            'title'         => 'Seguimiento de Pedidos',
            'pagina_actual' => 'pedidos',
            'pedidos'       => $pedidoModel->getPedidosPorEstado($page, $perPage),
            'stats'         => $pedidoModel->getStatsCards(),
            'clientes'      => $catalogos['clientes'],
            'productos'     => $catalogos['productos'],
            'repartidores'  => $catalogos['repartidores'],
            'columnas'      => $this->getColumnasKanban(),
            'currentPage'   => $page,
            'perPage'       => $perPage,
            'totals'        => $pedidoModel->getTotalPorEstado(),
        ];

        return view('pedidos', $data);
    }

    private function getColumnasKanban(): array
    {
        return [
            'pedido'      => ['titulo' => '📥 Pedidos Nuevos',   'icon' => 'inbox',        'color' => 'rose'],
            'confirmado'  => ['titulo' => '✅ Confirmados',       'icon' => 'check-circle', 'color' => 'blue'],
            'en_transito' => ['titulo' => '🚚 En Tránsito',       'icon' => 'truck',        'color' => 'amber'],
            'entregado'   => ['titulo' => '🏆 Entregados',        'icon' => 'award',        'color' => 'emerald'],
            'cancelado'   => ['titulo' => '❌ Cancelados',         'icon' => 'x-circle',    'color' => 'slate'],
            'credito'     => ['titulo' => '💰 Pedidos a Crédito', 'icon' => 'credit-card',  'color' => 'purple'],
            'pagado'      => ['titulo' => '✅ Pedidos Pagados',   'icon' => 'check-circle', 'color' => 'green'],
        ];
    }

    public function getPedidosPaginados()
    {
        $estado  = $this->request->getGet('estado');
        $page    = (int)($this->request->getGet('page')     ?? 1);
        $perPage = (int)($this->request->getGet('per_page') ?? 10);

        if (!$estado) {
            return $this->response->setJSON(['success' => false, 'message' => 'Estado no especificado']);
        }

        $pedidoModel = new PedidoModel();
        $total       = $pedidoModel->getCountByEstado($estado);
        $totalPages  = max(1, ceil($total / $perPage));
        $pedidos     = $pedidoModel->getPedidosByEstadoPaginados($estado, $page, $perPage);
        $stats       = $pedidoModel->getStatsCards();

        return $this->response->setJSON([
            'success'     => true,
            'pedidos'     => $pedidos,
            'total'       => $total,
            'currentPage' => $page,
            'totalPages'  => $totalPages,
            'perPage'     => $perPage,
            'stats'       => $stats,
        ]);
    }

    public function buscar()
    {
        $termino = trim($this->request->getGet('q') ?? '');

        if (strlen($termino) < 1) {
            return $this->response->setJSON(['success' => false, 'message' => 'Término de búsqueda vacío']);
        }

        $pedidoModel = new PedidoModel();
        $pedidos     = $pedidoModel->buscarPedidos($termino);

        return $this->response->setJSON([
            'success' => true,
            'pedidos' => $pedidos,
            'total'   => count($pedidos),
        ]);
    }

    public function guardar()
    {
        $pedidoModel    = new PedidoModel();
        $modalidad      = $this->request->getPost('modalidad_pago') === 'credito' ? 'credito' : 'contado';
        $total          = floatval($this->request->getPost('total'));
        $productos      = json_decode($this->request->getPost('productos'), true) ?? [];


        if (!empty($productos) && $total == 0) {
            foreach ($productos as $prod) {
                $total += $prod['cantidad'] * $prod['precio'];
            }
        }
        $total = round($total, 2);

        // De contado: el total completo se toma como pagado. A crédito: pago inicial desde $0.
        if ($modalidad === 'credito') {
            $pagoInicial = floatval($this->request->getPost('pago_inicial'));
            if ($pagoInicial < 0) {
                return $this->response->setJSON(['success' => false, 'message' => 'El pago inicial no puede ser negativo.']);
            }
            $pagoInicial = round($pagoInicial, 2);
            if ($pagoInicial > $total) {
                return $this->response->setJSON(['success' => false, 'message' => 'El pago inicial no puede superar el total del pedido ($' . number_format($total, 2) . ').']);
            }
            $deuda = round($total - $pagoInicial, 2);
        } else {
            $pagoInicial = $total;
            $deuda       = 0;
        }

        $dataPedido = [
            'fecha'                    => $this->request->getPost('fecha_pedido') ?: date('Y-m-d'),
            'id_cliente1'              => $this->request->getPost('id_cliente'),
            'id_repartidor'            => $this->request->getPost('id_repartidor') ?: null,
            'modalidad_pago'           => $modalidad,
            'total'                    => $total,
            'deuda_restante'           => $deuda,
            'pago_inicial'             => $pagoInicial,
            'origen'                   => 'admin',
            'fecha_entrega'            => null,
            'fecha_entrega_programada' => $this->request->getPost('fecha_entrega') ?: null,
            'horario_entrega'          => $this->request->getPost('horario') ?: null,
            'imagen_url'               => null,
        ];

        $estadoInicial = 'pedido'; // Siempre inicia en pedidos nuevos, sin importar la modalidad


        $resultado = $pedidoModel->crearPedidoCompleto($dataPedido, $productos, $estadoInicial);

        if ($resultado['success']) {
            return $this->response->setJSON([
                'success'   => true,
                'message'   => '¡Pedido creado con éxito!',
                'id_pedido' => $resultado['id_pedido'],
            ]);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Error al guardar el pedido']);
    }

    public function cambiarEstado()
    {
        $idPedido     = $this->request->getPost('id_pedido');
        $nuevoEstado  = $this->request->getPost('nuevo_estado');
        $repartidorId = $this->request->getPost('id_repartidor');

        if (!$idPedido || !$nuevoEstado) {
            return $this->response->setJSON(['success' => false, 'message' => 'Faltan parámetros']);
        }

        $pedidoModel = new PedidoModel();

        // Solo se puede cancelar antes de "En tránsito" (Pendiente o Aceptado).
        if ($nuevoEstado === 'cancelado' && !$pedidoModel->puedeCancelar($idPedido)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Este pedido ya no se puede cancelar porque está en tránsito, fue entregado o ya estaba cancelado.',
            ]);
        }

        $resultado   = $pedidoModel->cambiarEstado($idPedido, $nuevoEstado, $repartidorId);

        if ($resultado) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Estado actualizado correctamente',
                'stats'   => $pedidoModel->getStatsCards(),
            ]);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Error al actualizar el estado']);
    }

    public function registrarAbono()
    {
        $idPedido   = $this->request->getPost('id_pedido');
        $monto      = floatval($this->request->getPost('monto'));
        $fechaAbono = $this->request->getPost('fecha_abono');

        if (!$idPedido) {
            return $this->response->setJSON(['success' => false, 'message' => 'ID de pedido requerido']);
        }
        if ($monto <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'El monto debe ser mayor a 0']);
        }
        if (!$fechaAbono) {
            return $this->response->setJSON(['success' => false, 'message' => 'La fecha del abono es requerida']);
        }

        $pedidoModel = new PedidoModel();
        $pedido      = $pedidoModel->find($idPedido);

        if (!$pedido) {
            return $this->response->setJSON(['success' => false, 'message' => 'Pedido no encontrado']);
        }
        if ($pedido['modalidad_pago'] !== 'credito') {
            return $this->response->setJSON(['success' => false, 'message' => 'Solo los pedidos a crédito aceptan abonos']);
        }
        if ($pedidoModel->getEstadoActual($idPedido) === 'cancelado') {
            return $this->response->setJSON(['success' => false, 'message' => 'El pedido está cancelado']);
        }
        if ($monto > $pedido['deuda_restante']) {
            return $this->response->setJSON(['success' => false, 'message' => 'El abono no puede superar la deuda restante ($' . number_format($pedido['deuda_restante'], 2) . ')']);
        }

        $resultado = $pedidoModel->registrarAbono($idPedido, $monto, $fechaAbono);

        if ($resultado) {
            $nuevaDeuda = max(0, $pedido['deuda_restante'] - $monto);
            return $this->response->setJSON([
                'success'    => true,
                'message'    => ($nuevaDeuda <= 0) ? '¡Pedido liquidado completamente!' : 'Abono registrado correctamente',
                'liquidado'  => ($nuevaDeuda <= 0),
                'nueva_deuda' => $nuevaDeuda,
                'stats'      => $pedidoModel->getStatsCards(),
            ]);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Error al registrar el abono']);
    }

    public function obtenerDetallePedido()
    {
        $idPedido = $this->request->getGet('id_pedido');

        if (!$idPedido) {
            return $this->response->setJSON(['success' => false, 'message' => 'ID de pedido no proporcionado']);
        }

        $pedidoModel = new PedidoModel();
        $detalle     = $pedidoModel->getDetallePedido((int) $idPedido);

        if (!$detalle) {
            return $this->response->setJSON(['success' => false, 'message' => 'Pedido no encontrado']);
        }

        return $this->response->setJSON([
            'success'           => true,
            'pedido'            => $detalle,
            'detalles'          => $detalle['detalles'],
            'historial_estados' => $detalle['historial_estados'],
            'historial_abonos'  => $detalle['historial_abonos'],
        ]);
    }

    public function obtenerPedidoParaEditar()
    {
        $idPedido = $this->request->getGet('id_pedido');

        if (!$idPedido) {
            return $this->response->setJSON(['success' => false, 'message' => 'ID requerido']);
        }

        $pedidoModel = new PedidoModel();
        $pedido      = $pedidoModel->getPedidoParaEditar((int) $idPedido);

        if (!$pedido) {
            return $this->response->setJSON(['success' => false, 'message' => 'Pedido no existe']);
        }

        return $this->response->setJSON([
            'success'         => true,
            'pedido'          => $pedido,
            'productos'       => $pedido['productos_pedido'],
            'todos_productos' => $pedido['todos_productos'],
            'clientes'        => $pedido['clientes'],
            'repartidores'    => $pedido['repartidores'],
        ]);
    }

    public function actualizar()
    {
        $idPedido = (int) $this->request->getPost('id_pedido');

        if (!$idPedido) {
            return $this->response->setJSON(['success' => false, 'message' => 'ID de pedido no proporcionado']);
        }

        $pedidoModel = new PedidoModel();

        if (!$pedidoModel->find($idPedido)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Pedido no encontrado']);
        }

        $modalidad  = $this->request->getPost('modalidad_pago') === 'credito' ? 'credito' : 'contado';
        $total      = round(floatval($this->request->getPost('total')), 2);
        $productos  = json_decode($this->request->getPost('productos'), true) ?? [];

        // El saldo se recalcula conservando el pago inicial y los abonos ya registrados.
        $anterior = $pedidoModel->find($idPedido);
        if ($modalidad === 'credito') {
            $pagoInicial = ($anterior['modalidad_pago'] === 'credito')
                ? min((float) $anterior['pago_inicial'], $total)
                : 0.0;
            $deuda = max(0, round($total - $pagoInicial - $pedidoModel->getAbonado($idPedido), 2));
        } else {
            $pagoInicial = $total;
            $deuda       = 0;
        }

        $updateData = [
            'id_cliente1'              => $this->request->getPost('id_cliente'),
            'id_repartidor'            => $this->request->getPost('id_repartidor') ?: null,
            'modalidad_pago'           => $modalidad,
            'fecha'                    => $this->request->getPost('fecha'),
            'fecha_entrega_programada' => $this->request->getPost('fecha_entrega_programada') ?: null,
            'horario_entrega'          => $this->request->getPost('horario_entrega') ?: null,
            'total'                    => $total,
            'deuda_restante'           => $deuda,
            'pago_inicial'             => $pagoInicial,
        ];


        $pedidoModel->actualizarPedidoCompleto($idPedido, $updateData, $productos);

        return $this->response->setJSON(['success' => true, 'message' => 'Pedido actualizado correctamente']);
    }

    public function eliminar($id = null)
    {
        if ($id) {
            $pedidoModel = new PedidoModel();
            $pedidoModel->eliminarPedidoCompleto((int) $id);
        }
        return redirect()->to(base_url('pedidos'));
    }

    public function getStatsActualizados()
    {
        $pedidoModel = new PedidoModel();
        return $this->response->setJSON([
            'success' => true,
            'stats'   => $pedidoModel->getStatsCards(),
        ]);
    }
}