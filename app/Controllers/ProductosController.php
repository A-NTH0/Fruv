<?php
namespace App\Controllers;
use App\Models\ProductoModel;

class ProductosController extends BaseController
{
    public function index()
    {
        $model        = new ProductoModel();
        $busqueda     = trim($this->request->getVar('buscar') ?? '');
        $estadoFiltro = trim($this->request->getVar('estado') ?? '');

        $productos = $model->getProductos($busqueda, $estadoFiltro)->paginate(9, 'group1');

        $data = [
            'title'         => 'Catálogo de Productos',
            'productos'     => $productos,
            'pager'         => $model->pager,
            'busqueda'      => $busqueda,
            'estadoFiltro'  => $estadoFiltro,
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

        $precio = (float) ($this->request->getPost('precio') ?: 0);

        $model = new ProductoModel();
        $model->guardarProducto([
            'nombre'       => $this->request->getPost('nombre'),
            'descripcion'  => $this->request->getPost('descripcion'),
            'categoria'    => $this->request->getPost('categoria'),
            'unidad_venta' => $this->request->getPost('unidad_venta'),
            'precio'       => $precio,
            'total'        => $precio,
            'estado'       => $this->request->getPost('estado') ?: 'activo',
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

        $precio = (float) ($this->request->getPost('precio') ?: 0);

        $model->actualizarProducto($id, [
            'nombre'       => $this->request->getPost('nombre'),
            'descripcion'  => $this->request->getPost('descripcion'),
            'categoria'    => $this->request->getPost('categoria'),
            'unidad_venta' => $this->request->getPost('unidad_venta'),
            'precio'       => $precio,
            'total'        => $precio,
            'estado'       => $this->request->getPost('estado') ?: 'activo',
            'imagen'       => $nombreImagen,
        ]);

        return redirect()->to(base_url('productos'));
    }

    public function cambiarEstado($id = null)
    {
        if (!$id) {
            return $this->response->setJSON(['ok' => false, 'mensaje' => 'ID no válido']);
        }

        $model    = new ProductoModel();
        $producto = $model->obtenerProductoPorId((int) $id);

        if (!$producto) {
            return $this->response->setJSON(['ok' => false, 'mensaje' => 'Producto no encontrado']);
        }

        $estadoActual = strtolower($producto['estado'] ?? 'activo');
        $nuevoEstado  = ($estadoActual === 'activo') ? 'inactivo' : 'activo';

        $model->cambiarEstado((int) $id, $nuevoEstado);

        return $this->response->setJSON([
            'ok'          => true,
            'nuevoEstado' => $nuevoEstado,
            'mensaje'     => 'El producto ahora está ' . strtoupper($nuevoEstado),
        ]);
    }

    public function verificarEliminar($id = null)
    {
        if (!$id) {
            return $this->response->setJSON(['puede' => false, 'mensaje' => 'ID no válido']);
        }

        $model = new ProductoModel();

        if ($model->tieneExistencias((int) $id)) {
            return $this->response->setJSON([
                'puede'            => false,
                'tieneExistencias' => true,
                'mensaje'          => 'Este producto tiene existencias, entradas o pedidos asociados en la base de datos y no puede eliminarse físicamente. ¿Deseas marcarlo como INACTIVO?',
            ]);
        }

        return $this->response->setJSON(['puede' => true]);
    }

    public function eliminar($id)
    {
        $model = new ProductoModel();
        if (!$model->tieneExistencias((int) $id)) {
            $model->eliminarProducto((int) $id);
        }
        return redirect()->to(base_url('productos'));
    }
}