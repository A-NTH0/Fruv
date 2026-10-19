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
            'productos'     => $productoModel->getProductosActivos(),
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
            $idProducto = (int) $this->request->getPost('id_producto2');
            (new ExistenciasModel())->actualizarDesdeEntrada($idProducto);

            return redirect()->to(base_url('inventario'))->with('mensaje', 'Guardado correctamente');
        }

        return redirect()->back()->with('error', 'Error al registrar la entrada');
    }

    public function registrarMerma()
    {
        $model       = new MermaModel();
        $idsEntrada  = (array) $this->request->getPost('id_entrada');
        $cantidades  = (array) $this->request->getPost('cantidad');
        $fecha       = $this->request->getPost('fecha') ?: date('Y-m-d');
        $notas       = trim($this->request->getPost('notas') ?? '');
        $registradas = 0;

        foreach ($idsEntrada as $idx => $idEnt) {
            $idEnt = (int) $idEnt;
            $cant  = (float) ($cantidades[$idx] ?? 0);

            if ($idEnt > 0 && $cant > 0) {
                $ok = $model->registrarMerma([
                    'id_entrada' => $idEnt,
                    'cantidad'   => $cant,
                    'fecha'      => $fecha,
                    'notas'      => $notas !== '' ? $notas : 'Baja por merma',
                ]);
                if ($ok) {
                    $registradas++;
                }
            }
        }

        if ($registradas > 0) {
            return redirect()->to(base_url('inventario'))->with('mensaje', 'Merma(s) registrada(s) con éxito');
        }

        return redirect()->back()->with('error', 'Error al registrar la merma');
    }

    public function detallesStock($id_producto)
    {
        try {
            $model = new ProductoModel();
            $data  = $model->getRadiografia((int) $id_producto);
            $data['success'] = true;

            return $this->response->setJSON($data);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'success' => false,
                'error'   => 'Error al consultar radiografía: ' . $e->getMessage(),
            ]);
        }
    }
}