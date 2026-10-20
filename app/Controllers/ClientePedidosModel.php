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

    /** Endpoint de sondeo: devuelve el estado actual de los pedidos visibles. */
    public function estados()
    {
        $ids = array_values(array_filter(array_map('intval', explode(',', (string) $this->request->getGet('ids')))));
        $ids = array_slice($ids, 0, 50);

        return $this->response->setJSON([
            'success'  => true,
            'estados'  => (new PedidoClienteModel())->getEstadosPorIds((int) session()->get('cliente_id'), $ids),
        ]);
    }

    public function perfil()
    {
        $id = (int) session()->get('cliente_id');
        $db = \Config\Database::connect();

        $cliente = (new ClienteModel())
            ->select('cliente.id_cliente, cliente.nombre, cliente.ap_paterno, cliente.ap_materno, cliente.telefono, cliente.email, '
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