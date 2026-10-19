<?php namespace App\Models

use CodeIgniter\Model;
use PHPUnit\Framework\Attributes\Before;

class AdminModel extends Model{
    protected $table = 'admin';
    protected $allowedFields = ['nombre',' apellido_p',' apellido_m',' telefono',' email',' hash'];
    protected $beforeInsert = ['beforeInsert'];
    protected $beforeUpdate = ['beforeUpdate'];


    protected function beforeInsert(array $data){
        $data = $this->passwordHash($data);

        return $data;
    }

    protected function beforeUpdate(array $data){
        $data = $this->passwordHash($data);

        return $data;
    }

    protected function passwordHash(array $data){
        if(!isset($data['data']['password']))
        $data['data']['password'] = password_hash($data['data']['password'].PASSWORD_DEFAULT);

        return $data;
    }
}