<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MarcaSeeder extends Seeder
{
    /**
     * Canonical catalog of motorcycle brands.
     *
     * @return list<array{Id_marca: int, Marca: string}>
     */
    public static function getCatalog(): array
    {
        return [
            ['Id_marca' => 1, 'Marca' => 'Honda'],
            ['Id_marca' => 2, 'Marca' => 'FREEDOM'],
            ['Id_marca' => 3, 'Marca' => 'TVS'],
           
        ];
    }

    public function run(): void
    {
        $isPostgre = strcasecmp((string) ($this->db->DBDriver ?? ''), 'Postgre') === 0;
        $tables    = $this->db->listTables();
        $useLower  = $isPostgre && ! in_array('Marca', $tables, true) && in_array('marca', $tables, true);

        $table   = $useLower ? 'marca' : 'Marca';
        $idCol   = $useLower ? 'id_marca' : 'Id_marca';
        $nameCol = $useLower ? 'marca' : 'Marca';

        foreach (self::getCatalog() as $item) {
            $existingById = $this->db->table($table)
                ->where($idCol, $item['Id_marca'])
                ->get()
                ->getRowArray();

            if ($existingById !== null) {
                $this->db->table($table)
                    ->where($idCol, $item['Id_marca'])
                    ->update([$nameCol => $item['Marca']]);
                continue;
            }

            $existingByName = $this->db->table($table)
                ->where('LOWER(' . $this->db->escapeIdentifiers($nameCol) . ')', strtolower($item['Marca']))
                ->get()
                ->getRowArray();

            if ($existingByName !== null) {
                $existingId = (int) ($existingByName[$idCol] ?? $existingByName['id_marca'] ?? $existingByName['Id_marca'] ?? 0);
                $this->db->table($table)
                    ->where($idCol, $existingId)
                    ->update([$nameCol => $item['Marca']]);
                continue;
            }

            $this->db->table($table)->insert([
                $idCol   => $item['Id_marca'],
                $nameCol => $item['Marca'],
            ]);
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
