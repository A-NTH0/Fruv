<?php

namespace App\Models;

use CodeIgniter\Model;

class VendedorModel extends Model
{
    protected $table         = 'vendedor';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'nombre',
        'apellido_p',
        'apellido_m',
        'telefono',
        'email',
        'hash',
        'direccion',
        'notas',
        'email_verified_at',
        'email_verification_code_hash',
        'email_verification_expires_at',
        'email_verification_sent_at',
        'email_verification_attempts',
        'email_verification_locked_until',
    ];

    public function getVendedores(string $busqueda = ''): static
    {
        $this->select('id, nombre, apellido_p, apellido_m, telefono, email, direccion, notas');

        if ($busqueda !== '') {
            $this->groupStart()
                ->like('nombre', $busqueda)
                ->orLike('apellido_p', $busqueda)
                ->orLike('apellido_m', $busqueda)
                ->orLike('telefono', $busqueda)
                ->orLike('email', $busqueda)
                ->groupEnd();
        }

        return $this;
    }
}
