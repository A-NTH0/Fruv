<?php
namespace App\Models;
use CodeIgniter\Model;

class RepartidorModel extends Model
{
    protected $table         = 'repartidor';
    protected $primaryKey    = 'id_repartidor';
    protected $allowedFields = ['nombre', 'ap_paterno', 'ap_materno', 'telefono', 'direccion', 'notas'];


    public function tienePedidos(int $idRepartidor): bool
    {
        $db = \Config\Database::connect();
        return $db->table('pedido')
                  ->where('id_repartidor', $idRepartidor)
                  ->countAllResults() > 0;
    }

    public function getRepartidores(string $busqueda = ''): static
    {
        if ($busqueda !== '') {
            $this->groupStart()
                 ->like('nombre', $busqueda)
                 ->orLike('ap_paterno', $busqueda)
                 ->orLike('telefono', $busqueda)
                 ->groupEnd();
        }

        return $this;
    }
}
