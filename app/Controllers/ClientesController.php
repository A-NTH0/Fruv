<?php
namespace App\Controllers;

use App\Libraries\AccountCredentials;
use App\Models\ClienteModel;
use App\Models\PedidoClienteModel;
use App\Models\ProductoModel;

class ClientesController extends BaseController
{
    public function index()
    {
        $model    = new ClienteModel();
        $busqueda = trim($this->request->getVar('buscar') ?? '');

        $clientes = $model->getClientesConDireccion($busqueda)->paginate(9, 'group1');

        if (session()->get('auth_role') === 'vendedor') {
            return view('clientes_vendedor', [
                'clientes' => $clientes,
                'pager'    => $model->pager,
                'busqueda' => $busqueda,
            ]);
        }

        $data = [
            'clientes' => $clientes,
            'pager'    => $model->pager,
            'busqueda' => $busqueda,
        ];

        return view('clientes', $data);
    }

    public function historial(int $idCliente)
    {
        $cliente = (new ClienteModel())
            ->select('id_cliente, nombre, ap_paterno, ap_materno, telefono, email')
            ->where('baja', 0)
            ->find($idCliente);

        if (! $cliente) {
            return redirect()->to(base_url('clientes'))->with('error', 'El cliente no existe o está dado de baja.');
        }

        $pagina = max(1, (int) $this->request->getGet('page'));
        $datos  = (new PedidoClienteModel())->getPedidosCliente($idCliente, $pagina);

        return view('historial_cliente_vendedor', [
            'cliente' => $cliente,
            'pedidos' => $datos['pedidos'],
            'total'   => $datos['total'],
            'paginas' => $datos['paginas'],
            'pagina'  => min($pagina, $datos['paginas']),
        ]);
    }

    public function recargar()
    {
        $idCliente = (int) $this->request->getPost('id_cliente');
        $idPedido  = (int) $this->request->getPost('id_pedido');
        $url       = base_url('clientes/historial/' . $idCliente);

        $cliente = (new ClienteModel())
            ->where('baja', 0)
            ->find($idCliente);
        if ($idCliente < 1 || $idPedido < 1 || ! $cliente) {
            return redirect()->to(base_url('clientes'))->with('error', 'No se pudo recargar el pedido: cliente o pedido no válido.');
        }

        $cantidades = $this->request->getPost('cantidades');
        if (! is_array($cantidades)) {
            return redirect()->to($url)->with('error', 'No se recibieron las cantidades para recargar el pedido.');
        }

        $resultado = (new PedidoClienteModel())->recargarPedido($idCliente, $idPedido, $cantidades);
        if (! $resultado['success']) {
            return redirect()->to($url)->with('error', $resultado['message']);
        }

        return redirect()->to($url)->with('success', 'Pedido #' . $resultado['numero_pedido_cliente'] . ' creado a partir del pedido seleccionado.');
    }

    public function nuevoPedido(int $idCliente)
    {
        if (! $this->clienteVigente($idCliente)) {
            return redirect()->to(base_url('clientes'))->with('error', 'El cliente no existe o está dado de baja.');
        }

        session()->remove($this->claveCarritoVendedor($idCliente));
        session()->remove('carrito_vendedor_origen_' . $idCliente);

        return redirect()->to(base_url('clientes/pedido/' . $idCliente));
    }

    public function catalogoPedido(int $idCliente)
    {
        $cliente = $this->clienteVigente($idCliente);
        if (! $cliente) {
            return redirect()->to(base_url('clientes'))->with('error', 'El cliente no existe o está dado de baja.');
        }

        $busqueda = trim((string) $this->request->getGet('buscar'));
        $idPedidoOrigen = (int) $this->request->getGet('pedido');
        $claveCarrito = $this->claveCarritoVendedor($idCliente);
        $claveOrigenPedido = 'carrito_vendedor_origen_' . $idCliente;
        $carritoActual = session()->get($claveCarrito) ?? [];
        if (! is_array($carritoActual)) {
            $carritoActual = [];
        }

        if ($idPedidoOrigen > 0) {
            $modeloPedidos = new PedidoClienteModel();
            $cantidadesPedido = $modeloPedidos->getCantidadesPedidoCliente($idCliente, $idPedidoOrigen);
            if ($cantidadesPedido === null) {
                return redirect()->to(base_url('clientes/historial/' . $idCliente))
                    ->with('error', 'No se encontró ese pedido para el cliente.');
            }
            if ($cantidadesPedido === []) {
                return redirect()->to(base_url('clientes/historial/' . $idCliente))
                    ->with('error', 'Ese pedido no contiene productos para agregar.');
            }

            if ((int) session()->get($claveOrigenPedido) !== $idPedidoOrigen) {
                $carritoActual = $cantidadesPedido;
                session()->set($claveCarrito, $carritoActual);
                session()->set($claveOrigenPedido, $idPedidoOrigen);
            }
        }

        $modelo = new ProductoModel();
        $productos = $modelo->getProductos($busqueda, 'activo')
            ->where('producto.precio >', 0)
            ->where('existencias.exis_venta >', 0)
            ->orderBy('producto.nombre', 'ASC')
            ->paginate(12, 'catalogo');
        return view('pedido_vendedor_catalogo', [
            'cliente'    => $cliente,
            'productos'  => $productos,
            'pager'      => $modelo->pager,
            'carrito'    => (new PedidoClienteModel())->getItemsCarrito($carritoActual),
            'busqueda'   => $busqueda,
            'idCliente'  => $idCliente,
            'idPedidoOrigen' => $idPedidoOrigen,
        ]);
    }

    public function agregarProductoPedido()
    {
        $idCliente  = (int) $this->request->getPost('id_cliente');
        $idProducto = (int) $this->request->getPost('id_producto');
        $cantidad   = round((float) $this->request->getPost('cantidad'), 2);
        $url        = base_url('clientes/pedido/' . $idCliente);

        if (! $this->clienteVigente($idCliente) || $idProducto < 1 || $cantidad <= 0 || $cantidad > 10000) {
            return redirect()->to($url)->with('error', 'Selecciona un cliente y una cantidad válida.');
        }

        $clave   = $this->claveCarritoVendedor($idCliente);
        $carrito = session()->get($clave) ?? [];
        if (! is_array($carrito)) {
            $carrito = [];
        }
        $carrito[$idProducto] = round((float) ($carrito[$idProducto] ?? 0) + $cantidad, 2);

        $items = (new PedidoClienteModel())->getItemsCarrito([$idProducto => $carrito[$idProducto]]);
        $item  = $items[0] ?? null;
        if (! $item || ! $item['activo'] || $item['precio'] <= 0) {
            return redirect()->to($url)->with('error', 'El producto no está disponible para agregar.');
        }
        if ($item['unidad'] !== 'kilos') {
            $carrito[$idProducto] = max(1, (int) round($carrito[$idProducto]));
        }
        if ($carrito[$idProducto] > $item['stock']) {
            return redirect()->to($url)->with('error', 'Solo hay ' . $item['stock'] . ' disponibles de ' . $item['nombre'] . '.');
        }

        session()->set($clave, $carrito);

        return redirect()->to($url)->with('success', $item['nombre'] . ' agregado al pedido.');
    }

    public function quitarProductoPedido()
    {
        $idCliente  = (int) $this->request->getPost('id_cliente');
        $idProducto = (int) $this->request->getPost('id_producto');
        $url        = base_url('clientes/pedido/' . $idCliente);

        if (! $this->clienteVigente($idCliente)) {
            return redirect()->to(base_url('clientes'))->with('error', 'El cliente no existe o está dado de baja.');
        }

        $clave   = $this->claveCarritoVendedor($idCliente);
        $carrito = session()->get($clave) ?? [];
        if (is_array($carrito)) {
            unset($carrito[$idProducto]);
        }
        session()->set($clave, $carrito);

        return redirect()->to($url)->with('success', 'Producto quitado del nuevo pedido.');
    }

    public function actualizarCantidadPedido()
    {
        $idCliente  = (int) $this->request->getPost('id_cliente');
        $idProducto = (int) $this->request->getPost('id_producto');
        $cantidad   = round((float) $this->request->getPost('cantidad'), 2);
        $url        = base_url('clientes/pedido/' . $idCliente);

        if (! $this->clienteVigente($idCliente) || $idProducto < 1
            || ! is_finite($cantidad) || $cantidad <= 0 || $cantidad > 10000) {
            return redirect()->to($url)->with('error', 'Ingresa una cantidad válida para actualizar el producto.');
        }

        $clave   = $this->claveCarritoVendedor($idCliente);
        $carrito = session()->get($clave) ?? [];
        if (! is_array($carrito) || ! isset($carrito[$idProducto])) {
            return redirect()->to($url)->with('error', 'El producto no está en el pedido actual.');
        }

        $items = (new PedidoClienteModel())->getItemsCarrito([$idProducto => $cantidad]);
        $item  = $items[0] ?? null;
        if (! $item || ! $item['activo'] || $item['precio'] <= 0) {
            return redirect()->to($url)->with('error', 'El producto no está disponible para actualizar.');
        }
        if ($item['unidad'] !== 'kilos' && floor($cantidad) !== $cantidad) {
            return redirect()->to($url)->with('error', 'La cantidad de ' . $item['unidad'] . ' debe ser un número entero.');
        }
        if ($cantidad > $item['stock']) {
            return redirect()->to($url)->with('error', 'Solo hay ' . $item['stock'] . ' disponibles de ' . $item['nombre'] . '.');
        }

        $carrito[$idProducto] = $cantidad;
        session()->set($clave, $carrito);

        return redirect()->to($url)->with('success', 'Cantidad actualizada para ' . $item['nombre'] . '.');
    }

    public function enviarPedidoVendedor()
    {
        $idCliente = (int) $this->request->getPost('id_cliente');
        $url       = base_url('clientes/pedido/' . $idCliente);
        $cliente   = $this->clienteVigente($idCliente);

        if (! $cliente) {
            return redirect()->to(base_url('clientes'))->with('error', 'El cliente no existe o está dado de baja.');
        }

        $clave   = $this->claveCarritoVendedor($idCliente);
        $carrito = session()->get($clave) ?? [];
        if (! is_array($carrito) || $carrito === []) {
            return redirect()->to($url)->with('error', 'Agrega al menos un producto antes de enviar el pedido.');
        }

        $fecha   = trim((string) $this->request->getPost('fecha_entrega'));
        $horario = trim((string) $this->request->getPost('horario'));
        if ($fecha !== '' && (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)
            || strtotime($fecha) === false || $fecha < date('Y-m-d'))) {
            return redirect()->to($url)->with('error', 'La fecha de entrega no es válida.');
        }
        if ($horario !== '' && ! preg_match('/^\d{2}:\d{2}$/', $horario)) {
            return redirect()->to($url)->with('error', 'El horario de entrega no es válido.');
        }

        $modalidad = (string) $this->request->getPost('modalidad_pago');
        if (! in_array($modalidad, ['contado', 'credito'], true)) {
            return redirect()->to($url)->with('error', 'Selecciona un método de pago.');
        }

        $pagoInicial = 0.0;
        if ($modalidad === 'credito') {
            $texto = trim((string) $this->request->getPost('pago_inicial'));
            if ($texto === '' || ! is_numeric($texto) || (float) $texto < 0) {
                return redirect()->to($url)->with('error', 'El pago inicial debe ser un monto válido (puede ser $0).');
            }
            $pagoInicial = (float) $texto;
        }

        $resultado = (new PedidoClienteModel())->crearPedidoDesdeCarrito(
            $idCliente,
            $carrito,
            $fecha !== '' ? $fecha : null,
            $horario !== '' ? $horario : null,
            $modalidad,
            $pagoInicial
        );

        if (! $resultado['success']) {
            return redirect()->to($url)->with('error', $resultado['message']);
        }

        session()->remove($clave);
        session()->remove('carrito_vendedor_origen_' . $idCliente);

        return redirect()->to(base_url('clientes/historial/' . $idCliente))
            ->with('success', 'Pedido #' . $resultado['numero_pedido_cliente'] . ' enviado al administrador para su revisión.');
    }

    private function clienteVigente(int $idCliente): ?array
    {
        if ($idCliente < 1) {
            return null;
        }

        return (new ClienteModel())
            ->select('id_cliente, nombre, ap_paterno, ap_materno, telefono, email')
            ->where('baja', 0)
            ->find($idCliente);
    }

    private function claveCarritoVendedor(int $idCliente): string
    {
        return 'carrito_vendedor_' . $idCliente;
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

        $cliente = $id > 0 ? $model->find($id) : null;
        if ($cliente === null) {
            return redirect()->back()->with('error', 'El cliente seleccionado no existe.');
        }

        $changes = [
            'nombre'     => $this->request->getPost('nombre'),
            'ap_paterno' => $this->request->getPost('ap_paterno'),
            'ap_materno' => $this->request->getPost('ap_materno'),
            'telefono'   => $this->request->getPost('telefono'),
            'email'      => $email,
        ];
        if (strtolower(trim((string) $cliente['email'])) !== $email) {
            $changes += [
                'email_verified_at' => null,
                'email_verification_code_hash' => null,
                'email_verification_expires_at' => null,
                'email_verification_sent_at' => null,
                'email_verification_attempts' => 0,
                'email_verification_locked_until' => null,
            ];
        }

        if (! $model->update($id, $changes)) {
            return redirect()->back()->withInput()->with('error', 'No se pudo actualizar el cliente.');
        }

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
        if ($id <= 0) {
            return redirect()->to(base_url('clientes'))->with('error', 'No se indicó un cliente válido.');
        }

        $model   = new ClienteModel();
        $cliente = $model->find($id);

        if (! $cliente || (int) $cliente['baja'] === 1) {
            return redirect()->to(base_url('clientes'))->with('error', 'El cliente no existe o ya estaba dado de baja.');
        }
        if ($model->tienePedidosActivos($id)) {
            return redirect()->to(base_url('clientes'))->with('error', 'No se puede dar de baja a este cliente porque tiene pedidos en curso o saldo pendiente. Entrega, cancela o cobra esos pedidos primero.');
        }
        if (! $model->darDeBaja($id)) {
            return redirect()->to(base_url('clientes'))->with('error', 'No se pudo dar de baja al cliente.');
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