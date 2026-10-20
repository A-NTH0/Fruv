<?php

namespace App\Models;

use Config\Database;

/**
 * Lógica del flujo carrito → pedido pendiente → aceptación del administrador,
 * y consultas del historial del cliente. Usa el Query Builder directamente
 * sobre las tablas existentes (no modifica PedidoModel).
 */
class PedidoClienteModel
{
    public const POR_PAGINA = 8;

    public const PASOS = [
        1 => 'Pendiente',
        2 => 'Aceptado',
        3 => 'Repartidor asignado',
        4 => 'En camino',
        5 => 'Entregado',
    ];

    private const SUBQUERY_ULTIMO = '(SELECT id_pedido2, MAX(id_status) as max_id FROM status GROUP BY id_pedido2) as latest';

    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    // ─────────────────────────────────────────────
    //  ESTADO VISIBLE PARA EL CLIENTE
    // ─────────────────────────────────────────────

    public static function describirEstado(?string $estado, $idRepartidor = null, $enEspera = 0): array
    {
        $estado = $estado ?: 'pedido';

        switch ($estado) {
            case 'pedido':
                if ((int) $enEspera === 1) {
                    return ['clave' => 'en_espera', 'texto' => 'En espera', 'color' => 'amber', 'paso' => 1,
                        'detalle' => 'Estamos verificando la disponibilidad de tus productos.'];
                }
                return ['clave' => 'pendiente', 'texto' => 'Pendiente', 'color' => 'rose', 'paso' => 1,
                    'detalle' => 'Recibimos tu pedido. Está por ser aceptado por la tienda.'];
            case 'confirmado':
                if (! empty($idRepartidor)) {
                    return ['clave' => 'repartidor_asignado', 'texto' => 'Repartidor asignado', 'color' => 'indigo', 'paso' => 3,
                        'detalle' => 'Tu pedido ya tiene repartidor y se está preparando.'];
                }
                return ['clave' => 'aceptado', 'texto' => 'Aceptado', 'color' => 'blue', 'paso' => 2,
                    'detalle' => 'La tienda aceptó tu pedido. Pronto se asignará un repartidor.'];
            case 'en_transito':
                return ['clave' => 'en_camino', 'texto' => 'En camino', 'color' => 'amber', 'paso' => 4,
                    'detalle' => 'Tu pedido va en camino.'];
            case 'entregado':
                return ['clave' => 'entregado', 'texto' => 'Entregado', 'color' => 'emerald', 'paso' => 5,
                    'detalle' => 'Tu pedido fue entregado. ¡Gracias por tu compra!'];
            case 'credito':
                return ['clave' => 'entregado', 'texto' => 'Entregado', 'color' => 'emerald', 'paso' => 5,
                    'detalle' => 'Tu pedido fue entregado. Tienes un saldo pendiente por pagar.'];
            case 'pagado':
                return ['clave' => 'pagado', 'texto' => 'Pagado', 'color' => 'green', 'paso' => 5,
                    'detalle' => 'Tu pedido fue entregado y pagado por completo.'];
            case 'cancelado':
                return ['clave' => 'cancelado', 'texto' => 'Cancelado', 'color' => 'slate', 'paso' => 0,
                    'detalle' => 'Este pedido fue cancelado.'];
        }

        return ['clave' => 'pendiente', 'texto' => 'Pendiente', 'color' => 'rose', 'paso' => 1, 'detalle' => ''];
    }

    // ─────────────────────────────────────────────
    //  CARRITO
    // ─────────────────────────────────────────────

    /**
     * @param array $carrito [id_producto => cantidad]
     */
    public function getItemsCarrito(array $carrito): array
    {
        $carrito = array_filter($carrito, static fn ($c) => (float) $c > 0);
        if ($carrito === []) {
            return [];
        }

        $filas = $this->db->table('producto p')
            ->select('p.id_producto, p.nombre, p.precio, p.unidad_venta, p.imagen, p.estado, COALESCE(e.exis_venta, 0) AS stock_disponible')
            ->join('existencias e', 'e.id_producto1 = p.id_producto', 'left')
            ->whereIn('p.id_producto', array_map('intval', array_keys($carrito)))
            ->orderBy('p.nombre', 'ASC')
            ->get()
            ->getResultArray();

        $items = [];
        foreach ($filas as $f) {
            $cantidad = (float) $carrito[(int) $f['id_producto']];
            $precio   = (float) $f['precio'];
            $stock    = (float) $f['stock_disponible'];
            $activo   = $f['estado'] === 'activo';

            $motivo = null;
            if (! $activo) {
                $motivo = 'Producto no disponible por el momento.';
            } elseif ($precio <= 0) {
                $motivo = 'Este producto aún no tiene precio.';
            } elseif ($stock < $cantidad) {
                $motivo = 'Solo hay ' . rtrim(rtrim(number_format($stock, 2, '.', ''), '0'), '.') . ' disponibles.';
            }

            $items[] = [
                'id'       => (int) $f['id_producto'],
                'nombre'   => $f['nombre'],
                'precio'   => $precio,
                'unidad'   => $f['unidad_venta'],
                'imagen'   => $f['imagen'] ?: 'default.jpg',
                'cantidad' => $cantidad,
                'subtotal' => round($precio * $cantidad, 2),
                'stock'    => $stock,
                'activo'   => $activo,
                'ok'       => $motivo === null,
                'motivo'   => $motivo,
            ];
        }

        return $items;
    }

    /**
     * Crea el pedido del cliente con estado inicial "pedido" (Pendiente).
     * Valida stock con bloqueo de filas para evitar sobreventa.
     */
    public function crearPedidoDesdeCarrito(int $idCliente, array $carrito, ?string $fechaEntrega, ?string $horario): array
    {
        $ids = array_map('intval', array_keys($carrito));
        if ($ids === []) {
            return ['success' => false, 'message' => 'Tu carrito está vacío.'];
        }

        $this->db->transBegin();

        try {
            $filas = $this->db->query(
                'SELECT p.id_producto, p.nombre, p.precio, p.unidad_venta, p.estado, COALESCE(e.exis_venta, 0) AS stock
                 FROM producto p
                 LEFT JOIN existencias e ON e.id_producto1 = p.id_producto
                 WHERE p.id_producto IN ? FOR UPDATE',
                [$ids]
            )->getResultArray();

            $porId = [];
            foreach ($filas as $f) {
                $porId[(int) $f['id_producto']] = $f;
            }

            $problemas = [];
            $total     = 0.0;
            $lineas    = [];

            foreach ($carrito as $id => $cantidad) {
                $id       = (int) $id;
                $cantidad = (float) $cantidad;
                $f        = $porId[$id] ?? null;

                if ($f === null || $f['estado'] !== 'activo' || (float) $f['precio'] <= 0) {
                    $problemas[] = ($f['nombre'] ?? 'Producto #' . $id) . ' (no disponible)';
                    continue;
                }
                if ((float) $f['stock'] < $cantidad) {
                    $problemas[] = $f['nombre'] . ' (disponible: ' . (float) $f['stock'] . ')';
                    continue;
                }

                $subtotal = round((float) $f['precio'] * $cantidad, 2);
                $total   += $subtotal;
                $lineas[] = [
                    'id_producto3' => $id,
                    'cantidad'     => $cantidad,
                    'precio_venta' => (float) $f['precio'],
                    'unidad_venta' => $f['unidad_venta'],
                    'total'        => $subtotal,
                ];
            }

            if ($problemas !== []) {
                $this->db->transRollback();
                return [
                    'success' => false,
                    'message' => 'Revisa tu carrito, algunos productos no tienen existencia suficiente: ' . implode(', ', $problemas) . '.',
                ];
            }

            $this->db->table('pedido')->insert([
                'fecha'                    => date('Y-m-d'),
                'total'                    => round($total, 2),
                'modalidad_pago'           => 'contado',
                'deuda_restante'           => 0,
                'fecha_entrega'            => null,
                'fecha_entrega_programada' => $fechaEntrega,
                'horario_entrega'          => $horario,
                'imagen_url'               => null,
                'id_cliente1'              => $idCliente,
                'id_repartidor'            => null,
                'en_espera'                => 0,
                'origen'                   => 'cliente',
            ]);
            $idPedido = (int) $this->db->insertID();

            $this->db->table('status')->insert([
                'estado'     => 'pedido',
                'id_pedido2' => $idPedido,
                'fecha'      => date('Y-m-d H:i:s'),
            ]);

            foreach ($lineas as $l) {
                $this->db->table('producto_pedido')->insert($l + ['id_pedido1' => $idPedido]);
            }

            $this->db->transCommit();

            return ['success' => true, 'id_pedido' => $idPedido, 'message' => 'Pedido enviado.'];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'No se pudo crear el pedido del cliente: ' . $e->getMessage());

            return ['success' => false, 'message' => 'No pudimos registrar tu pedido. Inténtalo de nuevo.'];
        }
    }

    // ─────────────────────────────────────────────
    //  HISTORIAL DEL CLIENTE
    // ─────────────────────────────────────────────

    private function baseListado(int $idCliente)
    {
        return $this->db->table('pedido p')
            ->select('p.id_pedido, p.fecha, p.total, p.modalidad_pago, p.deuda_restante, p.en_espera, p.id_repartidor,
                      p.fecha_entrega_programada, p.horario_entrega,
                      r.nombre AS repartidor_nombre, r.ap_paterno AS repartidor_apellido,
                      s.estado AS estado_actual, s.fecha AS estado_fecha')
            ->join('repartidor r', 'r.id_repartidor = p.id_repartidor', 'left')
            ->join(self::SUBQUERY_ULTIMO, 'latest.id_pedido2 = p.id_pedido', 'left')
            ->join('status s', 's.id_status = latest.max_id', 'left')
            ->where('p.id_cliente1', $idCliente);
    }

    private function decorar(array $p): array
    {
        $p['estado'] = self::describirEstado($p['estado_actual'] ?? null, $p['id_repartidor'] ?? null, $p['en_espera'] ?? 0);

        $nombre = trim(($p['repartidor_nombre'] ?? '') . ' ' . ($p['repartidor_apellido'] ?? ''));
        $p['repartidor'] = $nombre !== '' ? $nombre : null;

        $p['actualizado'] = ! empty($p['estado_fecha']) ? date('d/m/Y H:i', strtotime($p['estado_fecha'])) : '';

        return $p;
    }

    public function getPedidosCliente(int $idCliente, int $pagina = 1): array
    {
        $total   = $this->db->table('pedido')->where('id_cliente1', $idCliente)->countAllResults();
        $paginas = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina  = min(max(1, $pagina), $paginas);

        $pedidos = $this->baseListado($idCliente)
            ->orderBy('p.id_pedido', 'DESC')
            ->limit(self::POR_PAGINA, ($pagina - 1) * self::POR_PAGINA)
            ->get()
            ->getResultArray();

        $ids = array_column($pedidos, 'id_pedido');
        $productos = [];

        if ($ids !== []) {
            $filas = $this->db->table('producto_pedido pp')
                ->select('pp.id_pedido1, pp.cantidad, pp.precio_venta, pp.unidad_venta, pp.total, pr.nombre AS nombre_producto')
                ->join('producto pr', 'pr.id_producto = pp.id_producto3')
                ->whereIn('pp.id_pedido1', $ids)
                ->get()
                ->getResultArray();

            foreach ($filas as $f) {
                $productos[$f['id_pedido1']][] = $f;
            }
        }

        foreach ($pedidos as &$p) {
            $p = $this->decorar($p);
            $p['productos'] = $productos[$p['id_pedido']] ?? [];
        }
        unset($p);

        return ['pedidos' => $pedidos, 'total' => $total, 'paginas' => $paginas];
    }

    /** Estados actuales de los pedidos indicados (solo si pertenecen al cliente). */
    public function getEstadosPorIds(int $idCliente, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $filas = $this->baseListado($idCliente)
            ->whereIn('p.id_pedido', $ids)
            ->get()
            ->getResultArray();

        $salida = [];
        foreach ($filas as $f) {
            $f = $this->decorar($f);
            $salida[$f['id_pedido']] = $f['estado'] + [
                'repartidor'  => $f['repartidor'],
                'actualizado' => $f['actualizado'],
            ];
        }

        return $salida;
    }

    // ─────────────────────────────────────────────
    //  ACCIONES DEL ADMINISTRADOR
    // ─────────────────────────────────────────────

    public function getEstadoActual(int $idPedido): ?string
    {
        $fila = $this->db->table('status')
            ->where('id_pedido2', $idPedido)
            ->orderBy('id_status', 'DESC')
            ->get(1)
            ->getRowArray();

        return $fila['estado'] ?? null;
    }

    public function aceptarPedido(int $idPedido): array
    {
        $pedido = $this->db->table('pedido')->where('id_pedido', $idPedido)->get()->getRowArray();
        if (! $pedido) {
            return ['success' => false, 'message' => 'El pedido no existe.'];
        }
        if (($this->getEstadoActual($idPedido) ?? 'pedido') !== 'pedido') {
            return ['success' => false, 'message' => 'Este pedido ya no está pendiente.'];
        }

        // Si las reservas superan el existente, otro pedido se atendió primero.
        $filas = $this->db->table('producto_pedido pp')
            ->select('pr.nombre, e.exis_total_general, e.exis_bloqueo')
            ->join('producto pr', 'pr.id_producto = pp.id_producto3')
            ->join('existencias e', 'e.id_producto1 = pp.id_producto3', 'left')
            ->where('pp.id_pedido1', $idPedido)
            ->get()
            ->getResultArray();

        $faltan = [];
        foreach ($filas as $f) {
            if ($f['exis_total_general'] === null || (float) $f['exis_bloqueo'] > (float) $f['exis_total_general']) {
                $faltan[] = $f['nombre'];
            }
        }
        if ($faltan !== []) {
            return [
                'success'   => false,
                'sin_stock' => true,
                'message'   => 'No hay existencia suficiente para: ' . implode(', ', $faltan)
                    . '. Puedes dejar el pedido en "Pendiente" hasta reponer el producto.',
            ];
        }

        $this->db->transStart();
        $this->db->table('status')->insert([
            'estado'     => 'confirmado',
            'id_pedido2' => $idPedido,
            'fecha'      => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('pedido')->where('id_pedido', $idPedido)->update(['en_espera' => 0]);
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            return ['success' => false, 'message' => 'No se pudo aceptar el pedido.'];
        }

        return [
            'success'            => true,
            'message'            => 'Pedido aceptado.',
            'necesita_repartidor' => empty($pedido['id_repartidor']),
        ];
    }

    public function ponerEnEspera(int $idPedido, bool $valor): array
    {
        if (! $this->db->table('pedido')->where('id_pedido', $idPedido)->countAllResults()) {
            return ['success' => false, 'message' => 'El pedido no existe.'];
        }
        if (($this->getEstadoActual($idPedido) ?? 'pedido') !== 'pedido') {
            return ['success' => false, 'message' => 'Solo los pedidos pendientes pueden ponerse en espera.'];
        }

        $this->db->table('pedido')->where('id_pedido', $idPedido)->update(['en_espera' => $valor ? 1 : 0]);

        return [
            'success' => true,
            'message' => $valor ? 'El pedido quedó en espera.' : 'El pedido volvió a la lista de pendientes.',
        ];
    }

    public function asignarRepartidor(int $idPedido, int $idRepartidor): array
    {
        if (! $this->db->table('pedido')->where('id_pedido', $idPedido)->countAllResults()) {
            return ['success' => false, 'message' => 'El pedido no existe.'];
        }
        if (! $this->db->table('repartidor')->where('id_repartidor', $idRepartidor)->countAllResults()) {
            return ['success' => false, 'message' => 'El repartidor no existe.'];
        }
        if (! in_array($this->getEstadoActual($idPedido) ?? 'pedido', ['pedido', 'confirmado', 'en_transito'], true)) {
            return ['success' => false, 'message' => 'No se puede asignar repartidor a este pedido.'];
        }

        $this->db->table('pedido')->where('id_pedido', $idPedido)->update(['id_repartidor' => $idRepartidor]);

        return ['success' => true, 'message' => 'Repartidor asignado.'];
    }
}