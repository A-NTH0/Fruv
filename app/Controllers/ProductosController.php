<?php
namespace App\Controllers;
use App\Models\ProductoModel;

class ProductosController extends BaseController
{
    public function index()
    {
        $model    = new ProductoModel();
        $busqueda = trim($this->request->getVar('buscar') ?? '');

        $productos = $model->getProductos($busqueda)->paginate(9, 'group1');

        $data = [
            'title'         => 'Catálogo de Productos',
            'productos'     => $productos,
            'pager'         => $model->pager,
            'busqueda'      => $busqueda,
            'pagina_actual' => 'productos',
            'isAjax'        => $this->request->isAJAX(),
        ];

        return view('productos', $data);
    }

    public function guardar()
    {
        $archivo      = $this->request->getFile('imagen_file');
        $nombreImagen = 'default.jpg';

        if ($archivo && $archivo->isValid() && !$archivo->hasMoved()) {
            $nombreImagen = $archivo->getRandomName();
            $archivo->move(ROOTPATH . 'public/uploads', $nombreImagen);
        }

        $model = new ProductoModel();
        $model->guardarProducto([
            'nombre'       => $this->request->getPost('nombre'),
            'descripcion'  => $this->request->getPost('descripcion'),
            'categoria'    => $this->request->getPost('categoria'),
            'unidad_venta' => $this->request->getPost('unidad_venta'),
            'imagen'       => $nombreImagen,
        ]);

        return redirect()->to(base_url('productos'));
    }

    public function actualizar()
    {
        $id    = (int) $this->request->getPost('id_producto');
        $model = new ProductoModel();

        $producto     = $model->obtenerProductoPorId($id);
        $nombreImagen = $producto['imagen'] ?? 'default.jpg';

        $archivo = $this->request->getFile('imagen_file');
        if ($archivo && $archivo->isValid() && !$archivo->hasMoved()) {
            $nombreImagen = $archivo->getRandomName();
            $archivo->move(ROOTPATH . 'public/uploads', $nombreImagen);
        }

        $model->actualizarProducto($id, [
            'nombre'       => $this->request->getPost('nombre'),
            'descripcion'  => $this->request->getPost('descripcion'),
            'categoria'    => $this->request->getPost('categoria'),
            'unidad_venta' => $this->request->getPost('unidad_venta'),
            'imagen'       => $nombreImagen,
        ]);

        return redirect()->to(base_url('productos'));
    }

    public function verificarEliminar($id = null)
    {
        if (!$id) {
            return $this->response->setJSON(['puede' => false, 'mensaje' => 'ID no válido']);
        }

        $model = new ProductoModel();

        if ($model->tieneExistencias((int) $id)) {
            return $this->response->setJSON([
                'puede'   => false,
                'mensaje' => 'Este producto tiene existencias registradas en inventario y no puede ser eliminado. Primero da de baja todas sus entradas.',
            ]);
        }

        return $this->response->setJSON(['puede' => true]);
    }

    public function eliminar($id)
    {
        $model = new ProductoModel();
        $model->eliminarProducto($id);
        return redirect()->to(base_url('productos'));
    }
}
