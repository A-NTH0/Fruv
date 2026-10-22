<?php

namespace App\Controllers;

use App\Models\PedidoClienteModel;

class CarritoController extends BaseController
{
    private const CLAVE = 'carrito';

    private function leer(): array
    {
        $c = session()->get(self::CLAVE);

        return is_array($c) ? $c : [];
    }

    private function guardar(array $c): void
    {
        session()->set(self::CLAVE, $c);
    }

    private function resumen(): array
    {
        $carrito = $this->leer();
        $items   = (new PedidoClienteModel())->getItemsCarrito($carrito);

        // Descarta productos que ya no existen o con cantidad 0.
        $vigentes = array_column($items, 'cantidad', 'id');
        if (count($vigentes) !== count($carrito)) {
            $this->guardar($vigentes);
        }

        return [
            'items'   => $items,
            'total'   => round(array_sum(array_column($items, 'subtotal')), 2),
            'cuenta'  => count($items),
            'todo_ok' => ! in_array(false, array_column($items, 'ok'), true),
        ];
    }

    private function responder(string $mensaje)
    {
        return $this->response->setJSON(array_merge(['success' => true, 'message' => $mensaje], $this->resumen()));
    }

    private function error(string $mensaje)
    {
        return $this->response->setJSON(['success' => false, 'message' => $mensaje]);
    }

    private function normalizarCantidad($valor): float
    {
        $n = round((float) $valor, 2);

        return ($n > 0 && $n <= 10000) ? $n : 0.0;
    }

    private function aplicar(int $id, float $cantidad, array $carrito, string $mensaje)
    {
        $items = (new PedidoClienteModel())->getItemsCarrito([$id => $cantidad]);
        if ($items === []) {
            return $this->error('El producto no existe.');
        }
        $item = $items[0];

        if ($item['unidad'] !== 'kilos') {
            $cantidad = max(1.0, round($cantidad));
        }
        if (! $item['activo'] || $item['precio'] <= 0) {
            return $this->error('Este producto no está disponible por el momento.');
        }
        if ($cantidad > $item['stock']) {
            return $this->error('Solo hay ' . $item['stock'] . ' disponibles de ' . $item['nombre'] . '.');
        }

        $carrito[$id] = $cantidad;
        $this->guardar($carrito);

        return $this->responder($mensaje);
    }

    public function index()
    {
        $r = $this->resumen();

        return view('carrito', [
            'items' => $r['items'],
            'total' => $r['total'],
            'hoy'   => date('Y-m-d'),
        ]);
    }

    public function resumenJson()
    {
        return $this->responder('');
    }

    public function agregar()
    {
        $id       = (int) $this->request->getPost('id_producto');
        $cantidad = $this->normalizarCantidad($this->request->getPost('cantidad'));

        if ($id <= 0 || $cantidad <= 0) {
            return $this->error('Indica una cantidad válida.');
        }

        $carrito = $this->leer();

        return $this->aplicar($id, ($carrito[$id] ?? 0) + $cantidad, $carrito, 'Producto agregado al carrito.');
    }

    public function actualizar()
    {
        $id       = (int) $this->request->getPost('id_producto');
        $cantidad = $this->normalizarCantidad($this->request->getPost('cantidad'));
        $carrito  = $this->leer();

        if ($id <= 0 || ! isset($carrito[$id])) {
            return $this->error('El producto no está en tu carrito.');
        }
        if ($cantidad <= 0) {
            unset($carrito[$id]);
            $this->guardar($carrito);

            return $this->responder('Producto eliminado.');
        }

        return $this->aplicar($id, $cantidad, $carrito, 'Cantidad actualizada.');
    }

    public function quitar()
    {
        $id      = (int) $this->request->getPost('id_producto');
        $carrito = $this->leer();
        unset($carrito[$id]);
        $this->guardar($carrito);

        return $this->responder('Producto eliminado.');
    }

    public function vaciar()
    {
        $this->guardar([]);

        return $this->responder('Carrito vaciado.');
    }

    public function confirmar()
    {
        $carrito = $this->leer();
        if ($carrito === []) {
            return $this->error('Tu carrito está vacío.');
        }

        $fecha   = trim((string) $this->request->getPost('fecha_entrega'));
        $horario = trim((string) $this->request->getPost('horario'));

        if ($fecha !== '') {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || strtotime($fecha) === false || $fecha < date('Y-m-d')) {
                return $this->error('La fecha de entrega no es válida.');
            }
        }
        if ($horario !== '' && ! preg_match('/^\d{2}:\d{2}$/', $horario)) {
            return $this->error('El horario de entrega no es válido.');
        }

        // Método de pago: de contado = 100 % del total; a crédito = pago inicial (puede ser $0).
        $modalidad = (string) $this->request->getPost('modalidad_pago');
        if (! in_array($modalidad, ['contado', 'credito'], true)) {
            return $this->error('Selecciona un método de pago.');
        }

        $pagoInicial = 0.0;
        if ($modalidad === 'credito') {
            $texto = trim((string) $this->request->getPost('pago_inicial'));
            if ($texto === '') {
                $texto = '0';
            }
            if (! is_numeric($texto) || (float) $texto < 0) {
                return $this->error('El pago inicial debe ser un monto válido (puede ser $0).');
            }
            $pagoInicial = (float) $texto;
        }

        $res = (new PedidoClienteModel())->crearPedidoDesdeCarrito(
            (int) session()->get('cliente_id'),
            $carrito,
            $fecha !== '' ? $fecha : null,
            $horario !== '' ? $horario : null,
            $modalidad,
            $pagoInicial
        );

        if (! $res['success']) {
            return $this->error($res['message']);
        }

        $this->guardar([]);
        session()->setFlashdata('pedido_ok', $res['numero_pedido_cliente']);

        return $this->response->setJSON([
            'success'   => true,
            'message'   => 'Tu pedido fue enviado y está pendiente de aceptación.',
            'id_pedido' => $res['id_pedido'],
            'redirect'  => base_url('mis-pedidos'),
        ]);
    }
}