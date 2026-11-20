<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class PaisSeeder extends Seeder
{
    public function run(): void
    {
        $isPostgre = strcasecmp((string) ($this->db->DBDriver ?? ''), 'Postgre') === 0;
        $tables    = $this->db->listTables();
        $useLower  = $isPostgre && ! in_array('Pais', $tables, true) && in_array('pais', $tables, true);

        $table    = $useLower ? 'pais' : 'Pais';
        $idCol    = $useLower ? 'id_pais' : 'Id_pais';
        $nameCol  = $useLower ? 'pais' : 'Pais';
        $hasReq   = $this->db->fieldExists('requiere_distrito', $table);

        $countries = [
            ['id' => 1, 'name' => 'El Salvador', 'requiere_distrito' => 1],
            ['id' => 2, 'name' => 'Guatemala', 'requiere_distrito' => 0],
            ['id' => 3, 'name' => 'Honduras', 'requiere_distrito' => 1],
        ];

        foreach ($countries as $c) {
            $builder  = $this->db->table($table);
            $existing = $builder->where($idCol, $c['id'])->get()->getRow();

            $updateData = [$nameCol => $c['name']];
            if ($hasReq) {
                $updateData['requiere_distrito'] = $c['requiere_distrito'];
            }

            if ($existing) {
                $this->db->table($table)->where($idCol, $c['id'])->update($updateData);
            } else {
                $insertData = array_merge([$idCol => $c['id']], $updateData);
                $this->db->table($table)->insert($insertData);
            }
        }

        $this->syncPostgreSequence($table, $idCol);
    }

    private function syncPostgreSequence(string $table, string $pkColumn): void
    {
        if (strcasecmp((string) ($this->db->DBDriver ?? ''), 'Postgre') !== 0) {
            return;
        }

        $pgTableIdent = ($table === strtolower($table)) ? $table : '"' . $table . '"';
        $row          = $this->db->query('SELECT pg_get_serial_sequence(?, ?) AS seq', [$pgTableIdent, $pkColumn])->getRow();

        if ($row && ! empty($row->seq)) {
            $quotedTable = $this->db->escapeIdentifiers($table);
            $quotedPk    = $this->db->escapeIdentifiers($pkColumn);
            $this->db->query(
                'SELECT setval(?, GREATEST(COALESCE((SELECT MAX(' . $quotedPk . ') FROM ' . $quotedTable . '), 0), 1), (SELECT COUNT(*) > 0 FROM ' . $quotedTable . '))',
                [$row->seq]
            );
        }
    }
}
