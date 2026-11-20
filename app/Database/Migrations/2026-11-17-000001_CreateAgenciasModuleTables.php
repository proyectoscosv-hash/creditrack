<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAgenciasModuleTables extends Migration
{
    public function up(): void
    {
        $isPostgre = $this->isPostgre();
        $id        = static fn (string $name): string => $isPostgre ? strtolower($name) : $name;

        [$paisTable, $paisPk]       = $this->resolveExistingTableAndPk('Pais', 'Id_pais');
        [$usuarioTable, $usuarioPk] = $this->resolveExistingTableAndPk('Usuario', 'Codigo_Empleado');

        // 1. Pais.requiere_distrito
        if (! $this->db->fieldExists('requiere_distrito', $paisTable)) {
            $this->forge->addColumn($paisTable, [
                'requiere_distrito' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
                    'null'       => false,
                ],
            ]);
        }
        $paisNameCol = $isPostgre ? 'pais' : 'Pais';
        $this->db->query(
            'UPDATE ' . $this->db->escapeIdentifiers($paisTable)
            . ' SET ' . $this->db->escapeIdentifiers('requiere_distrito') . ' = 0'
            . ' WHERE LOWER(' . $this->db->escapeIdentifiers($paisNameCol) . ") = 'guatemala'"
        );

        // 2. Marca
        $marcaTable = $id('Marca');
        $this->forge->addField([
            $id('Id_marca') => [
                'type'           => 'INT',
                'constraint'     => 11,
                'auto_increment' => true,
            ],
            $id('Marca') => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
                'unique'     => true,
            ],
        ]);
        $this->forge->addPrimaryKey($id('Id_marca'));
        $this->forge->createTable($marcaTable, true);

        // 3. Departamento
        $deptoTable = $id('Departamento');
        $this->forge->addField([
            $id('Id_departamento') => [
                'type'           => 'INT',
                'constraint'     => 11,
                'auto_increment' => true,
            ],
            $id('Departamento') => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            $id('Id_pais') => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
            ],
        ]);
        $this->forge->addPrimaryKey($id('Id_departamento'));
        $this->forge->addForeignKey($id('Id_pais'), $paisTable, $paisPk, 'CASCADE', 'CASCADE');
        $this->forge->createTable($deptoTable, true);

        // 4. Municipio
        $muniTable = $id('Municipio');
        $this->forge->addField([
            $id('Id_municipio') => [
                'type'           => 'INT',
                'constraint'     => 11,
                'auto_increment' => true,
            ],
            $id('Municipio') => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            $id('Id_departamento') => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
            ],
        ]);
        $this->forge->addPrimaryKey($id('Id_municipio'));
        $this->forge->addForeignKey($id('Id_departamento'), $deptoTable, $id('Id_departamento'), 'CASCADE', 'CASCADE');
        $this->forge->createTable($muniTable, true);

        // 5. Distrito
        $distritoTable = $id('Distrito');
        $this->forge->addField([
            $id('Id_distrito') => [
                'type'           => 'INT',
                'constraint'     => 11,
                'auto_increment' => true,
            ],
            $id('Distrito') => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            $id('Id_municipio') => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
            ],
        ]);
        $this->forge->addPrimaryKey($id('Id_distrito'));
        $this->forge->addForeignKey($id('Id_municipio'), $muniTable, $id('Id_municipio'), 'CASCADE', 'CASCADE');
        $this->forge->createTable($distritoTable, true);

        // 6. Agencias
        $agenciasTable = $id('Agencias');
        $this->forge->addField([
            $id('Idagencia') => [
                'type'           => 'INT',
                'constraint'     => 11,
                'auto_increment' => true,
            ],
            'nombre_agencia' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => false,
            ],
            'direccion' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            $id('Id_pais') => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
            ],
            $id('Id_departamento') => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
            ],
            $id('Id_municipio') => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
            ],
            $id('Id_distrito') => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'latitud' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,8',
                'null'       => true,
            ],
            'longitud' => [
                'type'       => 'DECIMAL',
                'constraint' => '11,8',
                'null'       => true,
            ],
            $id('Id_marca') => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'activo' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
                'null'       => false,
            ],
            'motivo_deshabilitacion' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'deshabilitado_en' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deshabilitado_por' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
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
        $this->forge->addPrimaryKey($id('Idagencia'));
        $this->forge->addKey([$id('Id_pais'), 'activo'], false, false, 'idx_agencias_pais_activo');
        $this->forge->addForeignKey($id('Id_pais'), $paisTable, $paisPk, 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey($id('Id_departamento'), $deptoTable, $id('Id_departamento'), 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey($id('Id_municipio'), $muniTable, $id('Id_municipio'), 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey($id('Id_distrito'), $distritoTable, $id('Id_distrito'), 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey($id('Id_marca'), $marcaTable, $id('Id_marca'), 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('deshabilitado_por', $usuarioTable, $usuarioPk, 'CASCADE', 'RESTRICT');
        $this->forge->createTable($agenciasTable, true);

        // If Agencias already existed before migration (e.g., from Base_Datos.sql on PostgreSQL),
        // ensure all required columns exist and id_distrito is nullable.
        $this->db->resetDataCache();
        $extraAgenciaCols = [
            $id('Id_pais') => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'latitud' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,8',
                'null'       => true,
            ],
            'longitud' => [
                'type'       => 'DECIMAL',
                'constraint' => '11,8',
                'null'       => true,
            ],
            'activo' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
                'null'       => false,
            ],
            'motivo_deshabilitacion' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'deshabilitado_en' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deshabilitado_por' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'creado_en' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'actualizado_en' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ];
        foreach ($extraAgenciaCols as $colName => $colDef) {
            if (! $this->db->fieldExists($colName, $agenciasTable)) {
                $this->forge->addColumn($agenciasTable, [$colName => $colDef]);
            }
        }
        if ($isPostgre) {
            $this->db->query('ALTER TABLE ' . $this->db->escapeIdentifiers($agenciasTable) . ' ALTER COLUMN ' . $this->db->escapeIdentifiers($id('Id_distrito')) . ' DROP NOT NULL');
            if ($this->db->fieldExists('direccion', $agenciasTable)) {
                $this->db->query('ALTER TABLE ' . $this->db->escapeIdentifiers($agenciasTable) . ' ALTER COLUMN direccion DROP NOT NULL');
            }
        }

        // 7. Agencia_Marca (M:N)
        $agenciaMarcaTable = $id('Agencia_Marca');
        $this->forge->addField([
            $id('Id_agencia_marca') => [
                'type'           => 'INT',
                'constraint'     => 11,
                'auto_increment' => true,
            ],
            $id('Idagencia') => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
            ],
            $id('Id_marca') => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
            ],
        ]);
        $this->forge->addPrimaryKey($id('Id_agencia_marca'));
        $this->forge->addUniqueKey([$id('Idagencia'), $id('Id_marca')]);
        $this->forge->addForeignKey($id('Idagencia'), $agenciasTable, $id('Idagencia'), 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey($id('Id_marca'), $marcaTable, $id('Id_marca'), 'CASCADE', 'RESTRICT');
        $this->forge->createTable($agenciaMarcaTable, true);

        // 8. Auditoria_Agencias
        $auditoriaTable = $id('Auditoria_Agencias');
        $this->forge->addField([
            $id('Id_auditoria') => [
                'type'           => 'INT',
                'constraint'     => 11,
                'auto_increment' => true,
            ],
            $id('Idagencia') => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
            ],
            $id('Codigo_Empleado') => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
            ],
            'fecha_hora' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'tipo_operacion' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => false,
            ],
            'datos_anteriores' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'datos_nuevos' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'motivo_deshabilitacion' => [
                'type' => 'TEXT',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey($id('Id_auditoria'));
        $this->forge->addKey([$id('Idagencia'), 'fecha_hora'], false, false, 'idx_auditoria_agencia');
        $this->forge->addForeignKey($id('Idagencia'), $agenciasTable, $id('Idagencia'), 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey($id('Codigo_Empleado'), $usuarioTable, $usuarioPk, 'CASCADE', 'RESTRICT');
        $this->forge->createTable($auditoriaTable, true);

        // 9. Visitas
        $visitasTable = $id('Visitas');
        $this->forge->addField([
            $id('Id_visita') => [
                'type'           => 'INT',
                'constraint'     => 11,
                'auto_increment' => true,
            ],
            $id('Idagencia') => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
            ],
            $id('Codigo_Asesor') => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
            ],
            'fecha_visita' => [
                'type' => 'DATE',
                'null' => false,
            ],
            'hora_visita' => [
                'type' => 'TIME',
                'null' => false,
            ],
            'latitud' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,8',
                'null'       => false,
            ],
            'longitud' => [
                'type'       => 'DECIMAL',
                'constraint' => '11,8',
                'null'       => false,
            ],
            'observaciones' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'creado_en' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey($id('Id_visita'));
        $this->forge->addKey([$id('Idagencia'), 'fecha_visita'], false, false, 'idx_visitas_agencia_fecha');
        $this->forge->addForeignKey($id('Idagencia'), $agenciasTable, $id('Idagencia'), 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey($id('Codigo_Asesor'), $usuarioTable, $usuarioPk, 'CASCADE', 'RESTRICT');
        $this->forge->createTable($visitasTable, true);

        $this->db->resetDataCache();
    }

    public function down(): void
    {
        $isPostgre = $this->isPostgre();
        $id        = static fn (string $name): string => $isPostgre ? strtolower($name) : $name;

        [$paisTable] = $this->resolveExistingTableAndPk('Pais', 'Id_pais');

        $tables = [
            $id('Visitas'),
            $id('Auditoria_Agencias'),
            $id('Agencia_Marca'),
            $id('Agencias'),
            $id('Distrito'),
            $id('Municipio'),
            $id('Departamento'),
            $id('Marca'),
        ];

        foreach ($tables as $table) {
            $this->forge->dropTable($table, true, $isPostgre);
        }

        if ($this->db->fieldExists('requiere_distrito', $paisTable)) {
            if ($this->db->DBDriver === 'SQLite3') {
                $this->db->query('PRAGMA legacy_alter_table = ON');
                try {
                    $this->forge->dropColumn($paisTable, 'requiere_distrito');
                } finally {
                    $this->db->query('PRAGMA legacy_alter_table = OFF');
                }
            } else {
                $this->forge->dropColumn($paisTable, 'requiere_distrito');
            }
        }

        $this->db->resetDataCache();
    }

    private function isPostgre(): bool
    {
        return strcasecmp((string) ($this->db->DBDriver ?? ''), 'Postgre') === 0;
    }

    /**
     * Resolve actual table and primary key casing for pre-existing tables
     * (supporting both CreateAuthTables mixed-case and Base_Datos.sql lowercase on PostgreSQL).
     *
     * @return array{0: string, 1: string}
     */
    private function resolveExistingTableAndPk(string $preferredTable, string $preferredPk): array
    {
        if (! $this->isPostgre()) {
            return [$preferredTable, $preferredPk];
        }

        $tables = $this->db->listTables();

        if (! in_array($preferredTable, $tables, true) && in_array(strtolower($preferredTable), $tables, true)) {
            return [strtolower($preferredTable), strtolower($preferredPk)];
        }

        if (in_array($preferredTable, $tables, true)) {
            $fields = $this->db->getFieldNames($preferredTable);
            if (! in_array($preferredPk, $fields, true) && in_array(strtolower($preferredPk), $fields, true)) {
                return [$preferredTable, strtolower($preferredPk)];
            }
        }

        return [$preferredTable, $preferredPk];
    }
}
