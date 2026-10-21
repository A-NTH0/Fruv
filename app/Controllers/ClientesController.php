<?php
namespace App\Controllers;

use App\Libraries\AccountCredentials;
use App\Models\ClienteModel;

class ClientesController extends BaseController
{
    public function index()
    {
        $model    = new ClienteModel();
        $busqueda = trim($this->request->getVar('buscar') ?? '');

        $clientes = $model->getClientesConDireccion($busqueda)->paginate(9, 'group1');

        $data = [
            'clientes' => $clientes,
            'pager'    => $model->pager,
            'busqueda' => $busqueda,
        ];

        return view('clientes', $data);
    }

    public function guardar()
    {
        $input = [
            'nombre'     => trim((string) $this->request->getPost('nombre')),
            'ap_paterno' => trim((string) $this->request->getPost('ap_paterno')),
            'ap_materno' => trim((string) $this->request->getPost('ap_materno')),
            'telefono'   => trim((string) $this->request->getPost('telefono')),
            'email'      => strtolower(trim((string) $this->request->getPost('email'))),
        ];
        $rules = [
            'nombre'     => 'required|max_length[100]',
            'ap_paterno' => 'required|max_length[100]',
            'ap_materno' => 'permit_empty|max_length[100]',
            'telefono'   => 'required|max_length[12]',
            'email'      => 'required|valid_email|max_length[50]|is_unique[cliente.email]',
        ];

        if (! $this->validate($rules, $input)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $emailConfigurationError = AccountCredentials::configurationError();
        if ($emailConfigurationError !== null) {
            return redirect()->back()->withInput()->with('error', $emailConfigurationError);
        }

        $password = AccountCredentials::generatePassword();
        $model = new ClienteModel();
        $db    = db_connect();
        $db->transBegin();

        $idCliente = $model->insert([
            ...$input,
            'hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        if ($idCliente === false) {
            $db->transRollback();

            return redirect()->back()->withInput()->with('error', 'No se pudo guardar el cliente.');
        }

        $model->guardarDireccion($idCliente, [
            'calle'     => $this->request->getPost('calle'),
            'numero'    => $this->request->getPost('numero'),
            'colonia'   => $this->request->getPost('colonia'),
            'municipio' => $this->request->getPost('municipio'),
            'estado'    => $this->request->getPost('estado'),
        ]);

        $nombre = trim($input['nombre'] . ' ' . $input['ap_paterno'] . ' ' . $input['ap_materno']);
        if (! $db->transStatus() || ! AccountCredentials::sendPassword($input['email'], $nombre, 'cliente', $password)) {
            $db->transRollback();

            return redirect()->back()->withInput()->with('error', 'No se pudo enviar el correo de acceso. Verifica los datos del servidor SMTP y vuelve a intentarlo.');
        }

        $db->transCommit();
        if (! $db->transStatus()) {
            return redirect()->back()->with('error', 'No se pudo completar el registro del cliente.');
        }

        return redirect()->to(base_url('clientes'))->with('credential', [
            'nombre'    => $nombre,
            'email'     => $input['email'],
            'password'  => $password,
            'tipo'      => 'Cliente',
        ]);
    }

    public function actualizar()
    {
        $model = new ClienteModel();
        $id    = (int) $this->request->getPost('id_cliente');
        $email = strtolower(trim((string) $this->request->getPost('email')));

        if (! $this->validate([
            'email' => "required|valid_email|max_length[50]|is_unique[cliente.email,id_cliente,{$id}]",
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $model->update($id, [
            'nombre'     => $this->request->getPost('nombre'),
            'ap_paterno' => $this->request->getPost('ap_paterno'),
            'ap_materno' => $this->request->getPost('ap_materno'),
            'telefono'   => $this->request->getPost('telefono'),
            'email'      => $email,
        ]);

        $model->actualizarDireccion($id, [
            'calle'     => $this->request->getPost('calle'),
            'numero'    => $this->request->getPost('numero'),
            'colonia'   => $this->request->getPost('colonia'),
            'municipio' => $this->request->getPost('municipio'),
            'estado'    => $this->request->getPost('estado'),
        ]);

        return redirect()->to(base_url('clientes'));
    }

    /** Ajuste manual: el administrador marca o quita a un cliente como preferencial. */
    public function preferencial()
    {
        $id    = (int) $this->request->getPost('id_cliente');
        $valor = (int) $this->request->getPost('valor') === 1;

        $model   = new ClienteModel();
        $cliente = $id > 0 ? $model->find($id) : null;
        if (! $cliente || (int) $cliente['baja'] === 1) {
            return $this->response->setJSON(['success' => false, 'message' => 'El cliente no existe o está dado de baja.']);
        }

        $model->setPreferencialManual($id, $valor);

        return $this->response->setJSON([
            'success' => true,
            'message' => $valor ? 'Cliente marcado como preferencial.' : 'Se quitó la marca manual de preferencial.',
        ]);
    }

    public function darDeBaja()
    {
        $id = (int) $this->request->getPost('id_cliente');
        if ($id > 0) {
            (new ClienteModel())->darDeBaja($id);
        }
        return redirect()->to(base_url('clientes'));
    }

    public function verificarEliminar($id = null)
    {
        if (!$id) {
            return $this->response->setJSON(['puede' => false, 'mensaje' => 'ID no válido']);
        }

        $model = new ClienteModel();

        if ($model->tienePedidos((int) $id)) {
            return $this->response->setJSON([
                'puede'   => false,
                'mensaje' => 'Este cliente tiene pedidos asociados y no puede ser eliminado. Si deseas eliminarlo, primero cancela o elimina todos sus pedidos.',
            ]);
        }

        return $this->response->setJSON(['puede' => true]);
    }

    public function eliminar($id = null)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return redirect()->to(base_url('clientes'))->with('error', 'No se indicó un cliente válido para eliminar.');
        }

        $model = new ClienteModel();
        if ($model->find($id) === null) {
            return redirect()->to(base_url('clientes'))->with('error', 'El cliente que intentas eliminar ya no existe.');
        }

        if ($model->tienePedidos($id)) {
            return redirect()->to(base_url('clientes'))->with('error', 'No se puede eliminar este cliente porque tiene pedidos asociados. Puedes darlo de baja para conservar su historial.');
        }

        $db = db_connect();
        $db->transBegin();
        $db->table('direccion')->where('id_cliente', $id)->delete();
        $eliminado = $db->transStatus() && $model->delete($id) !== false && $db->transStatus();

        if (! $eliminado) {
            $db->transRollback();

            return redirect()->to(base_url('clientes'))->with('error', 'No se pudo eliminar el cliente y sus datos relacionados.');
        }

        $db->transCommit();
        if (! $db->transStatus()) {
            return redirect()->to(base_url('clientes'))->with('error', 'No se pudo completar la eliminación del cliente.');
        }

        return redirect()->to(base_url('clientes'));
    }
}