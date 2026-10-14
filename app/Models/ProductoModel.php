<?php
namespace App\Models;
use CodeIgniter\Model;

class ProductoModel extends Model
{
    protected $table         = 'producto';
    protected $primaryKey    = 'id_producto';
    protected $allowedFields = ['nombre', 'descripcion', 'imagen', 'categoria', 'unidad_venta', 'precio', 'total'];
    protected $useTimestamps = false;

    public function getProductos(string $busqueda = ''): static
    {
        if ($busqueda !== '') {
            $this->groupStart()
                 ->like('nombre', $busqueda)
                 ->orLike('categoria', $busqueda)
                 ->orLike('descripcion', $busqueda)
                 ->groupEnd();
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
                             ->whereIn('estado', ['pedido', 'pedido_confirmado'])
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

    public function obtenerProductoPorId($id)
    {
        return $this->find($id);
    }

    public function guardarProducto($data)
    {
        return $this->insert($data);
    }

    public function actualizarProducto($id, $data)
    {
        return $this->update($id, $data);
    }

    public function tieneExistencias(int $idProducto): bool
    {
        $db = \Config\Database::connect();
        return $db->table('existencias')
                  ->where('id_producto1', $idProducto)
                  ->countAllResults() > 0;
    }

    public function eliminarProducto($id)
    {
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
