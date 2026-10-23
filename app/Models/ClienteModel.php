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
    public const DESCUENTO_PREFERENCIAL = 10;  // % de descuento sugerido en el precio

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
    protected $useAutoIncrement = true;

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
            array_merge(['id_cliente' => $idCliente], $direccion)
        );
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
        $existe = $db->table('direccion')
                     ->where('id_cliente', $idCliente)
                     ->countAllResults();

        if ($existe > 0) {
            $db->table('direccion')
               ->where('id_cliente', $idCliente)
               ->update($direccion);
        } else {
            $db->table('direccion')->insert(
                array_merge(['id_cliente' => $idCliente], $direccion)
            );
        }
    }
}