<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMfaToPrivilegedAccounts extends Migration
{
    public function up()
    {
        $columns = [
            'mfa_enabled' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
            ],
            'mfa_secret' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'mfa_recovery_codes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
        ];

        $this->forge->addColumn('admin', $columns);
        $this->forge->addColumn('vendedor', $columns);
    }

    public function down()
    {
        $columns = ['mfa_enabled', 'mfa_secret', 'mfa_recovery_codes'];
        $this->forge->dropColumn('admin', $columns);
        $this->forge->dropColumn('vendedor', $columns);
    }
}
