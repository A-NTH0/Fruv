<?php

namespace App\Models;

use CodeIgniter\Model;

class AdminModel extends Model
{
    protected $table         = 'admin';
    protected $allowedFields = ['nombre', 'apellido_p', 'apellido_m', 'telefono', 'email', 'hash'];
}