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

        if (session()->get('auth_role') === 'cliente') {
            $productos = $model->getProductos($busqueda, 'activo')->paginate(9, 'group1');

            return view('catalogo_cliente', [
                'productos' => $productos,
                'pager'     => $model->pager,
                'busqueda'  => $busqueda,
            ]);
        }

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
        $model  = new ProductoModel();
        $codigo = trim((string) $this->request->getPost('codigo'));
        $nombre = trim($this->request->getPost('nombre'));

        if (! $this->validate(['codigo' => 'required|max_length[50]'])) {
            return redirect()->back()->withInput()
                ->with('error', implode(' ', $this->validator->getErrors()))
                ->with('modalProducto', 'nuevo');
        }

        if ($model->existeCodigo($codigo)) {
            return redirect()->back()->withInput()
                ->with('error', 'Ya existe un producto con el código: ' . $codigo)
                ->with('modalProducto', 'nuevo');
        }

        if ($model->existeProducto($nombre)) {
            return redirect()->back()->withInput()
                ->with('error', 'Ya existe un producto con el nombre: ' . $nombre)
                ->with('modalProducto', 'nuevo');
        }

        $archivo      = $this->request->getFile('imagen_file');
        $nombreImagen = 'default.jpg';

        if ($archivo && $archivo->isValid() && !$archivo->hasMoved()) {
            $nombreImagen = $archivo->getRandomName();
            $archivo->move(FCPATH . 'uploads', $nombreImagen);
        }

        $precio = (float) ($this->request->getPost('precio') ?: 0);

        $model->guardarProducto([
            'codigo'       => $codigo,
            'nombre'       => $nombre,
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
        $id     = (int) $this->request->getPost('id_producto');
        $model  = new ProductoModel();
        $codigo = trim((string) $this->request->getPost('codigo'));
        $nombre = trim($this->request->getPost('nombre'));

        if ($id < 1 || ! $this->validate(['codigo' => 'required|max_length[50]'])) {
            $error = $id < 1
                ? 'El producto seleccionado no es válido.'
                : implode(' ', $this->validator->getErrors());

            return redirect()->back()->withInput()
                ->with('error', $error)
                ->with('modalProducto', 'editar');
        }

        if ($model->existeCodigo($codigo, $id)) {
            return redirect()->back()->withInput()
                ->with('error', 'Ya existe otro producto con el código: ' . $codigo)
                ->with('modalProducto', 'editar');
        }

        if ($model->existeProducto($nombre, $id)) {
            return redirect()->back()->withInput()
                ->with('error', 'Ya existe otro producto con el nombre: ' . $nombre)
                ->with('modalProducto', 'editar');
        }

        $producto     = $model->obtenerProductoPorId($id);
        if ($producto === null) {
            return redirect()->to(base_url('productos'))->with('error', 'El producto ya no existe.');
        }
        $nombreImagen = $producto['imagen'] ?? 'default.jpg';

        $archivo = $this->request->getFile('imagen_file');
        if ($archivo && $archivo->isValid() && !$archivo->hasMoved()) {
            
            // Limpieza: Borrar imagen vieja si no es la por defecto
            if ($nombreImagen !== 'default.jpg' && file_exists(FCPATH . 'uploads/' . $nombreImagen)) {
                unlink(FCPATH . 'uploads/' . $nombreImagen);
            }
            
            $nombreImagen = $archivo->getRandomName();
            // Ruta corregida para php spark serve
            $archivo->move(FCPATH . 'uploads', $nombreImagen);
        }

        $precio = (float) ($this->request->getPost('precio') ?: 0);

        $model->actualizarProducto($id, [
            'codigo'       => $codigo,
            'nombre'       => $nombre,
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
            $producto = $model->obtenerProductoPorId((int) $id);
            
            $model->eliminarProducto((int) $id);
            
            if ($producto && isset($producto['imagen']) && $producto['imagen'] !== 'default.jpg') {
                $rutaImagen = FCPATH . 'uploads/' . $producto['imagen'];
                if (file_exists($rutaImagen)) {
                    unlink($rutaImagen);
                }
            }
        }
        
        return redirect()->to(base_url('productos'));
    }
}