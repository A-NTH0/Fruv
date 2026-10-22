<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCodigoToProducto extends Migration
{
    public function up()
    {
        $this->forge->addColumn('producto', [
            'codigo' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'after'      => 'id_producto',
            ],
        ]);

        $productos = $this->db->table('producto')
            ->select('id_producto')
            ->where('codigo', null)
            ->get()
            ->getResultArray();

        foreach ($productos as $producto) {
            $this->db->table('producto')
                ->where('id_producto', $producto['id_producto'])
                ->update(['codigo' => 'LEGACY-' . $producto['id_producto']]);
        }

        $this->forge->modifyColumn('producto', [
            'codigo' => [
                'name'       => 'codigo',
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
            ],
        ]);
        $this->forge->addUniqueKey('codigo', 'uq_producto_codigo');
    }

    public function down()
    {
        $this->forge->dropColumn('producto', 'codigo');
    }
}
