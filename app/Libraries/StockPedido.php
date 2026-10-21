<?php

namespace App\Libraries;

use Config\Database;

/**
 * Descuento y reposición de existencias de un pedido.
 *
 * Regla de negocio: el stock se descuenta UNA sola vez, cuando el administrador
 * acepta el pedido (estado "confirmado"). Mientras el pedido está Pendiente las
 * existencias no se tocan. Si el pedido se cancela (o vuelve a Pendiente) después
 * de aceptado, se repone exactamente lo descontado.
 *
 * La bandera pedido.stock_descontado evita descontar o reponer dos veces.
 *
 * IMPORTANTE: estos métodos NO abren transacción. Deben llamarse dentro de la
 * transacción del llamador para que el bloqueo (FOR UPDATE) y las escrituras
 * sean atómicos. Si descontar() devuelve ok=false no escribió nada.
 */
class StockPedido
{
    public static function estaDescontado(int $idPedido): bool
    {
        $fila = Database::connect()->table('pedido')
            ->select('stock_descontado')
            ->where('id_pedido', $idPedido)
            ->get()
            ->getRowArray();

        return $fila !== null && (int) $fila['stock_descontado'] === 1;
    }

    /**
     * Valida y descuenta el stock de todas las líneas del pedido.
     *
     * @return array{ok: bool, sin_stock: bool, message: string}
     */
    public static function descontar(int $idPedido): array
    {
        $db = Database::connect();

        $pedido = $db->query(
            'SELECT stock_descontado FROM pedido WHERE id_pedido = ? FOR UPDATE',
            [$idPedido]
        )->getRowArray();

        if (! $pedido) {
            return ['ok' => false, 'sin_stock' => false, 'message' => 'El pedido no existe.'];
        }
        if ((int) $pedido['stock_descontado'] === 1) {
            return ['ok' => true, 'sin_stock' => false, 'message' => 'El stock de este pedido ya estaba descontado.'];
        }

        // 1) Validación de TODAS las líneas antes de escribir nada.
        $faltantes = [];
        $aplicar   = [];

        foreach (self::lineas($idPedido) as $l) {
            $ex = $db->query(
                'SELECT e.id_existencias, e.exis_total_general, e.exis_venta, p.nombre
                   FROM producto p
              LEFT JOIN existencias e ON e.id_producto1 = p.id_producto
                  WHERE p.id_producto = ?
               ORDER BY e.id_existencias ASC
                  LIMIT 1 FOR UPDATE',
                [(int) $l['id']]
            )->getRowArray() ?? [];

            $pide       = round((float) $l['cantidad'], 2);
            $venta      = round((float) ($ex['exis_venta'] ?? 0), 2);
            $total      = round((float) ($ex['exis_total_general'] ?? 0), 2);
            $disponible = max(0.0, min($venta, $total));

            if (($ex['id_existencias'] ?? null) === null || $disponible + 0.004 < $pide) {
                $faltantes[] = ($ex['nombre'] ?? 'Producto #' . $l['id'])
                    . ' (pide ' . self::fmt($pide) . ', disponible ' . self::fmt($disponible) . ')';
                continue;
            }

            $aplicar[] = ['id' => (int) $l['id'], 'cantidad' => number_format($pide, 2, '.', '')];
        }

        if ($faltantes !== []) {
            return [
                'ok'        => false,
                'sin_stock' => true,
                'message'   => 'No hay existencia suficiente para: ' . implode(', ', $faltantes) . '.',
            ];
        }

        // 2) Descuento. Cada columna se calcula solo con su propio valor anterior
        //    (sin referenciar otra columna ya modificada en el mismo UPDATE).
        foreach ($aplicar as $f) {
            $db->query(
                'UPDATE existencias
                    SET exis_total_general = exis_total_general - ?,
                        exis_venta         = GREATEST(0, exis_venta - ?)
                  WHERE id_producto1 = ?',
                [$f['cantidad'], $f['cantidad'], $f['id']]
            );
        }

        $db->table('pedido')->where('id_pedido', $idPedido)->update(['stock_descontado' => 1]);

        return ['ok' => true, 'sin_stock' => false, 'message' => 'Stock descontado.'];
    }

    /** Repone lo descontado. No hace nada si el pedido no tenía stock descontado. */
    public static function reponer(int $idPedido): void
    {
        $db = Database::connect();

        $pedido = $db->query(
            'SELECT stock_descontado FROM pedido WHERE id_pedido = ? FOR UPDATE',
            [$idPedido]
        )->getRowArray();

        if (! $pedido || (int) $pedido['stock_descontado'] !== 1) {
            return;
        }

        foreach (self::lineas($idPedido) as $l) {
            $cantidad = number_format(round((float) $l['cantidad'], 2), 2, '.', '');

            $db->query(
                'UPDATE existencias
                    SET exis_total_general = exis_total_general + ?,
                        exis_venta         = exis_venta + ?
                  WHERE id_producto1 = ?',
                [$cantidad, $cantidad, (int) $l['id']]
            );
        }

        $db->table('pedido')->where('id_pedido', $idPedido)->update(['stock_descontado' => 0]);
    }

    /** Cantidades del pedido agrupadas por producto (un producto repetido se suma). */
    private static function lineas(int $idPedido): array
    {
        return Database::connect()->query(
            'SELECT id_producto3 AS id, SUM(cantidad) AS cantidad
               FROM producto_pedido
              WHERE id_pedido1 = ?
           GROUP BY id_producto3',
            [$idPedido]
        )->getResultArray();
    }

    private static function fmt(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.') ?: '0';
    }
}