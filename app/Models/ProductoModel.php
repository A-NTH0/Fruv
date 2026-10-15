<?php
namespace App\Models;
use CodeIgniter\Model;

class ProductoModel extends Model
{
    protected $table         = 'producto';
    protected $primaryKey    = 'id_producto';
    protected $allowedFields = ['nombre', 'descripcion', 'imagen', 'categoria', 'unidad_venta', 'precio', 'total', 'estado'];
    protected $useTimestamps = false;

    public function getProductos(string $busqueda = '', string $estado = ''): static
    {
        $this->select('producto.*, COALESCE(existencias.exis_venta, 0) as stock_disponible, COALESCE(existencias.exis_total_general, 0) as stock_general')
             ->join('existencias', 'existencias.id_producto1 = producto.id_producto', 'left');

        if ($busqueda !== '') {
            $this->groupStart()
                 ->like('producto.nombre', $busqueda)
                 ->orLike('producto.categoria', $busqueda)
                 ->orLike('producto.descripcion', $busqueda)
                 ->groupEnd();
        }

        if ($estado !== '') {
            $this->where('producto.estado', $estado);
        }

        return $this;
    }

    public function getDashboardStats()
    {
        $db = \Config\Database::connect();

        $totalProductos = $this->countAll();
        $totalClientes  = $db->tableExists('cliente') ? $db->table('cliente')->countAll() : 0;
        $totalPedidos   = $db->tableExists('pedido')  ? $db->table('pedido')->countAll()  : 0;

        $pendientes = 0;
        $transito   = 0;
        if ($db->tableExists('status')) {
            $pendientes = $db->table('status')
                             ->whereIn('estado', ['pedido', 'confirmado'])
                             ->countAllResults();

            $transito = $db->table('status')
                           ->where('estado', 'en_transito')
                           ->countAllResults();
        }

        $totalVentas = 0;
        if ($db->tableExists('producto_pedido')) {
            $query       = $db->table('producto_pedido')->selectSum('total')->get();
            $totalVentas = $query->getRow()->total ?? 0;
        }

        $repartidoresInfo = "0/0";
        if ($db->tableExists('repartidor')) {
            $totalR           = $db->table('repartidor')->countAll();
            $repartidoresInfo = $totalR;
        }

        return [
            'total_productos'      => $totalProductos,
            'total_clientes'       => $totalClientes,
            'pedidos_pendientes'   => $pendientes,
            'en_transito'          => $transito,
            'total_ventas'         => $totalVentas,
            'total_pedidos'        => $totalPedidos,
            'repartidores_activos' => $repartidoresInfo,
        ];
    }

    public function getUltimasEntradas($limite = 5)
    {
        $db = \Config\Database::connect();

        if ($db->tableExists('entrada')) {
            return $db->table('entrada e')
                      ->select('e.id_entrada, e.fecha, e.cantidad, e.precio_compra, p.nombre, p.categoria, p.unidad_venta')
                      ->join('producto p', 'e.id_producto2 = p.id_producto')
                      ->orderBy('e.fecha', 'DESC')
                      ->limit($limite)
                      ->get()
                      ->getResultArray();
        }

        return [];
    }

    // ─────────────────────────────────────────────
    //  HELPERS REUTILIZABLES
    // ─────────────────────────────────────────────

    public function getAllProductos()
    {
        return $this->findAll();
    }

    public function getProductosActivos()
    {
        return $this->where('estado', 'activo')->findAll();
    }

    public function obtenerProductoPorId($id)
    {
        return $this->find($id);
    }

    public function guardarProducto($data)
    {
        $db = \Config\Database::connect();
        $idNuevo = $this->insert($data, true);

        // Crear registro base en existencias para que el trigger 'actualizar_por_nueva_entrada' funcione
        if ($idNuevo && $db->tableExists('existencias')) {
            $db->table('existencias')->insert([
                'exis_total_general' => 0,
                'exis_bloqueo'       => 0,
                'exis_venta'         => 0,
                'exis_total_dia'     => 0,
                'id_producto1'       => $idNuevo,
            ]);
        }

        return $idNuevo;
    }

    public function actualizarProducto($id, $data)
    {
        return $this->update($id, $data);
    }

    public function cambiarEstado(int $id, string $nuevoEstado)
    {
        return $this->update($id, ['estado' => $nuevoEstado]);
    }

    /**
     * Verifica si el producto no se puede borrar físicamente por tener:
     * 1) Stock mayor a 0 en existencias
     * 2) Entradas registradas en 'entrada' (FK id_producto2)
     * 3) Pedidos registrados en 'producto_pedido' (FK id_producto3)
     */
    public function tieneExistencias(int $idProducto): bool
    {
        $db = \Config\Database::connect();

        $tieneStock = $db->table('existencias')
                         ->where('id_producto1', $idProducto)
                         ->groupStart()
                             ->where('exis_total_general >', 0)
                             ->orWhere('exis_venta >', 0)
                             ->orWhere('exis_bloqueo >', 0)
                         ->groupEnd()
                         ->countAllResults() > 0;

        $tieneEntradas = $db->table('entrada')
                            ->where('id_producto2', $idProducto)
                            ->countAllResults() > 0;

        $tienePedidos = $db->table('producto_pedido')
                           ->where('id_producto3', $idProducto)
                           ->countAllResults() > 0;

        return ($tieneStock || $tieneEntradas || $tienePedidos);
    }

    public function eliminarProducto($id)
    {
        $db = \Config\Database::connect();
        // Si solo tenía su fila en 0 en existencias (sin entradas ni pedidos), limpiamos esa fila primero por la FK
        $db->table('existencias')->where('id_producto1', (int) $id)->delete();
        return $this->delete($id);
    }

    public function obtenerPedidosConEstado()
    {
        $db = \Config\Database::connect();
        if ($db->tableExists('pedido')) {
            return $db->table('pedido')->get()->getResultArray();
        }
        return [];
    }

    public function obtenerProductosDePedido($id_pedido)
    {
        return [];
    }
}