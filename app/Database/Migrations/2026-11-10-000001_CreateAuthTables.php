<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuthTables extends Migration
{
    public function up(): void
    {
        // 1. Table: Pais
        $this->forge->addField([
            'Id_pais' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'auto_increment' => true,
            ],
            'Pais' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
        ]);
        $this->forge->addPrimaryKey('Id_pais');
        $this->forge->createTable('Pais', true);

        // 2. Table: Usuario
        $this->forge->addField([
            'Codigo_Empleado' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
            ],
            'Nombre' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => false,
            ],
            'Usuario' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
                'unique'     => true,
            ],
            'Contrasena' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'Id_Pais' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
            ],
            'telefono' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'rol' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
            ],
            'intentos_fallidos' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
                'null'       => false,
            ],
            'bloqueado' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
            ],
            'ultimo_fallo' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'ultimo_login' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'creado_en' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'actualizado_en' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('Codigo_Empleado');
        $this->forge->addForeignKey('Id_Pais', 'Pais', 'Id_pais', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('Usuario', true);
    }

    public function down(): void
    {
        // Drop in reverse order to respect foreign key constraint
        $this->forge->dropTable('Usuario', true);
        $this->forge->dropTable('Pais', true);
    }
}
