<?php

namespace App\Controllers;

class LogingController extends BaseController
{
    public function login(){
        $data = [];
        helper(['form']);

        if ($this->request->getMethod()=='post'){

            $rules = [
                'nombre' => 'required|min_length[3]|max_length[20]',
                'apellido_p' => 'required|min_length[3]|max_length[20]',
                'apellido_m' => 'required|min_length[3]|max_length[20]',
                'telefono' => 'required|min_length[3]|max_length[20]',
                'email' => 'required|min_length[6]|max_length[50]|valid_email|is_unique[user.email]',
                'contrasena' => 'required|min_length[8]|max_length[255]',
            ];

            if (! $this->validate($rules)){
                $data['validation'] = $this->validator;
            }else
            $model = new UserModel();

            $newsData = [
                'nombre'
                'apellido_p'
                'apellido_m'
                
            ]
        }

        echo view('login.php', $data)
    }

    public function registrtar()
    {
        $data
    }
}
