<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCoordenadasToDireccion extends Migration
{
    public function up()
    {
        $columnas = [
            'latitud'        => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true, 'after' => 'estado'],
            'longitud'       => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true, 'after' => 'latitud'],
            'geo_precision'  => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'longitud'],
            'geo_intento_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'geo_precision'],
        ];

        // Si ya se corrió el .sql a mano, no duplicar columnas.
        foreach ($columnas as $nombre => $def) {
            if (! $this->db->fieldExists($nombre, 'direccion')) {
                $this->forge->addColumn('direccion', [$nombre => $def]);
            }
        }
    }

    public function down()
    {
        foreach (['geo_intento_at', 'geo_precision', 'longitud', 'latitud'] as $nombre) {
            if ($this->db->fieldExists($nombre, 'direccion')) {
                $this->forge->dropColumn('direccion', $nombre);
            }
        }
    }
}
