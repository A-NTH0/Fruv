<?php namespace App\Models;
use CodeIgniter\Model;

class MermaModel extends Model {
    protected $table      = 'merma';
    protected $primaryKey = 'id_merma';
    protected $allowedFields = [
        'cantidad',
        'fecha',
        'notas',
        'id_entrada'
    ];

    public function getAllMermas()
    {
        $db = \Config\Database::connect();
        return $db->table('merma m')
            ->select('m.*, e.cantidad as cantidad_entrada, p.nombre as nombre_producto')
            ->join('entrada e', 'e.id_entrada = m.id_entrada')
            ->join('producto p', 'p.id_producto = e.id_producto2')
            ->get()
            ->getResultArray();
    }

    public function registrarMerma($data)
    {
        $db = \Config\Database::connect();

        // Usar transacción para asegurar consistencia
        $db->transStart();

        $inserted = $db->table($this->table)->insert($data);

        if ($inserted) {
            $idEntrada = $data['id_entrada'];
            $cantidad  = (float) $data['cantidad'];

            $entrada = $db->table('entrada')
                          ->select('id_producto2')
                          ->where('id_entrada', $idEntrada)
                          ->get()
                          ->getRowArray();

            if ($entrada) {
                $idProducto = $entrada['id_producto2'];

                $existencia = $db->table('existencias')
                                 ->where('id_producto1', $idProducto)
                                 ->get()
                                 ->getRowArray();

                if ($existencia) {
                    $nuevoTotal = max(0, $existencia['exis_total_general'] - $cantidad);
                    $nuevoVenta = max(0, $nuevoTotal - $existencia['exis_bloqueo']);

                    $db->table('existencias')
                       ->where('id_producto1', $idProducto)
                       ->update([
                           'exis_total_general' => $nuevoTotal,
                           'exis_venta'         => $nuevoVenta,
                       ]);
                }
            }
        }

        $db->transComplete();

        return ($inserted && $db->transStatus());
    }
}
