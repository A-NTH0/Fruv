<?php

namespace App\Controllers;

use App\Models\PedidoClienteModel;
use App\Models\PedidoModel;

/**
 * Acciones del administrador sobre los pedidos pendientes:
 * aceptar, dejar en espera y asignar repartidor.
 */
class PedidosFlujoController extends BaseController
{
    private function respuesta(array $res)
    {
        if ($res['success'] ?? false) {
            $res['stats'] = (new PedidoModel())->getStatsCards();
        }

        return $this->response->setJSON($res);
    }

    public function aceptar()
    {
        $id = (int) $this->request->getPost('id_pedido');
        if ($id <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'ID de pedido requerido']);
        }

        return $this->respuesta((new PedidoClienteModel())->aceptarPedido($id));
    }

    public function ponerPendiente()
    {
        $id    = (int) $this->request->getPost('id_pedido');
        $valor = (int) $this->request->getPost('en_espera') === 1;
        if ($id <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'ID de pedido requerido']);
        }

        return $this->respuesta((new PedidoClienteModel())->ponerEnEspera($id, $valor));
    }

    public function asignarRepartidor()
    {
        $id  = (int) $this->request->getPost('id_pedido');
        $rep = (int) $this->request->getPost('id_repartidor');
        if ($id <= 0 || $rep <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'Faltan parámetros']);
        }

        return $this->respuesta((new PedidoClienteModel())->asignarRepartidor($id, $rep));
    }
}