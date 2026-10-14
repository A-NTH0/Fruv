<?php
namespace App\Controllers;
use App\Models\ClienteModel;

class ClientesController extends BaseController
{
    public function index()
    {
        $model    = new ClienteModel();
        $busqueda = trim($this->request->getVar('buscar') ?? '');

        $clientes = $model->getClientesConDireccion($busqueda)->paginate(9, 'group1');

        $data = [
            'clientes' => $clientes,
            'pager'    => $model->pager,
            'busqueda' => $busqueda,
        ];

        return view('clientes', $data);
    }

    public function guardar()
    {
        $model = new ClienteModel();

        $idCliente = $model->insert([
            'nombre'     => $this->request->getPost('nombre'),
            'ap_paterno' => $this->request->getPost('ap_paterno'),
            'ap_materno' => $this->request->getPost('ap_materno'),
            'telefono'   => $this->request->getPost('telefono'),
        ]);

        if ($idCliente) {
       
            $model->guardarDireccion($idCliente, [
                'calle'     => $this->request->getPost('calle'),
                'numero'    => $this->request->getPost('numero'),
                'colonia'   => $this->request->getPost('colonia'),
                'municipio' => $this->request->getPost('municipio'),
                'estado'    => $this->request->getPost('estado'),
            ]);
        }

        return redirect()->to(base_url('clientes'));
    }

    public function actualizar()
    {
        $model = new ClienteModel();
        $id    = (int) $this->request->getPost('id_cliente');

        $model->update($id, [
            'nombre'     => $this->request->getPost('nombre'),
            'ap_paterno' => $this->request->getPost('ap_paterno'),
            'ap_materno' => $this->request->getPost('ap_materno'),
            'telefono'   => $this->request->getPost('telefono'),
        ]);

        $model->actualizarDireccion($id, [
            'calle'     => $this->request->getPost('calle'),
            'numero'    => $this->request->getPost('numero'),
            'colonia'   => $this->request->getPost('colonia'),
            'municipio' => $this->request->getPost('municipio'),
            'estado'    => $this->request->getPost('estado'),
        ]);

        return redirect()->to(base_url('clientes'));
    }

    public function verificarEliminar($id = null)
    {
        if (!$id) {
            return $this->response->setJSON(['puede' => false, 'mensaje' => 'ID no válido']);
        }

        $model = new ClienteModel();

        if ($model->tienePedidos((int) $id)) {
            return $this->response->setJSON([
                'puede'   => false,
                'mensaje' => 'Este cliente tiene pedidos asociados y no puede ser eliminado. Si deseas eliminarlo, primero cancela o elimina todos sus pedidos.',
            ]);
        }

        return $this->response->setJSON(['puede' => true]);
    }

    public function eliminar($id = null)
    {
        if ($id) {
            $model = new ClienteModel();
            $model->delete($id);
        }
        return redirect()->to(base_url('clientes'));
    }
}
