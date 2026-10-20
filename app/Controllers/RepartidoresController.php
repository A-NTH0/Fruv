<?php
namespace App\Controllers;

use App\Libraries\AccountCredentials;
use App\Models\RepartidorModel;

class RepartidoresController extends BaseController
{
    public function index()
    {
        $model    = new RepartidorModel();
        $busqueda = trim($this->request->getVar('buscar') ?? '');

        $repartidores = $model->getRepartidores($busqueda)->paginate(9, 'group1');

        $data = [
            'title'        => 'Catálogo de Repartidores',
            'repartidores' => $repartidores,
            'pager'        => $model->pager,
            'busqueda'     => $busqueda,
            'isAjax'       => $this->request->isAJAX(),
        ];

        return view('repartidores', $data);
    }

    public function guardar()
    {
        $input = [
            'nombre'     => trim((string) $this->request->getPost('nombre')),
            'ap_paterno' => trim((string) $this->request->getPost('ap_paterno')),
            'ap_materno' => trim((string) $this->request->getPost('ap_materno')),
            'telefono'   => trim((string) $this->request->getPost('telefono')),
            'direccion'  => trim((string) $this->request->getPost('direccion')),
            'notas'      => trim((string) $this->request->getPost('notas')),
            'email'      => strtolower(trim((string) $this->request->getPost('email'))),
        ];
        $rules = [
            'nombre'     => 'required|max_length[100]',
            'ap_paterno' => 'required|max_length[100]',
            'ap_materno' => 'permit_empty|max_length[100]',
            'telefono'   => 'required|max_length[12]',
            'direccion'  => 'permit_empty|max_length[500]',
            'notas'      => 'permit_empty|max_length[500]',
            'email'      => 'required|valid_email|max_length[50]|is_unique[repartidor.email]',
        ];

        if (! $this->validate($rules, $input)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $emailConfigurationError = AccountCredentials::configurationError();
        if ($emailConfigurationError !== null) {
            return redirect()->back()->withInput()->with('error', $emailConfigurationError);
        }

        $password = AccountCredentials::generatePassword();
        $model = new RepartidorModel();
        $db    = db_connect();
        $db->transBegin();

        $repartidorId = $model->insert([
            ...$input,
            'hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        if ($repartidorId === false) {
            $db->transRollback();

            return redirect()->back()->withInput()->with('error', 'No se pudo guardar el repartidor.');
        }

        $nombre = trim($input['nombre'] . ' ' . $input['ap_paterno'] . ' ' . $input['ap_materno']);
        if (! $db->transStatus() || ! AccountCredentials::sendPassword($input['email'], $nombre, 'repartidor', $password)) {
            $db->transRollback();

            return redirect()->back()->withInput()->with('error', 'No se pudo enviar el correo de acceso. Verifica los datos del servidor SMTP y vuelve a intentarlo.');
        }

        $db->transCommit();
        if (! $db->transStatus()) {
            return redirect()->back()->with('error', 'No se pudo completar el registro del repartidor.');
        }

        return redirect()->to(base_url('repartidores'))->with('credential', [
            'nombre'    => $nombre,
            'email'     => $input['email'],
            'password'  => $password,
            'tipo'      => 'Repartidor',
        ]);
    }

    public function actualizar()
    {
        $model = new RepartidorModel();
        $id    = (int) $this->request->getPost('id_repartidor');
        $email = strtolower(trim((string) $this->request->getPost('email')));

        if (! $this->validate([
            'email' => "required|valid_email|max_length[50]|is_unique[repartidor.email,id_repartidor,{$id}]",
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $model->update($id, [
            'nombre'     => $this->request->getPost('nombre'),
            'ap_paterno' => $this->request->getPost('ap_paterno'),
            'ap_materno' => $this->request->getPost('ap_materno'),
            'telefono'   => $this->request->getPost('telefono'),
            'direccion'  => $this->request->getPost('direccion'),
            'notas'      => $this->request->getPost('notas'),
            'email'      => $email,
        ]);

        return redirect()->to(base_url('repartidores'));
    }

    public function verificarEliminar($id = null)
    {
        if (!$id) {
            return $this->response->setJSON(['puede' => false, 'mensaje' => 'ID no válido']);
        }

        $model = new RepartidorModel();

        if ($model->tienePedidos((int) $id)) {
            return $this->response->setJSON([
                'puede'   => false,
                'mensaje' => 'Este repartidor tiene pedidos asignados y no puede ser eliminado. Si deseas eliminarlo, primero reasigna o elimina todos sus pedidos.',
            ]);
        }

        return $this->response->setJSON(['puede' => true]);
    }

    public function eliminar($id = null)
    {
        if ($id) {
            $model = new RepartidorModel();
            $model->delete($id);
        }
        return redirect()->to(base_url('repartidores'));
    }
}
