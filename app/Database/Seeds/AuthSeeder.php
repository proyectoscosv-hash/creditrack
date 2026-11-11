<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AuthSeeder extends Seeder
{
    public function run(): void
    {
        $this->call('PaisSeeder');
        $this->call('UsuarioSeeder');
    }
}
