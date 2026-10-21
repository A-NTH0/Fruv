<?php namespace App\Models;
use CodeIgniter\Model;

class ExistenciasModel extends Model {
    protected $table      = 'existencias';
    protected $primaryKey = 'id_existencias';
    protected $allowedFields = [
        'exis_total_general',
        'exis_bloqueo',
        'exis_venta',
        'fecha',
        'id_producto1',
        'exis_total_dia'
    ];

    public function getAllExistencias()
    {
        $db = \Config\Database::connect();
        return $db->table('existencias e')
            ->select('p.id_producto, p.nombre, p.categoria, p.unidad_venta, p.estado, e.exis_total_general, e.exis_bloqueo, e.exis_venta')
            ->join('producto p', 'p.id_producto = e.id_producto1')
            ->orderBy('p.nombre', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function actualizarDesdeEntrada(int $idProducto): bool
    {
        $db = \Config\Database::connect();

        $existeRegistro = $db->table('existencias')
            ->where('id_producto1', $idProducto)
            ->countAllResults() > 0;

        if ($existeRegistro) {
            // El trigger actualizar_por_nueva_entrada ya sumó la conversión al total físico.
            // Aquí NO se recalcula el total desde el histórico de entradas (eso borraba los
            // descuentos por ventas y mermas); solo se recalcula lo disponible.
            return (bool) $db->query(
                'UPDATE existencias
                    SET exis_venta = GREATEST(0, exis_total_general - exis_bloqueo),
                        fecha      = ?
                  WHERE id_producto1 = ?',
                [date('Y-m-d'), $idProducto]
            );
        }

        // Producto sin fila de existencias: el trigger no tenía nada que actualizar.
        $totalConversion = (float) $db->table('entrada')
            ->selectSum('conversion')
            ->where('id_producto2', $idProducto)
            ->get()
            ->getRow()
            ->conversion;

        return $db->table('existencias')->insert([
            'id_producto1'       => $idProducto,
            'exis_total_general' => $totalConversion,
            'exis_bloqueo'       => 0,
            'exis_venta'         => $totalConversion,
            'exis_total_dia'     => 0,
            'fecha'              => date('Y-m-d'),
        ]);
    }
}