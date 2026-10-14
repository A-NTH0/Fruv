<?php namespace App\Models;
use CodeIgniter\Model;

class EntradaModel extends Model {
    protected $table      = 'entrada';
    protected $primaryKey = 'id_entrada';
    protected $allowedFields = [
        'id_producto2', 'fecha', 'fecha_caducidad',
        'cantidad', 'unidad_compra', 'unidad_venta',
        'precio_compra', 'equivalente', 'conversion'
    ];


    public function getEntradasConProductos($limite = 10)
    {
        return $this->select('entrada.*, producto.nombre, producto.categoria')
                    ->join('producto', 'producto.id_producto = entrada.id_producto2')
                    ->orderBy('entrada.fecha', 'DESC')
                    ->findAll($limite);
    }

    public function getAllEntradas()
    {
        return $this->getEntradasConProductos(1000);
    }
}
