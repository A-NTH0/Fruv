<?php
namespace App\Models;
use CodeIgniter\Model;

class RepartidorModel extends Model
{
    protected $table         = 'repartidor';
    protected $primaryKey    = 'id_repartidor';
    protected $allowedFields = [
        'nombre',
        'ap_paterno',
        'ap_materno',
        'telefono',
        'direccion',
        'notas',
        'email',
        'hash',
        'email_verified_at',
        'email_verification_code_hash',
        'email_verification_expires_at',
        'email_verification_sent_at',
        'email_verification_attempts',
        'email_verification_locked_until',
    ];


    public function tienePedidos(int $idRepartidor): bool
    {
        $db = \Config\Database::connect();
        return $db->table('pedido')
                  ->where('id_repartidor', $idRepartidor)
                  ->countAllResults() > 0;
    }

    public function getRepartidores(string $busqueda = ''): static
    {
        $this->select('id_repartidor, nombre, ap_paterno, ap_materno, telefono, direccion, notas, email');

        if ($busqueda !== '') {
            $this->groupStart()
                 ->like('nombre', $busqueda)
                 ->orLike('ap_paterno', $busqueda)
                 ->orLike('telefono', $busqueda)
                 ->orLike('email', $busqueda)
                 ->groupEnd();
        }

        return $this;
    }
}
