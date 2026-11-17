<?php
namespace App\Models;
use CodeIgniter\Model;

class ClienteModel extends Model
{
    protected $table            = 'cliente';
    protected $primaryKey       = 'id_cliente';
    protected $allowedFields    = [
        'nombre',
        'ap_paterno',
        'ap_materno',
        'telefono',
        'email',
        'hash',
        'baja',
        'fecha_baja',
        'preferencial_manual',
        'email_verified_at',
        'email_verification_code_hash',
        'email_verification_expires_at',
        'email_verification_sent_at',
        'email_verification_attempts',
        'email_verification_locked_until',
    ];

   
    public const UMBRAL_PREFERENCIAL    = 5;   // pedidos de contado concluidos para ser preferencial
    public const DESCUENTO_MAXIMO       = 30;  // % máximo de descuento manual a un cliente preferencial

    /**
     * Subconsulta: pedidos de contado concluidos de un cliente (último estado entregado
     * o pagado, sin saldo). Los de crédito y los cancelados no cuentan.
     */
    public static function sqlCompras(): string
    {
        return "(SELECT COUNT(*) FROM pedido pe
                 JOIN (SELECT id_pedido2, MAX(id_status) AS max_id FROM status GROUP BY id_pedido2) l
                      ON l.id_pedido2 = pe.id_pedido
                 JOIN status st ON st.id_status = l.max_id
                 WHERE pe.id_cliente1 = cliente.id_cliente
                   AND pe.modalidad_pago = 'contado'
                   AND COALESCE(pe.deuda_restante, 0) <= 0
                   AND st.estado IN ('entregado','pagado')) AS compras";
    }

    /** @param int|string $manual valor de cliente.preferencial_manual */
    public static function esPreferencial(int $compras, $manual = 0): bool
    {
        return (int) $manual === 1 || $compras >= self::UMBRAL_PREFERENCIAL;
    }

    /** ¿El cliente es preferencial? (compras de contado concluidas o marca manual del admin). */
    public function esPreferencialPorId(int $idCliente): bool
    {
        $fila = $this->select('cliente.preferencial_manual, ' . self::sqlCompras(), false)
                     ->where('cliente.id_cliente', $idCliente)
                     ->first();

        return $fila !== null
            && self::esPreferencial((int) $fila['compras'], $fila['preferencial_manual'] ?? 0);
    }

    /** Precio unitario con el % de descuento aplicado (mismo redondeo que el navegador). */
    public static function aplicarDescuento(float $precio, float $porcentaje): float
    {
        return round($precio * (100 - $porcentaje) / 100, 2);
    }

    /**
     * Valida en el servidor el descuento manual que llega del formulario.
     * Vacío o 0 = sin descuento. Solo se permite a clientes preferenciales.
     *
     * @param mixed $crudo valor enviado por el navegador
     * @return array{0: float, 1: ?string} [porcentaje, mensaje de error]
     */
    public function validarDescuento($crudo, int $idCliente): array
    {
        $texto = trim((string) $crudo);
        if ($texto === '') {
            return [0.0, null];
        }
        if (! is_numeric($texto)) {
            return [0.0, 'El descuento debe ser un número.'];
        }

        $porcentaje = round((float) $texto, 2);
        if ($porcentaje < 0 || $porcentaje > self::DESCUENTO_MAXIMO) {
            return [0.0, 'El descuento debe estar entre 0% y ' . self::DESCUENTO_MAXIMO . '%.'];
        }
        if ($porcentaje === 0.0) {
            return [0.0, null];
        }
        if (! $this->esPreferencialPorId($idCliente)) {
            return [0.0, 'Solo los clientes preferenciales pueden recibir descuento.'];
        }

        return [$porcentaje, null];
    }

    /** Ajuste manual del administrador. */
    public function setPreferencialManual(int $idCliente, bool $valor): bool
    {
        return (bool) $this->update($idCliente, ['preferencial_manual' => $valor ? 1 : 0]);
    }

    /**
     * Catálogo mínimo para el formulario de pedidos (RNF-05): solo id, nombre,
     * apellido, marca de preferencial y compras; sin teléfono ni dirección. Excluye clientes dados de baja.
     */
    public function getClientesParaPedido(): array
    {
        return $this->select('cliente.id_cliente, cliente.nombre, cliente.ap_paterno, cliente.preferencial_manual, ' . self::sqlCompras(), false)
                    ->where('cliente.baja', 0)
                    ->orderBy('cliente.nombre', 'ASC')
                    ->findAll();
    }

    /**
     * Pedidos en curso del cliente: último estado pedido / confirmado / en_transito,
     * o a crédito con saldo pendiente. Mientras existan, no se puede dar de baja
     * (mismo criterio que el RF-08 para repartidores con pedidos activos).
     */
    public function tienePedidosActivos(int $idCliente): bool
    {
        $fila = \Config\Database::connect()->query(
            "SELECT COUNT(*) AS n
               FROM pedido pe
               JOIN (SELECT id_pedido2, MAX(id_status) AS max_id FROM status GROUP BY id_pedido2) l
                    ON l.id_pedido2 = pe.id_pedido
               JOIN status st ON st.id_status = l.max_id
              WHERE pe.id_cliente1 = ?
                AND (st.estado IN ('pedido', 'confirmado', 'en_transito')
                     OR (st.estado = 'credito' AND COALESCE(pe.deuda_restante, 0) > 0))",
            [$idCliente]
        )->getRowArray();

        return (int) ($fila['n'] ?? 0) > 0;
    }

    /**
     * Baja manual (RNF-04): anonimiza los datos personales y borra la dirección,
     * conservando el historial de pedidos para no romper reportes ni relaciones.
     * No procede si el cliente tiene pedidos en curso o saldo pendiente.
     */
    public function darDeBaja(int $idCliente): bool
    {
        if ($this->tienePedidosActivos($idCliente)) {
            return false;
        }

        $db = \Config\Database::connect();
        $db->transStart();
        $this->update($idCliente, [
            'nombre'     => 'Cliente dado de baja',
            'ap_paterno' => '',
            'ap_materno' => '',
            'telefono'   => '',
            // Marcador único y sin datos personales: evita choques si la columna email tiene índice UNIQUE.
            'email'      => 'baja-' . $idCliente . '@baja.invalid',
            'hash'       => '',
            'baja'       => 1,
            'fecha_baja' => date('Y-m-d H:i:s'),
        ]);
        $db->table('direccion')->where('id_cliente', $idCliente)->delete();
        $db->transComplete();

        return $db->transStatus();
    }

    public function getClientesConDireccion(string $busqueda = ''): static
    {
        $this->select('cliente.id_cliente, cliente.nombre, cliente.ap_paterno, cliente.ap_materno, cliente.telefono, cliente.email, cliente.preferencial_manual,
                        direccion.calle, direccion.numero, direccion.colonia, direccion.municipio, direccion.estado,
                        ' . self::sqlCompras(), false)
             ->join('direccion', 'direccion.id_cliente = cliente.id_cliente', 'left')
             ->where('cliente.baja', 0);

        if ($busqueda !== '') {
            $this->groupStart()
                 ->like('cliente.nombre', $busqueda)
                 ->orLike('cliente.ap_paterno', $busqueda)
                 ->orLike('cliente.telefono', $busqueda)
                 ->orLike('cliente.email', $busqueda)
                 ->groupEnd();
        }

        $this->orderBy('cliente.id_cliente', 'DESC');

        return $this;
    }

    public function guardarDireccion(int $idCliente, array $direccion): void
    {
        $db = \Config\Database::connect();
        $db->table('direccion')->insert(
            array_merge(['id_cliente' => $idCliente], $direccion, $this->coordenadasDe($direccion))
        );
    }

    /**
     * Latitud/longitud de la dirección (para las rutas con Dijkstra).
     * Si el servicio de mapas falla no se interrumpe el registro: queda NULL
     * y se reintenta después (portal del repartidor o `php spark direcciones:geocodificar`).
     */
    private function coordenadasDe(array $direccion): array
    {
        return \App\Libraries\Geocoder::camposParaGuardar($direccion);
    }


    public function tienePedidos(int $idCliente): bool
    {
        $db = \Config\Database::connect();
        return $db->table('pedido')
                  ->where('id_cliente1', $idCliente)
                  ->countAllResults() > 0;
    }

    public function actualizarDireccion(int $idCliente, array $direccion): void
    {
        $db     = \Config\Database::connect();
        $actual = $db->table('direccion')
                     ->where('id_cliente', $idCliente)
                     ->get()
                     ->getRowArray();

        if ($actual !== null) {
            // Solo se vuelve a geocodificar si la dirección cambió o aún no tiene coordenadas.
            $cambio = false;
            foreach (['calle', 'numero', 'colonia', 'municipio', 'estado'] as $campo) {
                if (trim((string) ($direccion[$campo] ?? '')) !== trim((string) ($actual[$campo] ?? ''))) {
                    $cambio = true;
                    break;
                }
            }
            $extra = ($cambio || $actual['latitud'] === null) ? $this->coordenadasDe($direccion) : [];

            $db->table('direccion')
               ->where('id_cliente', $idCliente)
               ->update($direccion + $extra);
        } else {
            $db->table('direccion')->insert(
                array_merge(['id_cliente' => $idCliente], $direccion, $this->coordenadasDe($direccion))
            );
        }
    }
}