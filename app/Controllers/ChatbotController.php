<?php

namespace App\Controllers;

use App\Models\ClienteModel;
use App\Models\PedidoClienteModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Asistente virtual (chatbot) — EXCLUSIVO del rol cliente.
 *
 * Acotado en cuatro capas:
 *  1. AuthFilter: 'chatbot' es ruta de cliente; admin, vendedor y repartidor son redirigidos.
 *  2. Este controlador vuelve a validar el rol (defensa en profundidad) y responde 403 JSON.
 *  3. El widget solo se pinta para el rol cliente (plantilla/menu.php).
 *  4. Todo dato sale de la sesión (cliente_id): nunca se acepta un id de cliente desde el navegador.
 */
class ChatbotController extends BaseController
{
    private const MAX_TEXTO = 200;

    /** @return array{id:int, nombre:string, email:string}|null */
    private function cliente(): ?array
    {
        if (session()->get('auth_role') !== 'cliente') {
            return null;
        }

        $id = (int) session()->get('cliente_id');
        if ($id < 1) {
            return null;
        }

        $fila = (new ClienteModel())->select('id_cliente, nombre, email')->where('baja', 0)->find($id);
        if (! $fila) {
            return null;
        }

        return ['id' => (int) $fila['id_cliente'], 'nombre' => (string) $fila['nombre'], 'email' => (string) $fila['email']];
    }

    private function prohibido(): ResponseInterface
    {
        return $this->response->setStatusCode(403)->setJSON([
            'ok'      => false,
            'mensaje' => 'El asistente solo está disponible para clientes.',
        ]);
    }

    // ─────────────────────────────────────────────
    //  GET chatbot/inicio — saludo y productos destacados
    // ─────────────────────────────────────────────
    public function inicio(): ResponseInterface
    {
        $cli = $this->cliente();
        if ($cli === null) {
            return $this->prohibido();
        }
        session_write_close(); // libera el bloqueo de sesión

        $filas = db_connect()->table('producto p')
            ->select('p.nombre, p.precio, p.unidad_venta, p.imagen')
            ->join('existencias e', 'e.id_producto1 = p.id_producto')
            ->where('p.estado', 'activo')
            ->where('p.precio >', 0)
            ->where('e.exis_venta >', 0)
            ->orderBy('RAND()', '', false)
            ->limit(2)
            ->get()->getResultArray();

        $destacados = array_map(static fn (array $p): array => [
            'nombre'  => $p['nombre'],
            'precio'  => round((float) $p['precio'], 2),
            'unidad'  => $p['unidad_venta'],
            'imagen'  => base_url('uploads/' . rawurlencode($p['imagen'] ?: 'default.jpg')),
        ], $filas);

        return $this->response->setJSON([
            'ok'         => true,
            'nombre'     => explode(' ', trim($cli['nombre']))[0],
            'destacados' => $destacados,
        ]);
    }

    public function mensaje(): ResponseInterface
    {
        $cli = $this->cliente();
        if ($cli === null) {
            return $this->prohibido();
        }
        session_write_close();

        $intent = (string) $this->request->getPost('intent');
        $texto  = mb_substr(trim((string) $this->request->getPost('texto')), 0, self::MAX_TEXTO);

        if (! in_array($intent, ['seguimiento', 'promociones', 'historial', 'faq_lista', 'faq', 'pedido'], true)) {
            $intent = $this->detectarIntencion($texto);
        }

        switch ($intent) {
            case 'seguimiento':
                return $this->response->setJSON(['ok' => true, 'tipo' => 'seguimiento_form']);

            case 'promociones':
                return $this->response->setJSON(['ok' => true, 'tipo' => 'promociones'] + $this->promociones($cli['id']));

            case 'historial':
                return $this->response->setJSON(['ok' => true, 'tipo' => 'historial', 'pedidos' => $this->historial($cli['id'])]);

            case 'pedido':
                $pedido = $this->pedidoPorNumero($cli['id'], $this->numeroPedido($this->request->getPost('numero')));
                if ($pedido === null) {
                    return $this->response->setJSON(['ok' => true, 'tipo' => 'texto', 'mensaje' => 'No encontré ese pedido en tu cuenta.']);
                }

                return $this->response->setJSON(['ok' => true, 'tipo' => 'pedido', 'pedido' => $pedido]);

            case 'faq':
                $faq = $this->buscarFaqPorId((string) $this->request->getPost('id'));
                if ($faq !== null) {
                    return $this->response->setJSON(['ok' => true, 'tipo' => 'faq'] + $this->presentarFaq($faq));
                }
                break;
        }

        // Texto libre: ¿coincide con alguna pregunta frecuente?
        if ($texto !== '') {
            $faq = $this->mejorFaq($texto);
            if ($faq !== null) {
                return $this->response->setJSON(['ok' => true, 'tipo' => 'faq'] + $this->presentarFaq($faq));
            }
        }

        return $this->response->setJSON([
            'ok'        => true,
            'tipo'      => 'faq_lista',
            'mensaje'   => $texto === ''
                ? 'Estas son las dudas más frecuentes. Elige una:'
                : 'No estoy seguro de haber entendido. Estas son las dudas más frecuentes:',
            'preguntas' => array_map(
                static fn (array $f): array => ['id' => $f['id'], 'pregunta' => $f['pregunta']],
                config('Chatbot')->faq
            ),
        ]);
    }

    public function seguimiento(): ResponseInterface
    {
        $cli = $this->cliente();
        if ($cli === null) {
            return $this->prohibido();
        }

        $cfg   = config('Chatbot');
        $ahora = time();

        // Freno a la fuerza bruta: se cuentan los intentos fallidos recientes de esta sesión.
        $fallos = array_values(array_filter(
            (array) session()->get('chatbot_fallos'),
            static fn ($t): bool => is_int($t) && $t > $ahora - $cfg->ventanaSegundos
        ));

        if (count($fallos) >= $cfg->maxIntentosFallidos) {
            return $this->response->setStatusCode(429)->setJSON([
                'ok'        => false,
                'bloqueado' => true,
                'mensaje'   => 'Hiciste varios intentos con datos que no coinciden. Espera unos minutos para intentarlo de nuevo.',
            ]);
        }

        $numero = $this->numeroPedido($this->request->getPost('numero'));
        $correo = mb_strtolower(trim((string) $this->request->getPost('email')));

        $pedido = null;
        // El correo debe ser el de la cuenta con la sesión abierta: nadie consulta pedidos ajenos.
        if ($numero > 0 && filter_var($correo, FILTER_VALIDATE_EMAIL)
            && hash_equals(mb_strtolower($cli['email']), $correo)) {
            $pedido = $this->pedidoPorNumero($cli['id'], $numero);
        }

        if ($pedido === null) {
            $fallos[] = $ahora;
            session()->set('chatbot_fallos', $fallos);

            // Mensaje único: no revela si falló el número o el correo.
            return $this->response->setJSON([
                'ok'      => false,
                'mensaje' => 'No encontramos un pedido con esos datos. Verifica el número (el de Mis pedidos) y el correo de tu cuenta.',
            ]);
        }

        session()->remove('chatbot_fallos');

        return $this->response->setJSON(['ok' => true, 'pedido' => $pedido]);
    }

    private function numeroPedido($crudo): int
    {
        $t = ltrim(trim((string) $crudo), '#');

        return ctype_digit($t) && strlen($t) <= 9 ? (int) $t : 0;
    }

    private function normalizar(string $t): string
    {
        $t = mb_strtolower(trim($t), 'UTF-8');
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return (string) preg_replace('/\s+/', ' ', $t);
    }

    private function detectarIntencion(string $texto): string
    {
        $t = $this->normalizar($texto);
        if ($t === '') {
            return 'faq_lista';
        }

        $mapa = [
            'seguimiento' => ['seguimiento', 'rastre', 'estado de mi pedido', 'estado del pedido', 'donde esta mi', 'donde va', 'ya llego', 'estatus', 'status'],
            'historial'   => ['historial', 'mis pedidos', 'ultimos pedidos', 'pedidos anteriores', 'mis compras', 'mis ultimos'],
            'promociones' => ['promo', 'oferta', 'descuento', 'rebaja', 'cupon'],
            'faq_lista'   => ['preguntas frecuentes', 'dudas', 'ayuda', 'faq'],
        ];

        foreach ($mapa as $intent => $palabras) {
            foreach ($palabras as $p) {
                if (str_contains($t, $p)) {
                    return $intent;
                }
            }
        }

        return 'libre';
    }

    private function mejorFaq(string $texto): ?array
    {
        $t     = $this->normalizar($texto);
        $mejor = null;
        $max   = 0;

        foreach (config('Chatbot')->faq as $faq) {
            $puntos = 0;
            foreach ($faq['palabras'] as $p) {
                if (str_contains($t, $this->normalizar($p))) {
                    $puntos++;
                }
            }
            if ($puntos > $max) {
                $max   = $puntos;
                $mejor = $faq;
            }
        }

        return $mejor;
    }

    private function buscarFaqPorId(string $id): ?array
    {
        foreach (config('Chatbot')->faq as $faq) {
            if ($faq['id'] === $id) {
                return $faq;
            }
        }

        return null;
    }

    private function presentarFaq(array $faq): array
    {
        $relacionadas = [];
        foreach (config('Chatbot')->faq as $otra) {
            if ($otra['id'] !== $faq['id'] && count($relacionadas) < 3) {
                $relacionadas[] = ['id' => $otra['id'], 'pregunta' => $otra['pregunta']];
            }
        }

        return [
            'pregunta'     => $faq['pregunta'],
            'respuesta'    => str_replace('{umbral}', (string) ClienteModel::UMBRAL_PREFERENCIAL, $faq['respuesta']),
            'relacionadas' => $relacionadas,
        ];
    }

    /** Promociones vigentes hoy + situación del cliente en el programa de preferenciales. */
    private function promociones(int $idCliente): array
    {
        $fila = (new ClienteModel())
            ->select('cliente.preferencial_manual, ' . ClienteModel::sqlCompras(), false)
            ->where('cliente.id_cliente', $idCliente)
            ->first();

        $compras = (int) ($fila['compras'] ?? 0);
        $es      = ClienteModel::esPreferencial($compras, $fila['preferencial_manual'] ?? 0);
        $umbral  = ClienteModel::UMBRAL_PREFERENCIAL;
        $hoy     = date('Y-m-d');

        $vigentes = [];
        foreach (config('Chatbot')->promociones as $p) {
            if ($hoy < $p['desde'] || $hoy > $p['hasta']) {
                continue;
            }
            if (! empty($p['solo_preferenciales']) && ! $es) {
                continue;
            }
            $vigentes[] = [
                'titulo'      => $p['titulo'],
                'descripcion' => $p['descripcion'],
                'vigencia'    => 'Vigente hasta el ' . date('d/m/Y', strtotime($p['hasta'])),
            ];
        }

        $texto = $es
            ? 'Eres cliente preferencial. Puedes recibir un descuento que la tienda define en cada pedido.'
            : "Con {$umbral} pedidos de contado pagados al 100 % te vuelves cliente preferencial y puedes recibir descuentos. Llevas {$compras}; te faltan " . max(0, $umbral - $compras) . '.';

        return [
            'promociones'  => $vigentes,
            'preferencial' => ['es' => $es, 'texto' => $texto],
        ];
    }

    private function historial(int $idCliente): array
    {
        $datos = (new PedidoClienteModel())->getPedidosCliente($idCliente, 1);

        return array_map(static fn (array $p): array => [
            'numero' => (int) $p['numero_pedido_cliente'],
            'fecha'  => date('d/m/Y', strtotime($p['fecha'])),
            'total'  => round((float) $p['total'], 2),
            'estado' => ['texto' => $p['estado']['texto'], 'color' => $p['estado']['color']],
        ], $datos['pedidos']);
    }
    
    private function pedidoPorNumero(int $idCliente, int $numero): ?array
    {
        if ($numero < 1) {
            return null;
        }

        $db   = db_connect();
        $fila = $db->table('pedido')
            ->select('id_pedido')
            ->where('id_cliente1', $idCliente)
            ->orderBy('id_pedido', 'ASC')
            ->limit(1, $numero - 1)
            ->get()->getRowArray();

        if (! $fila) {
            return null;
        }

        $id = (int) $fila['id_pedido'];

        $p = $db->table('pedido')
            ->select('id_pedido, fecha, total, fecha_entrega_programada, horario_entrega')
            ->where('id_pedido', $id)
            ->where('id_cliente1', $idCliente)
            ->get()->getRowArray();

        $estado = (new PedidoClienteModel())->getEstadosPorIds($idCliente, [$id])[$id] ?? null;
        if (! $p || $estado === null) {
            return null;
        }

        $lineas = $db->table('producto_pedido pp')
            ->select('pp.cantidad, pp.unidad_venta, pp.total, pr.nombre')
            ->join('producto pr', 'pr.id_producto = pp.id_producto3')
            ->where('pp.id_pedido1', $id)
            ->get()->getResultArray();

        $fechaOk   = ! empty($p['fecha_entrega_programada']) && strpos((string) $p['fecha_entrega_programada'], '0000') !== 0;
        $horarioOk = ! empty($p['horario_entrega']) && $p['horario_entrega'] !== '00:00:00';

        return [
            'numero'     => $numero,
            'fecha'      => date('d/m/Y', strtotime($p['fecha'])),
            'total'      => round((float) $p['total'], 2),
            'modalidad'  => $estado['pago']['modalidad_texto'],
            'estado_pago' => $estado['pago']['estado_pago'],
            'saldo'      => $estado['pago']['saldo'],
            'estado'     => [
                'texto'   => $estado['texto'],
                'color'   => $estado['color'],
                'paso'    => $estado['paso'],
                'detalle' => $estado['detalle'],
            ],
            'pasos'      => array_values(PedidoClienteModel::PASOS),
            'repartidor' => $estado['repartidor'],
            'entrega'    => $fechaOk
                ? date('d/m/Y', strtotime($p['fecha_entrega_programada'])) . ($horarioOk ? ' · ' . substr($p['horario_entrega'], 0, 5) : '')
                : null,
            'productos'  => array_map(static fn (array $l): array => [
                'nombre'   => $l['nombre'],
                'cantidad' => (float) $l['cantidad'],
                'unidad'   => $l['unidad_venta'],
                'total'    => round((float) $l['total'], 2),
            ], $lineas),
            'url'        => base_url('mis-pedidos'),
        ];
    }
}