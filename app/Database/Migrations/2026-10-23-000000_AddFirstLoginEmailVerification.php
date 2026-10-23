<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFirstLoginEmailVerification extends Migration
{
    private const TABLES = ['admin', 'vendedor', 'cliente'];

    public function up()
    {
        $columns = [
            'email_verified_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'email_verification_code_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'email_verification_expires_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'email_verification_sent_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'email_verification_attempts' => [
                'type'       => 'TINYINT',
                'constraint' => 2,
                'default'    => 0,
                'null'       => false,
            ],
            'email_verification_locked_until' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ];

        foreach (self::TABLES as $table) {
            $existing = array_fill_keys($this->db->getFieldNames($table), true);
            $missing = array_diff_key($columns, $existing);
            if ($missing !== []) {
                $this->forge->addColumn($table, $missing);
            }
        }
    }

    public function down()
    {
        $columns = [
            'email_verified_at',
            'email_verification_code_hash',
            'email_verification_expires_at',
            'email_verification_sent_at',
            'email_verification_attempts',
            'email_verification_locked_until',
        ];

        foreach (self::TABLES as $table) {
            $existing = array_fill_keys($this->db->getFieldNames($table), true);
            $present = array_values(array_intersect($columns, array_keys($existing)));
            if ($present !== []) {
                $this->forge->dropColumn($table, $present);
            }
        }
    }
}
