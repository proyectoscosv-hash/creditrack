<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class PaisSeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['Id_pais' => 1, 'Pais' => 'El Salvador'],
            ['Id_pais' => 2, 'Pais' => 'Guatemala'],
            ['Id_pais' => 3, 'Pais' => 'Honduras'],
        ];

        $builder = $this->db->table('Pais');

        foreach ($countries as $c) {
            $existing = $builder->where('Id_pais', $c['Id_pais'])->get()->getRow();
            if ($existing) {
                $builder->where('Id_pais', $c['Id_pais'])->update(['Pais' => $c['Pais']]);
            } else {
                $builder->insert($c);
            }
        }
    }
}
