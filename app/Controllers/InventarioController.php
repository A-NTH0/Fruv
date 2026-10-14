<?php

namespace App\Controllers;

use App\Models\EntradaModel;
use App\Models\ExistenciasModel;
use App\Models\MermaModel;
use App\Models\ProductoModel;

class InventarioController extends BaseController
{
    public function index()
    {
        helper('url');

        $productoModel = new ProductoModel();
        $entradaModel  = new EntradaModel();

        $data = [
            'stats'         => $productoModel->getDashboardStats(),
            'entradas'      => $entradaModel->getEntradasConProductos(10),
            'todasEntradas' => $entradaModel->getAllEntradas(),
            'productos'     => $productoModel->getAllProductos(),
            'existencias'   => (new ExistenciasModel())->getAllExistencias(),
            'mermas'        => (new MermaModel())->getAllMermas(),
        ];

        return view('inventario', $data);
    }

    public function registrarEntrada()
    {
        $model    = new EntradaModel();
        $fechaCad = $this->request->getPost('fecha_caducidad');

        $data = [
            'id_producto2'    => $this->request->getPost('id_producto2'),
            'fecha'           => $this->request->getPost('fecha_entrada') ?: date('Y-m-d'),
            'fecha_caducidad' => !empty($fechaCad) ? $fechaCad : null,
            'cantidad'        => $this->request->getPost('cantidad'),
            'precio_compra'   => $this->request->getPost('precio_compra'),
            'unidad_compra'   => $this->request->getPost('unidad_compra'),
            'unidad_venta'    => $this->request->getPost('unidad_venta'),
            'equivalente'     => $this->request->getPost('equivalente'),
            'conversion'      => $this->request->getPost('conversion'),
        ];

        if ($model->insert($data)) {
            // Actualizar (o crear) el registro de existencias para este producto
            $idProducto = (int) $this->request->getPost('id_producto2');
            (new ExistenciasModel())->actualizarDesdeEntrada($idProducto);

            return redirect()->to(base_url('inventario'))->with('mensaje', 'Guardado correctamente');
        }

        return redirect()->back()->with('error', 'Error al registrar la entrada');
    }

    public function registrarMerma()
    {
        $model = new MermaModel();

        $data = [
            'id_entrada' => $this->request->getPost('id_entrada'),
            'cantidad'   => (float) $this->request->getPost('cantidad'),
            'fecha'      => $this->request->getPost('fecha') ?: date('Y-m-d'),
            'notas'      => $this->request->getPost('notas'),
        ];

        if ($model->registrarMerma($data)) {
            return redirect()->to(base_url('inventario'))->with('mensaje', 'Merma registrada con éxito');
        }

        return redirect()->back()->with('error', 'Error al registrar la merma');
    }
}
