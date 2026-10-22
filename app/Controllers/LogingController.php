<?php

namespace App\Controllers;

use App\Models\AdminModel;
use App\Models\ClienteModel;
use App\Models\VendedorModel;

class LogingController extends BaseController
{
    public function login()
    {
        if (session()->get('isLoggedIn')) {
            return redirect()->to($this->homeForRole());
        }

        return view('login');
    }

    public function autenticar()
    {
        $rules = [
            'email'      => 'required|valid_email|max_length[50]',
            'contrasena' => 'required|max_length[255]',
        ];
        $email = strtolower(trim((string) $this->request->getPost('email')));
        $contrasena = (string) $this->request->getPost('contrasena');

        if (! $this->validate($rules, [
            'email'      => $email,
            'contrasena' => $contrasena,
        ])) {
            return view('login', [
                'validation' => $this->validator,
                'email'      => $email,
            ]);
        }

        $admin = (new AdminModel())->where('email', $email)->first();
        if ($admin !== null && isset($admin['hash']) && password_verify($contrasena, $admin['hash'])) {
            session()->regenerate(true);
            session()->remove(['cliente_id', 'cliente_nombre', 'vendedor_id', 'vendedor_nombre']);
            session()->set([
                'auth_role'    => 'admin',
                'admin_email'  => $admin['email'],
                'admin_nombre' => $admin['nombre'],
                'isLoggedIn'   => true,
            ]);

            return redirect()->to(base_url('dashboard'));
        }

        $vendedor = (new VendedorModel())->where('email', $email)->first();
        if ($vendedor !== null && isset($vendedor['hash']) && password_verify($contrasena, $vendedor['hash'])) {
            session()->regenerate(true);
            session()->remove(['admin_email', 'admin_nombre', 'cliente_id', 'cliente_nombre', 'carrito']);
            session()->set([
                'auth_role'     => 'vendedor',
                'vendedor_id'   => $vendedor['id'],
                'vendedor_nombre' => $vendedor['nombre'],
                'isLoggedIn'    => true,
            ]);

            return redirect()->to(base_url('clientes'));
        }

        $cliente = (new ClienteModel())
            ->where('email', $email)
            ->where('baja', 0)
            ->first();

        if ($cliente !== null && isset($cliente['hash']) && password_verify($contrasena, $cliente['hash'])) {
            session()->regenerate(true);
            session()->remove(['admin_email', 'admin_nombre', 'vendedor_id', 'vendedor_nombre']);
            session()->set([
                'auth_role'   => 'cliente',
                'cliente_id'  => $cliente['id_cliente'],
                'cliente_nombre' => $cliente['nombre'],
                'isLoggedIn'  => true,
            ]);

            return redirect()->to(base_url('productos'));
        }

        return view('login', [
            'error' => 'El correo o la contraseña no son correctos.',
            'email' => $email,
        ]);
    }

    public function cerrarSesion()
    {
        session()->destroy();

        return redirect()->to(base_url('login'));
    }

    private function homeForRole(): string
    {
        return match (session()->get('auth_role')) {
            'cliente'   => base_url('productos'),
            'vendedor'  => base_url('clientes'),
            default     => base_url('dashboard'),
        };
    }
}
