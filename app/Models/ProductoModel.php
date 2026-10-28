<?php
namespace App\Models;

use App\Libraries\ProductoCodigo;
use CodeIgniter\Model;
use RuntimeException;

class ProductoModel extends Model
{
    protected $table         = 'producto';
    protected $primaryKey    = 'id_producto';
    protected $allowedFields = ['codigo', 'nombre', 'descripcion', 'imagen', 'categoria', 'unidad_venta', 'precio', 'total', 'estado'];
    protected $useTimestamps = false;

    public function getProductos(string $busqueda = '', string $estado = ''): static
    {
        $this->select('producto.*, COALESCE(existencias.exis_venta, 0) as stock_disponible, COALESCE(existencias.exis_total_general, 0) as stock_general')
             ->join('existencias', 'existencias.id_producto1 = producto.id_producto', 'left');

        if ($busqueda !== '') {
            $this->groupStart()
                 ->like('producto.nombre', $busqueda)
                 ->orLike('producto.codigo', $busqueda)
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

        $tieneEstadoProducto = $db->fieldExists('estado', 'producto');
        $totalProductos = $tieneEstadoProducto
            ? $this->where('estado', 'activo')->countAllResults()
            : $this->countAllResults();
        
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

    // NUEVO: Obtener alertas de stock (Productos activos con 10 o menos unidades)
    public function getAlertasStock($umbral = 10)
    {
        $db = \Config\Database::connect();
        $consulta = $db->table('producto p')
            ->select('p.nombre, COALESCE(e.exis_venta, 0) as stock')
            ->join('existencias e', 'e.id_producto1 = p.id_producto', 'left')
            ->groupStart()
                ->where('e.exis_venta <=', $umbral)
                ->orWhere('e.exis_venta IS NULL')
            ->groupEnd();

        if ($db->fieldExists('estado', 'producto')) {
            $consulta->where('p.estado', 'activo');
        }

        return $consulta->orderBy('stock', 'ASC')
            ->get()
            ->getResultArray();
    }

    // NUEVO: Verificar si ya existe un producto con el mismo nombre
    public function existeProducto(string $nombre, ?int $idExcluir = null): bool
    {
        $this->where('nombre', trim($nombre));
        if ($idExcluir !== null) {
            $this->where('id_producto !=', $idExcluir);
        }
        return $this->countAllResults() > 0;
    }

    public function existeCodigo(string $codigo, ?int $idExcluir = null): bool
    {
        $this->where('codigo', trim($codigo));
        if ($idExcluir !== null) {
            $this->where('id_producto !=', $idExcluir);
        }

        return $this->countAllResults() > 0;
    }

    public function generarCodigoSugerido(string $nombre): string
    {
        $db = \Config\Database::connect();
        $tabla = $db->prefixTable($this->table);
        $resultado = $db->query(
            'SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$tabla]
        )->getRowArray();

        if (! isset($resultado['AUTO_INCREMENT'])) {
            throw new RuntimeException('No se pudo determinar el próximo número de registro del producto.');
        }

        $codigos = $db->table($this->table)->select('codigo')->get()->getResultArray();
        $basesOcupadas = [];
        foreach ($codigos as $producto) {
            if (preg_match('/^([A-Z]+)-\d+$/', (string) $producto['codigo'], $coincidencias)) {
                $basesOcupadas[] = $coincidencias[1];
            }
        }

        return ProductoCodigo::generarCodigo($nombre, (int) $resultado['AUTO_INCREMENT'], $basesOcupadas);
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

    public function getProductosParaVenta()
    {
        $db = \Config\Database::connect();
        
        return $db->table('producto p')
            ->select('p.id_producto, p.nombre, p.precio, p.unidad_venta, COALESCE(e.exis_venta, 0) as stock_disponible')
            ->join('existencias e', 'e.id_producto1 = p.id_producto', 'left')
            ->groupStart()
                ->where('p.estado', 'activo')
                ->orWhere('e.exis_venta >', 0) 
            ->groupEnd()
            ->get()
            ->getResultArray();
    }

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
        $db->transBegin();

        try {
            $idNuevo        = $this->insert($data, true);

            if (! is_numeric($idNuevo) || (int) $idNuevo < 1) {
                throw new RuntimeException('No se pudo registrar el producto.');
            }

            $idNuevo = (int) $idNuevo;
            if ($db->tableExists('existencias') && ! $db->table('existencias')->insert([
                'exis_total_general' => 0,
                'exis_bloqueo'       => 0,
                'exis_venta'         => 0,
                'exis_total_dia'     => 0,
                'id_producto1'       => $idNuevo,
            ])) {
                throw new RuntimeException('No se pudieron inicializar las existencias del producto.');
            }

            if (! $db->transStatus()) {
                throw new RuntimeException('No se pudo completar el registro del producto.');
            }

            $db->transCommit();

            return $idNuevo;
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    public function actualizarProducto($id, $data)
    {
        return $this->update($id, $data);
    }

    public function cambiarEstado(int $id, string $nuevoEstado)
    {
        return $this->update($id, ['estado' => $nuevoEstado]);
    }

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
        $db->table('existencias')->where('id_producto1', (int) $id)->delete();
        return $this->delete($id);
    }

    // ─────────────────────────────────────────────
    //  RADIOGRAFÍA DE STOCK Y KARDEX
    // ─────────────────────────────────────────────
    public function getRadiografia(int $id_producto): array
    {
        $db   = \Config\Database::connect();
        $data = [];

        // 1. Stock físico, bloqueado y libre
        $data['stock'] = $db->table('existencias')
                            ->where('id_producto1', $id_producto)
                            ->get()
                            ->getRowArray();

        // 2. Total de ventas registradas en pedidos
        $data['ventas'] = $db->query(
            "SELECT COUNT(id_pedido1) as total_ventas, COALESCE(SUM(cantidad), 0) as unidades_vendidas 
             FROM producto_pedido 
             WHERE id_producto3 = ?",
            [$id_producto]
        )->getRowArray();

        // 3. Total de mermas acumuladas
        $data['mermas'] = $db->query(
            "SELECT COALESCE(SUM(merma.cantidad), 0) as total_mermas 
             FROM merma 
             JOIN entrada ON merma.id_entrada = entrada.id_entrada 
             WHERE entrada.id_producto2 = ?",
            [$id_producto]
        )->getRowArray();

        // 4. Historial Kardex (Entradas, Ventas y Mermas)
        $sqlKardex = "
            SELECT 
                fecha, 
                'Entrada 🟢' as movimiento, 
                COALESCE(conversion, cantidad * equivalente) as cant, 
                CONCAT('Compra Lote #', id_entrada, ' (', cantidad, ' ', unidad_compra, ')') as detalle 
            FROM entrada 
            WHERE id_producto2 = ?
            
            UNION ALL
            
            SELECT 
                pedido.fecha, 
                'Venta 🔵' as movimiento, 
                producto_pedido.cantidad as cant, 
                CONCAT('Pedido #', pedido.id_pedido) as detalle 
            FROM producto_pedido 
            JOIN pedido ON producto_pedido.id_pedido1 = pedido.id_pedido 
            WHERE producto_pedido.id_producto3 = ?
            
            UNION ALL
            
            SELECT 
                merma.fecha, 
                'Merma 🔴' as movimiento, 
                merma.cantidad as cant, 
                merma.notas as detalle 
            FROM merma 
            JOIN entrada ON merma.id_entrada = entrada.id_entrada 
            WHERE entrada.id_producto2 = ?
            
            ORDER BY fecha DESC 
            LIMIT 15
        ";

        $data['kardex'] = $db->query($sqlKardex, [$id_producto, $id_producto, $id_producto])->getResultArray();

        return $data;
    }
}