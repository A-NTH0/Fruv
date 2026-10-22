<?php

namespace App\Controllers;

use App\Models\ClienteModel;
use App\Models\PedidoClienteModel;

class ClientePedidosController extends BaseController
{
    public function index()
    {
        $idCliente = (int) session()->get('cliente_id');
        $pagina    = max(1, (int) $this->request->getGet('page'));
        $datos     = (new PedidoClienteModel())->getPedidosCliente($idCliente, $pagina);

        return view('mis_pedidos', [
            'pedidos' => $datos['pedidos'],
            'total'   => $datos['total'],
            'paginas' => $datos['paginas'],
            'pagina'  => min($pagina, $datos['paginas']),
        ]);
    }

    /**
     * Endpoint de sondeo: devuelve el estado actual de los pedidos visibles.
     * RF-12: también devuelve el total y los productos vigentes, para que el cliente vea
     * sin recargar la página cuando la tienda ajusta los precios (p. ej. precio preferencial).
     */
    public function estados()
    {
        $idCliente = (int) session()->get('cliente_id');
        $ids       = array_values(array_filter(array_map('intval', explode(',', (string) $this->request->getGet('ids')))));
        $ids       = array_slice($ids, 0, 50);

        $estados = (new PedidoClienteModel())->getEstadosPorIds($idCliente, $ids);

        if ($estados !== []) {
            $db         = \Config\Database::connect();
            $idsValidos = array_map('intval', array_keys($estados));

            // Solo pedidos del propio cliente (RNF-05).
            $totales = $db->table('pedido')
                ->select('id_pedido, total')
                ->where('id_cliente1', $idCliente)
                ->whereIn('id_pedido', $idsValidos)
                ->get()->getResultArray();

            foreach ($totales as $t) {
                $estados[(int) $t['id_pedido']]['total']     = round((float) $t['total'], 2);
                $estados[(int) $t['id_pedido']]['productos'] = [];
            }

            $lineas = $db->table('producto_pedido pp')
                ->select('pp.id_pedido1, pp.cantidad, pp.unidad_venta, pp.total, pr.nombre AS nombre_producto')
                ->join('producto pr', 'pr.id_producto = pp.id_producto3')
                ->whereIn('pp.id_pedido1', $idsValidos)
                ->orderBy('pp.id_pedido1')
                ->get()->getResultArray();

            foreach ($lineas as $l) {
                $estados[(int) $l['id_pedido1']]['productos'][] = [
                    'nombre'   => $l['nombre_producto'],
                    'cantidad' => (float) $l['cantidad'],
                    'unidad'   => $l['unidad_venta'],
                    'total'    => round((float) $l['total'], 2),
                ];
            }
        }

        return $this->response->setJSON([
            'success' => true,
            'estados' => $estados,
        ]);
    }

    /** Cancela un pedido propio (solo Pendiente o Aceptado). */
    public function cancelar()
    {
        $id = (int) $this->request->getPost('id_pedido');
        if ($id <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'Pedido no válido.']);
        }

        $res = (new PedidoClienteModel())->cancelarPedidoCliente((int) session()->get('cliente_id'), $id);

        return $this->response->setJSON($res);
    }

    /** Registra un abono del cliente sobre un pedido propio a crédito. */
    public function abonar()
    {
        $id    = (int) $this->request->getPost('id_pedido');
        $texto = trim((string) $this->request->getPost('monto'));
        if ($id <= 0 || ! is_numeric($texto)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Ingresa un monto válido.']);
        }

        $res = (new PedidoClienteModel())->registrarAbonoCliente((int) session()->get('cliente_id'), $id, (float) $texto);

        return $this->response->setJSON($res);
    }

    public function perfil()
    {
        $id = (int) session()->get('cliente_id');
        $db = \Config\Database::connect();

        $cliente = (new ClienteModel())
            ->select('cliente.id_cliente, cliente.nombre, cliente.ap_paterno, cliente.ap_materno, cliente.telefono, cliente.email, cliente.preferencial_manual, '
                . ClienteModel::sqlCompras(), false)
            ->where('cliente.id_cliente', $id)
            ->first();

        if (! $cliente) {
            return redirect()->to(base_url('logout'));
        }

        return view('perfil', [
            'cliente'      => $cliente,
            'direccion'    => $db->table('direccion')->where('id_cliente', $id)->get()->getRowArray(),
            'totalPedidos' => $db->table('pedido')->where('id_cliente1', $id)->countAllResults(),
        ]);
    }
}