<?php

namespace App\Controllers;

use App\Libraries\AccountCredentials;
use App\Models\VendedorModel;

class VendedoresController extends BaseController
{
    public function index()
    {
        $model    = new VendedorModel();
        $busqueda = trim((string) $this->request->getGet('buscar'));
        $vendedores = $model->getVendedores($busqueda)->paginate(9, 'vendedores');

        return view('vendedores', [
            'title'       => 'Catálogo de Vendedores',
            'vendedores'  => $vendedores,
            'pager'       => $model->pager,
            'busqueda'    => $busqueda,
            'isAjax'      => false,
        ]);
    }

    public function guardar()
    {
        $input = $this->datosVendedor();
        $rules = $this->reglasVendedor();
        $rules['email'] .= '|is_unique[vendedor.email]';

        if (! $this->validate($rules, $input)) {
            return redirect()->back()->withInput()
                ->with('error', implode(' ', $this->validator->getErrors()))
                ->with('modalVendedor', 'nuevo');
        }

        $emailConfigurationError = AccountCredentials::configurationError();
        if ($emailConfigurationError !== null) {
            return redirect()->back()->withInput()
                ->with('error', $emailConfigurationError)
                ->with('modalVendedor', 'nuevo');
        }

        $password = AccountCredentials::generatePassword();
        $model = new VendedorModel();
        $db = db_connect();
        $db->transBegin();

        $id = $model->insert([
            ...$input,
            'hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        if ($id === false) {
            $db->transRollback();

            return redirect()->back()->withInput()
                ->with('error', 'No se pudo guardar el vendedor.')
                ->with('modalVendedor', 'nuevo');
        }

        $nombre = trim($input['nombre'] . ' ' . $input['apellido_p'] . ' ' . $input['apellido_m']);
        if (! $db->transStatus() || ! AccountCredentials::sendPassword($input['email'], $nombre, 'vendedor', $password)) {
            $db->transRollback();

            return redirect()->back()->withInput()
                ->with('error', 'No se pudo enviar el correo de acceso. Verifica los datos del servidor SMTP y vuelve a intentarlo.')
                ->with('modalVendedor', 'nuevo');
        }

        $db->transCommit();
        if (! $db->transStatus()) {
            return redirect()->back()->with('error', 'No se pudo completar el registro del vendedor.');
        }

        return redirect()->to(base_url('vendedores'))->with('credential', [
            'nombre'   => $nombre,
            'email'    => $input['email'],
            'password' => $password,
            'tipo'     => 'Vendedor',
        ]);
    }

    public function actualizar()
    {
        $id    = (int) $this->request->getPost('id');
        $input = $this->datosVendedor();
        $rules = $this->reglasVendedor();
        $rules['email'] .= "|is_unique[vendedor.email,id,{$id}]";

        if ($id < 1 || ! $this->validate($rules, $input)) {
            $mensaje = $id < 1
                ? 'El vendedor seleccionado no es válido.'
                : implode(' ', $this->validator->getErrors());

            return redirect()->back()->withInput()
                ->with('error', $mensaje)
                ->with('modalVendedor', 'editar');
        }

        $model = new VendedorModel();
        if ($model->find($id) === null) {
            return redirect()->to(base_url('vendedores'))->with('error', 'El vendedor ya no existe.');
        }

        if (! $model->update($id, $input)) {
            return redirect()->back()->withInput()
                ->with('error', 'No se pudo actualizar el vendedor.')
                ->with('modalVendedor', 'editar');
        }

        return redirect()->to(base_url('vendedores'))->with('success', 'Vendedor actualizado correctamente.');
    }

    public function eliminar()
    {
        $id = (int) $this->request->getPost('id');
        $model = new VendedorModel();

        if ($id < 1 || $model->find($id) === null) {
            return redirect()->to(base_url('vendedores'))->with('error', 'El vendedor seleccionado no existe.');
        }

        if (! $model->delete($id)) {
            return redirect()->to(base_url('vendedores'))->with('error', 'No se pudo eliminar el vendedor.');
        }

        return redirect()->to(base_url('vendedores'))->with('success', 'Vendedor eliminado correctamente.');
    }

    private function datosVendedor(): array
    {
        return [
            'nombre'     => trim((string) $this->request->getPost('nombre')),
            'apellido_p' => trim((string) $this->request->getPost('apellido_p')),
            'apellido_m' => trim((string) $this->request->getPost('apellido_m')),
            'telefono'   => trim((string) $this->request->getPost('telefono')),
            'email'      => strtolower(trim((string) $this->request->getPost('email'))),
            'direccion'  => trim((string) $this->request->getPost('direccion')),
            'notas'      => trim((string) $this->request->getPost('notas')),
        ];
    }

    private function reglasVendedor(): array
    {
        return [
            'nombre'     => 'required|max_length[20]',
            'apellido_p' => 'required|max_length[20]',
            'apellido_m' => 'required|max_length[20]',
            'telefono'   => 'required|max_length[20]',
            'email'      => 'required|valid_email|max_length[50]',
            'direccion'  => 'permit_empty|max_length[50]',
            'notas'      => 'permit_empty|max_length[100]',
        ];
    }
}
