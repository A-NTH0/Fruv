<?php
namespace App\Controllers;
use App\Models\RepartidorModel;

class RepartidoresController extends BaseController
{
    public function index()
    {
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

    public function guardar()
    {
        $model = new RepartidorModel();

        $model->insert([
            'nombre'     => $this->request->getPost('nombre'),
            'ap_paterno' => $this->request->getPost('ap_paterno'),
            'ap_materno' => $this->request->getPost('ap_materno'),
            'telefono'   => $this->request->getPost('telefono'),
            'direccion'  => $this->request->getPost('direccion'),
            'notas'      => $this->request->getPost('notas'),
        ]);

        return redirect()->to(base_url('repartidores'));
    }

    public function actualizar()
    {
        $model = new RepartidorModel();
        $id    = (int) $this->request->getPost('id_repartidor');

        $model->update($id, [
            'nombre'     => $this->request->getPost('nombre'),
            'ap_paterno' => $this->request->getPost('ap_paterno'),
            'ap_materno' => $this->request->getPost('ap_materno'),
            'telefono'   => $this->request->getPost('telefono'),
            'direccion'  => $this->request->getPost('direccion'),
            'notas'      => $this->request->getPost('notas'),
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
