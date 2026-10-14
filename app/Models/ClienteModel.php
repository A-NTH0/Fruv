<?php
namespace App\Models;
use CodeIgniter\Model;

class ClienteModel extends Model
{
    protected $table            = 'cliente';
    protected $primaryKey       = 'id_cliente';
    protected $allowedFields    = ['nombre', 'ap_paterno', 'ap_materno', 'telefono'];
    protected $useAutoIncrement = true;

    public function getClientesConDireccion(string $busqueda = ''): static
    {
        $this->select('cliente.*, direccion.calle, direccion.numero,
                        direccion.colonia, direccion.municipio, direccion.estado')
             ->join('direccion', 'direccion.id_cliente = cliente.id_cliente', 'left');

        if ($busqueda !== '') {
            $this->groupStart()
                 ->like('cliente.nombre', $busqueda)
                 ->orLike('cliente.ap_paterno', $busqueda)
                 ->orLike('cliente.telefono', $busqueda)
                 ->groupEnd();
        }

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
