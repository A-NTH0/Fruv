<?php

namespace App\Models;

use CodeIgniter\Model;

class PedidoModel extends Model
{
    protected $table         = 'pedido';
    protected $primaryKey    = 'id_pedido';
    protected $allowedFields = ['fecha', 'id_cliente1', 'id_repartidor', 'modalidad_pago', 'deuda_restante', 'fecha_entrega', 'fecha_entrega_programada', 'horario_entrega', 'imagen_url', 'total'];
    protected $useTimestamps  = false;
    protected $useSoftDeletes = false;

    // ─────────────────────────────────────────────
    //  ESTADOS
    // ─────────────────────────────────────────────

    public function getEstadoActual($idPedido)
    {
        $db     = \Config\Database::connect();
        $result = $db->table('status')
            ->where('id_pedido2', $idPedido)
            ->orderBy('id_status', 'DESC')
            ->get()
            ->getRowArray();

        return $result ? $result['estado'] : 'pedido';
    }

    public function getHistorialEstados($idPedido)
    {
        $db = \Config\Database::connect();
        return $db->table('status')
            ->where('id_pedido2', $idPedido)
            ->orderBy('id_status', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function cambiarEstado($idPedido, $nuevoEstado, $repartidorId = null)
    {
        $db = \Config\Database::connect();
        $db->transStart();

        if ($repartidorId) {
            $this->update($idPedido, ['id_repartidor' => $repartidorId]);
        }

        if ($nuevoEstado == 'entregado') {
            $this->update($idPedido, ['fecha_entrega' => date('Y-m-d H:i:s')]);
        }

        // Si el pedido es de crédito y se acaba de entregar, pasarlo a cobro (crédito pendiente)
        $pedido = $this->find($idPedido);
        if ($pedido && $pedido['modalidad_pago'] == 'credito' && $nuevoEstado == 'entregado') {
            // Primero insertamos el estado 'entregado' y luego automáticamente 'credito'
            $db->table('status')->insert([
                'estado'     => 'entregado',
                'id_pedido2' => $idPedido,
                'fecha'      => date('Y-m-d H:i:s'),
            ]);
            $nuevoEstado = 'credito';
        }

        $db->table('status')->insert([
            'estado'     => $nuevoEstado,
            'id_pedido2' => $idPedido,
            'fecha'      => date('Y-m-d H:i:s'),
        ]);

        $db->transComplete();
        return $db->transStatus();
    }

    // ─────────────────────────────────────────────
    //  LISTADOS / PAGINACIÓN
    // ─────────────────────────────────────────────

    public function getPedidosPorEstado($page = 1, $perPage = 10)
    {
        $estados   = ['pedido', 'confirmado', 'en_transito', 'entregado', 'cancelado', 'credito', 'pagado'];
        $resultado = [];

        foreach ($estados as $estado) {
            $resultado[$estado] = $this->getPedidosByEstadoPaginados($estado, $page, $perPage);
        }

        return $resultado;
    }

    public function getTotalPorEstado()
    {
        $db      = \Config\Database::connect();
        $estados = ['pedido', 'confirmado', 'en_transito', 'entregado', 'cancelado', 'credito', 'pagado'];
        $totales = [];

        $subquery = "(SELECT id_pedido2, MAX(id_status) as max_id FROM status GROUP BY id_pedido2) as latest";

        foreach ($estados as $estado) {
            if ($estado == 'credito' || $estado == 'pagado') {
                $result = $db->table('status s1')
                    ->select('COUNT(DISTINCT s1.id_pedido2) as total')
                    ->join($subquery, 'latest.id_pedido2 = s1.id_pedido2 AND latest.max_id = s1.id_status', 'inner')
                    ->where('s1.estado', $estado)
                    ->get()
                    ->getRowArray();
                $totales[$estado] = $result['total'] ?? 0;
            } else {
                $result = $db->table('status s1')
                    ->select('COUNT(DISTINCT s1.id_pedido2) as total')
                    ->join($subquery, 'latest.id_pedido2 = s1.id_pedido2 AND latest.max_id = s1.id_status', 'inner')
                    ->where('s1.estado', $estado)
                    ->get()
                    ->getRowArray();

                $totales[$estado] = $result['total'] ?? 0;
            }
        }

        return $totales;
    }

    public function getPedidosByEstadoPaginados($estado, $page = 1, $perPage = 10)
    {
        $db     = \Config\Database::connect();
        $offset = ($page - 1) * $perPage;

        if ($estado == 'credito' || $estado == 'pagado') {
            // Crédito y Pagado se determinan por el último estado en la tabla status
            $subquery = "(SELECT id_pedido2, MAX(id_status) as max_id FROM status GROUP BY id_pedido2) as latest";

            $ids = $db->table('status s1')
                ->select('s1.id_pedido2')
                ->join($subquery, 'latest.id_pedido2 = s1.id_pedido2 AND latest.max_id = s1.id_status', 'inner')
                ->where('s1.estado', $estado)
                ->orderBy('s1.fecha', 'DESC')
                ->limit($perPage, $offset)
                ->get()
                ->getResultArray();

            $idsPedido = array_column($ids, 'id_pedido2');

            if (empty($idsPedido)) {
                return [];
            }

            $pedidos = $db->table('pedido p')
                ->select('p.*, c.nombre as cliente_nombre, c.ap_paterno as cliente_apellido, c.telefono as cliente_telefono, r.nombre as repartidor_nombre, r.ap_paterno as repartidor_apellido')
                ->join('cliente c', 'p.id_cliente1 = c.id_cliente')
                ->join('repartidor r', 'p.id_repartidor = r.id_repartidor', 'left')
                ->whereIn('p.id_pedido', $idsPedido)
                ->orderBy('p.fecha', 'DESC')
                ->get()
                ->getResultArray();

        } else {
            $subquery = "(SELECT id_pedido2, MAX(id_status) as max_id FROM status GROUP BY id_pedido2) as latest";

            $ids = $db->table('status s1')
                ->select('s1.id_pedido2')
                ->join($subquery, 'latest.id_pedido2 = s1.id_pedido2 AND latest.max_id = s1.id_status', 'inner')
                ->where('s1.estado', $estado)
                ->orderBy('s1.fecha', 'DESC')
                ->limit($perPage, $offset)
                ->get()
                ->getResultArray();

            $idsPedido = array_column($ids, 'id_pedido2');

            if (empty($idsPedido)) {
                return [];
            }

            $pedidos = $db->table('pedido p')
                ->select('p.*, c.nombre as cliente_nombre, c.ap_paterno as cliente_apellido, c.telefono as cliente_telefono, r.nombre as repartidor_nombre, r.ap_paterno as repartidor_apellido')
                ->join('cliente c', 'p.id_cliente1 = c.id_cliente')
                ->join('repartidor r', 'p.id_repartidor = r.id_repartidor', 'left')
                ->whereIn('p.id_pedido', $idsPedido)
                ->orderBy('p.fecha', 'DESC')
                ->get()
                ->getResultArray();
        }

        foreach ($pedidos as &$pedido) {
            $pedido['productos'] = $db->table('producto_pedido pp')
                ->select('pp.cantidad, pp.precio_venta, p.nombre as nombre_producto, pp.unidad_venta')
                ->join('producto p', 'pp.id_producto3 = p.id_producto')
                ->where('pp.id_pedido1', $pedido['id_pedido'])
                ->get()
                ->getResultArray();
        }

        return $pedidos;
    }

    public function getCountByEstado($estado)
    {
        $db = \Config\Database::connect();

        if ($estado == 'credito' || $estado == 'pagado') {
            $subquery = "(SELECT id_pedido2, MAX(id_status) as max_id FROM status GROUP BY id_pedido2) as latest";
            $result   = $db->table('status s1')
                ->select('COUNT(DISTINCT s1.id_pedido2) as total')
                ->join($subquery, 'latest.id_pedido2 = s1.id_pedido2 AND latest.max_id = s1.id_status', 'inner')
                ->where('s1.estado', $estado)
                ->get()
                ->getRowArray();
            return $result['total'] ?? 0;
        } else {
            $subquery = "(SELECT id_pedido2, MAX(id_status) as max_id FROM status GROUP BY id_pedido2) as latest";

            $result = $db->table('status s1')
                ->select('COUNT(DISTINCT s1.id_pedido2) as total')
                ->join($subquery, 'latest.id_pedido2 = s1.id_pedido2 AND latest.max_id = s1.id_status', 'inner')
                ->where('s1.estado', $estado)
                ->get()
                ->getRowArray();

            return $result['total'] ?? 0;
        }
    }

    // ─────────────────────────────────────────────
    //  BÚSQUEDA
    // ─────────────────────────────────────────────

    public function buscarPedidos($termino)
    {
        $db = \Config\Database::connect();

        $pedidos = $db->table('pedido p')
            ->select('p.*, c.nombre as cliente_nombre, c.ap_paterno as cliente_apellido, c.telefono as cliente_telefono, r.nombre as repartidor_nombre, r.ap_paterno as repartidor_apellido')
            ->join('cliente c', 'p.id_cliente1 = c.id_cliente')
            ->join('repartidor r', 'p.id_repartidor = r.id_repartidor', 'left')
            ->groupStart()
                ->like('CAST(p.id_pedido AS CHAR)', $termino)
                ->orLike('c.nombre', $termino)
                ->orLike('c.ap_paterno', $termino)
                ->orLike("CONCAT(c.nombre, ' ', c.ap_paterno)", $termino)
            ->groupEnd()
            ->orderBy('p.fecha', 'DESC')
            ->get()
            ->getResultArray();

        $idsPorProducto = $db->table('producto_pedido pp')
            ->select('DISTINCT pp.id_pedido1')
            ->join('producto p', 'pp.id_producto3 = p.id_producto')
            ->like('p.nombre', $termino)
            ->get()
            ->getResultArray();

        $idsProd = array_column($idsPorProducto, 'id_pedido1');

        if (!empty($idsProd)) {
            $idsYaEncontrados = array_column($pedidos, 'id_pedido');
            $idsFaltantes     = array_diff($idsProd, $idsYaEncontrados);

            if (!empty($idsFaltantes)) {
                $extra = $db->table('pedido p')
                    ->select('p.*, c.nombre as cliente_nombre, c.ap_paterno as cliente_apellido, c.telefono as cliente_telefono, r.nombre as repartidor_nombre, r.ap_paterno as repartidor_apellido')
                    ->join('cliente c', 'p.id_cliente1 = c.id_cliente')
                    ->join('repartidor r', 'p.id_repartidor = r.id_repartidor', 'left')
                    ->whereIn('p.id_pedido', $idsFaltantes)
                    ->get()
                    ->getResultArray();

                $pedidos = array_merge($pedidos, $extra);
            }
        }

        foreach ($pedidos as &$pedido) {
            $pedido['productos'] = $db->table('producto_pedido pp')
                ->select('pp.cantidad, pp.precio_venta, p.nombre as nombre_producto, pp.unidad_venta')
                ->join('producto p', 'pp.id_producto3 = p.id_producto')
                ->where('pp.id_pedido1', $pedido['id_pedido'])
                ->get()
                ->getResultArray();

            $pedido['estado_actual'] = $this->getEstadoActual($pedido['id_pedido']);
        }

        return $pedidos;
    }

    // ─────────────────────────────────────────────
    //  CREAR PEDIDO
    // ─────────────────────────────────────────────

    /**
     * Inserta el pedido, su estado inicial y sus productos en una transacción.
     * Devuelve ['success' => bool, 'id_pedido' => int].
     */
    public function crearPedidoCompleto(array $dataPedido, array $productos, string $estadoInicial): array
    {
        $db = \Config\Database::connect();
        $db->transStart();

        $db->table('pedido')->insert($dataPedido);
        $idPedido = $db->insertID();

        $db->table('status')->insert([
            'estado'     => $estadoInicial,
            'id_pedido2' => $idPedido,
            'fecha'      => date('Y-m-d H:i:s'),
        ]);

        foreach ($productos as $prod) {
            $subtotal = $prod['cantidad'] * $prod['precio'];
            $db->table('producto_pedido')->insert([
                'id_pedido1'   => $idPedido,
                'id_producto3' => $prod['id'],
                'cantidad'     => $prod['cantidad'],
                'precio_venta' => $prod['precio'],
                'unidad_venta' => $prod['unidad'] ?? 'kilos',
                'total'        => $subtotal,
            ]);
        }

        $db->transComplete();

        return [
            'success'   => $db->transStatus(),
            'id_pedido' => $idPedido,
        ];
    }

    // ─────────────────────────────────────────────
    //  ACTUALIZAR PEDIDO
    // ─────────────────────────────────────────────

    /**
     * Actualiza los datos del pedido y reemplaza su lista de productos.
     * Devuelve true/false.
     */
    public function actualizarPedidoCompleto(int $idPedido, array $updateData, array $productos): bool
    {
        $db = \Config\Database::connect();

        $db->table('pedido')->where('id_pedido', $idPedido)->update($updateData);
        $db->table('producto_pedido')->where('id_pedido1', $idPedido)->delete();

        foreach ($productos as $prod) {
            $productoExiste = $db->table('producto')->where('id_producto', $prod['id'])->get()->getRowArray();
            if (!$productoExiste) continue;

            $subtotal = $prod['cantidad'] * $prod['precio'];
            $db->table('producto_pedido')->insert([
                'id_pedido1'   => $idPedido,
                'id_producto3' => $prod['id'],
                'cantidad'     => $prod['cantidad'],
                'precio_venta' => $prod['precio'],
                'unidad_venta' => $prod['unidad'] ?? 'kilos',
                'total'        => $subtotal,
            ]);
        }

        return true;
    }

    // ─────────────────────────────────────────────
    //  ELIMINAR PEDIDO
    // ─────────────────────────────────────────────

    /**
     * Elimina el pedido y todos sus registros relacionados.
     */
    public function eliminarPedidoCompleto(int $id): void
    {
        $db = \Config\Database::connect();
        $db->table('producto_pedido')->where('id_pedido1', $id)->delete();
        $db->table('status')->where('id_pedido2', $id)->delete();
        $db->table('abonos')->where('id_pedido', $id)->delete();
        $db->table('pedido')->where('id_pedido', $id)->delete();
    }

    // ─────────────────────────────────────────────
    //  DETALLE / EDICIÓN
    // ─────────────────────────────────────────────

    /**
     * Devuelve el pedido completo con cliente, repartidor, productos, historial
     * de estados y historial de abonos.
     */
    public function getDetallePedido(int $idPedido): ?array
    {
        $db = \Config\Database::connect();

        $pedido = $db->table('pedido p')
            ->select('p.*, c.nombre as cliente_nombre, c.ap_paterno as cliente_apellido, c.telefono as cliente_telefono, r.nombre as repartidor_nombre, r.ap_paterno as repartidor_apellido')
            ->join('cliente c', 'p.id_cliente1 = c.id_cliente')
            ->join('repartidor r', 'p.id_repartidor = r.id_repartidor', 'left')
            ->where('p.id_pedido', $idPedido)
            ->get()
            ->getRowArray();

        if (!$pedido) return null;

        $pedido['detalles'] = $db->table('producto_pedido pp')
            ->select('pp.cantidad, pp.precio_venta, p.nombre as nombre_producto, pp.unidad_venta, pp.total')
            ->join('producto p', 'pp.id_producto3 = p.id_producto')
            ->where('pp.id_pedido1', $idPedido)
            ->get()
            ->getResultArray();

        $pedido['historial_estados'] = $db->table('status')
            ->where('id_pedido2', $idPedido)
            ->orderBy('id_status', 'ASC')
            ->get()
            ->getResultArray();

        $pedido['historial_abonos'] = $db->table('abonos')
            ->where('id_pedido', $idPedido)
            ->orderBy('fecha_registro', 'DESC')
            ->get()
            ->getResultArray();

        return $pedido;
    }

    /**
     * Devuelve el pedido con sus productos y los catálogos necesarios para el
     * formulario de edición.
     */
    public function getPedidoParaEditar(int $idPedido): ?array
    {
        $db     = \Config\Database::connect();
        $pedido = $db->table('pedido')->where('id_pedido', $idPedido)->get()->getRowArray();

        if (!$pedido) return null;

        $cliente = $db->table('cliente')->where('id_cliente', $pedido['id_cliente1'])->get()->getRowArray();
        $pedido['id_cliente']       = $cliente['id_cliente']  ?? null;
        $pedido['cliente_nombre']   = $cliente['nombre']       ?? '';
        $pedido['cliente_apellido'] = $cliente['ap_paterno']   ?? '';

        $pedido['productos_pedido'] = $db->table('producto_pedido pp')
            ->select('pp.id_producto3 as id_producto, pp.cantidad, pp.precio_venta as precio, pp.unidad_venta, p.nombre as nombre_producto')
            ->join('producto p', 'pp.id_producto3 = p.id_producto')
            ->where('pp.id_pedido1', $idPedido)
            ->get()
            ->getResultArray();

        $pedido['todos_productos'] = $db->table('producto')->select('id_producto, nombre, precio, unidad_venta')->get()->getResultArray();
        $pedido['clientes']        = $db->table('cliente')->select('id_cliente, nombre, ap_paterno')->get()->getResultArray();
        $pedido['repartidores']    = $db->table('repartidor')->select('id_repartidor, nombre, ap_paterno')->get()->getResultArray();

        return $pedido;
    }

    // ─────────────────────────────────────────────
    //  CATÁLOGOS PARA EL INDEX
    // ─────────────────────────────────────────────

    /**
     * Devuelve los catálogos (clientes, productos, repartidores) que se necesitan
     * para cargar la pantalla principal de pedidos.
     */
    public function getCatalogosIndex(): array
    {
        $db = \Config\Database::connect();
        return [
            'clientes'     => $db->table('cliente')->get()->getResultArray(),
            'productos'    => $db->table('producto')->select('id_producto, nombre, precio, unidad_venta')->get()->getResultArray(),
            'repartidores' => $db->table('repartidor')->get()->getResultArray(),
        ];
    }

    // ─────────────────────────────────────────────
    //  ABONOS
    // ─────────────────────────────────────────────

    public function registrarAbono($idPedido, $monto, $fechaAbono)
    {
        $db = \Config\Database::connect();
        $db->transStart();

        $db->table('abonos')->insert([
            'id_pedido'      => $idPedido,
            'monto'          => $monto,
            'fecha_abono'    => $fechaAbono,
            'fecha_registro' => date('Y-m-d H:i:s'),
        ]);

        $pedido     = $this->find($idPedido);
        $nuevaDeuda = $pedido['deuda_restante'] - $monto;
        $this->update($idPedido, ['deuda_restante' => max(0, $nuevaDeuda)]);

        if ($nuevaDeuda <= 0) {
            $this->cambiarEstado($idPedido, 'pagado');
        }

        $db->transComplete();
        return $db->transStatus();
    }

    public function getHistorialAbonos($idPedido)
    {
        $db = \Config\Database::connect();
        return $db->table('abonos')
            ->where('id_pedido', $idPedido)
            ->orderBy('fecha_registro', 'DESC')
            ->get()
            ->getResultArray();
    }

    // ─────────────────────────────────────────────
    //  STATS / DASHBOARD
    // ─────────────────────────────────────────────

    public function getStatsCards()
    {
        $db            = \Config\Database::connect();
        $estadosNormal = ['pedido', 'confirmado', 'en_transito', 'entregado', 'cancelado'];
        $stats         = [];

        $subquery = "(SELECT id_pedido2, MAX(id_status) as max_id FROM status GROUP BY id_pedido2) as latest";

        foreach ($estadosNormal as $estado) {
            $result = $db->table('status s1')
                ->select('COUNT(DISTINCT s1.id_pedido2) as total')
                ->join($subquery, 'latest.id_pedido2 = s1.id_pedido2 AND latest.max_id = s1.id_status', 'inner')
                ->where('s1.estado', $estado)
                ->get()
                ->getRowArray();

            $stats[$estado] = $result['total'] ?? 0;
        }

        foreach (['credito', 'pagado'] as $estado) {
            $result = $db->table('status s1')
                ->select('COUNT(DISTINCT s1.id_pedido2) as total')
                ->join($subquery, 'latest.id_pedido2 = s1.id_pedido2 AND latest.max_id = s1.id_status', 'inner')
                ->where('s1.estado', $estado)
                ->get()
                ->getRowArray();
            $stats[$estado] = $result['total'] ?? 0;
        }

        $stats['nuevo']      = $stats['pedido']      ?? 0;
        $stats['confirmado'] = $stats['confirmado']  ?? 0;
        $stats['transito']   = $stats['en_transito'] ?? 0;
        $stats['completado'] = $stats['entregado']   ?? 0;
        $stats['cancelado']  = $stats['cancelado']   ?? 0;

        return $stats;
    }
}