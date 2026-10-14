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
            ->select('p.nombre, e.exis_total_general, e.exis_bloqueo, e.exis_venta')
            ->join('producto p', 'p.id_producto = e.id_producto1')
            ->get()
            ->getResultArray();
    }

    /**
     * Actualiza (o crea) el registro de existencias cuando llega una nueva entrada.
     * - exis_total_general: suma de todas las conversiones de ese producto en 'entrada'
     * - exis_venta: total_general - bloqueo
     */
    public function actualizarDesdeEntrada(int $idProducto): bool
    {
        $db = \Config\Database::connect();

        // Suma de la conversión (unidades de venta) de todas las entradas del producto
        $totalConversion = (float) $db->table('entrada')
            ->selectSum('conversion')
            ->where('id_producto2', $idProducto)
            ->get()
            ->getRow()
            ->conversion;

        $existeRegistro = $db->table('existencias')
            ->where('id_producto1', $idProducto)
            ->countAllResults() > 0;

        if ($existeRegistro) {
            // Obtener el bloqueo actual para recalcular exis_venta
            $bloqueo = (float) $db->table('existencias')
                ->select('exis_bloqueo')
                ->where('id_producto1', $idProducto)
                ->get()
                ->getRow()
                ->exis_bloqueo;

            return $db->table('existencias')->where('id_producto1', $idProducto)->update([
                'exis_total_general' => $totalConversion,
                'exis_venta'         => max(0, $totalConversion - $bloqueo),
                'fecha'              => date('Y-m-d'),
            ]);
        } else {
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
}