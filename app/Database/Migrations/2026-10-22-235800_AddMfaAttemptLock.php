<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMfaAttemptLock extends Migration
{
    public function up()
    {
        $columns = [
            'mfa_failed_attempts' => [
                'type'       => 'TINYINT',
                'constraint' => 2,
                'default'    => 0,
                'null'       => false,
            ],
            'mfa_locked_until' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ];

        foreach (['admin', 'vendedor'] as $table) {
            $existing = array_fill_keys($this->db->getFieldNames($table), true);
            $missing = array_diff_key($columns, $existing);
            if ($missing !== []) {
                $this->forge->addColumn($table, $missing);
            }
        }
    }

    public function down()
    {
        $columns = ['mfa_failed_attempts', 'mfa_locked_until'];
        $this->forge->dropColumn('admin', $columns);
        $this->forge->dropColumn('vendedor', $columns);
    }
}
