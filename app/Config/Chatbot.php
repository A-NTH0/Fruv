<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Chatbot extends BaseConfig
{
    /** Intentos fallidos permitidos en el formulario de seguimiento dentro de la ventana. */
    public int $maxIntentosFallidos = 5;

    /** Ventana (segundos) para contar intentos fallidos. 600 = 10 minutos. */
    public int $ventanaSegundos = 600;

    /**
     *
     *
     * Está vacío a propósito: no se publica nada que el negocio no haya definido.
     * Ejemplo (descomenta y edita):
     *
     *   [
     *       'titulo'              => 'Semana de la fruta',
     *       'descripcion'         => '10 % menos en toda la fruta de temporada.',
     *       'desde'               => '2026-11-02',
     *       'hasta'               => '2026-11-08',
     *       'solo_preferenciales' => false,
     *   ],
     *
     * @var list<array{titulo:string, descripcion:string, desde:string, hasta:string, solo_preferenciales?:bool}>
     */
    public array $promociones = [];

    /**
     
     *
     * @var list<array{id:string, pregunta:string, respuesta:string, palabras:list<string>}>
     */
    public array $faq = [
        [
            'id'        => 'hacer-pedido',
            'pregunta'  => '¿Cómo hago un pedido?',
            'respuesta' => "Entra al Catálogo, agrega los productos a tu carrito y, en Mi carrito, elige si pagas de contado o a crédito.\nAl confirmar, tu pedido llega a la tienda como Pendiente. Después puedes seguirlo en Mis pedidos.",
            'palabras'  => ['como hago un pedido', 'hacer un pedido', 'realizar un pedido', 'pedir', 'comprar', 'carrito', 'ordenar'],
        ],
        [
            'id'        => 'estados',
            'pregunta'  => '¿Qué significa el estado de mi pedido?',
            'respuesta' => "Tu pedido pasa por cinco pasos: Pendiente (la tienda lo recibió), Aceptado, Repartidor asignado, En camino y Entregado.\nSi lo ves En espera, la tienda está verificando la disponibilidad de tus productos.",
            'palabras'  => ['significa', 'estados', 'en espera', 'pendiente', 'aceptado', 'en camino', 'paso'],
        ],
        [
            'id'        => 'cancelar',
            'pregunta'  => '¿Puedo cancelar mi pedido?',
            'respuesta' => "Sí, mientras esté Pendiente o Aceptado. Desde que sale En camino ya no se puede cancelar.\nPuedes hacerlo desde Mis pedidos.",
            'palabras'  => ['cancel', 'anular', 'ya no lo quiero'],
        ],
        [
            'id'        => 'credito',
            'pregunta'  => '¿Cómo funciona el pago a crédito?',
            'respuesta' => "Al elegir A crédito puedes dejar un pago inicial desde \$0. El saldo lo abonas cuando quieras desde Mis pedidos y se actualiza al momento.\nCuando llega a \$0 el pedido queda como Totalmente Pagado.",
            'palabras'  => ['credito', 'abono', 'abonar', 'saldo', 'deuda', 'enganche', 'pago inicial', 'pagar'],
        ],
        [
            'id'        => 'entrega',
            'pregunta'  => '¿Cuándo y cómo recibo mi pedido?',
            'respuesta' => "Al confirmar tu carrito puedes indicar una fecha y un horario de entrega (son opcionales).\nCuando la tienda asigna un repartidor lo verás en el seguimiento, y el estado cambia a En camino cuando sale a tu domicilio.",
            'palabras'  => ['cuando llega', 'entrega', 'envio', 'repartidor', 'domicilio', 'horario', 'llegar', 'recibir'],
        ],
        [
            'id'        => 'preferencial',
            'pregunta'  => '¿Qué es un cliente preferencial?',
            'respuesta' => "Es un cliente con {umbral} pedidos de contado pagados al 100 % (los de crédito no cuentan). La tienda también puede marcarte como preferencial.\nLos clientes preferenciales pueden recibir un descuento que la tienda define en cada pedido.",
            'palabras'  => ['preferencial', 'cliente frecuente', 'beneficio'],
        ],
        [
            'id'        => 'no-encuentro-pedido',
            'pregunta'  => 'No encuentro mi pedido',
            'respuesta' => "El número de pedido es el que ves en Mis pedidos (Pedido #1, #2…). Para consultarlo aquí, escríbelo junto con el correo de tu cuenta.\nSi aún no aparece, confirma que hayas enviado el carrito.",
            'palabras'  => ['no encuentro', 'no aparece', 'numero de pedido', 'perdi mi pedido'],
        ],
    ];
}