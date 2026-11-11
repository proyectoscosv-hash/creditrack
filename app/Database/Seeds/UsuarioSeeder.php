<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        $defaultPasswordHash = password_hash('Password123!', PASSWORD_BCRYPT);
        $now = date('Y-m-d H:i:s');

        $users = [
            // El Salvador (Id_Pais = 1)
            [
                'Codigo_Empleado'   => 'EMP-SV-001',
                'Nombre'            => 'Carlos Administrador SV',
                'Usuario'           => 'admin_sv',
                'Contrasena'        => $defaultPasswordHash,
                'Id_Pais'           => 1,
                'telefono'          => '50370000001',
                'rol'               => 'Administrador',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => $now,
                'actualizado_en'    => $now,
            ],
            [
                'Codigo_Empleado'   => 'EMP-SV-002',
                'Nombre'            => 'Elena Supervisora SV',
                'Usuario'           => 'supervisor_sv',
                'Contrasena'        => $defaultPasswordHash,
                'Id_Pais'           => 1,
                'telefono'          => '50370000002',
                'rol'               => 'Supervisor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => $now,
                'actualizado_en'    => $now,
            ],
            [
                'Codigo_Empleado'   => 'EMP-SV-003',
                'Nombre'            => 'Mario Asesor SV',
                'Usuario'           => 'asesor_sv',
                'Contrasena'        => $defaultPasswordHash,
                'Id_Pais'           => 1,
                'telefono'          => '50370000003',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => $now,
                'actualizado_en'    => $now,
            ],

            // Guatemala (Id_Pais = 2)
            [
                'Codigo_Empleado'   => 'EMP-GT-001',
                'Nombre'            => 'Ana Administradora GT',
                'Usuario'           => 'admin_gt',
                'Contrasena'        => $defaultPasswordHash,
                'Id_Pais'           => 2,
                'telefono'          => '50250000001',
                'rol'               => 'Administrador',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => $now,
                'actualizado_en'    => $now,
            ],
            [
                'Codigo_Empleado'   => 'EMP-GT-002',
                'Nombre'            => 'Jorge Supervisor GT',
                'Usuario'           => 'supervisor_gt',
                'Contrasena'        => $defaultPasswordHash,
                'Id_Pais'           => 2,
                'telefono'          => '50250000002',
                'rol'               => 'Supervisor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => $now,
                'actualizado_en'    => $now,
            ],
            [
                'Codigo_Empleado'   => 'EMP-GT-003',
                'Nombre'            => 'Lucia Asesora GT',
                'Usuario'           => 'asesor_gt',
                'Contrasena'        => $defaultPasswordHash,
                'Id_Pais'           => 2,
                'telefono'          => '50250000003',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => $now,
                'actualizado_en'    => $now,
            ],

            // Honduras (Id_Pais = 3)
            [
                'Codigo_Empleado'   => 'EMP-HN-001',
                'Nombre'            => 'Roberto Administrador HN',
                'Usuario'           => 'admin_hn',
                'Contrasena'        => $defaultPasswordHash,
                'Id_Pais'           => 3,
                'telefono'          => '50490000001',
                'rol'               => 'Administrador',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => $now,
                'actualizado_en'    => $now,
            ],
            [
                'Codigo_Empleado'   => 'EMP-HN-002',
                'Nombre'            => 'Claudia Supervisora HN',
                'Usuario'           => 'supervisor_hn',
                'Contrasena'        => $defaultPasswordHash,
                'Id_Pais'           => 3,
                'telefono'          => '50490000002',
                'rol'               => 'Supervisor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => $now,
                'actualizado_en'    => $now,
            ],
            [
                'Codigo_Empleado'   => 'EMP-HN-003',
                'Nombre'            => 'David Asesor HN',
                'Usuario'           => 'asesor_hn',
                'Contrasena'        => $defaultPasswordHash,
                'Id_Pais'           => 3,
                'telefono'          => '50490000003',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => $now,
                'actualizado_en'    => $now,
            ],

            // Security Edge-Case Users for Automated Tests
            [
                'Codigo_Empleado'   => 'EMP-LOCKED-01',
                'Nombre'            => 'Usuario Bloqueado Test',
                'Usuario'           => 'user_locked',
                'Contrasena'        => $defaultPasswordHash,
                'Id_Pais'           => 1,
                'telefono'          => '50379999999',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 3,
                'bloqueado'         => 1,
                'ultimo_fallo'      => $now,
                'ultimo_login'      => null,
                'creado_en'         => $now,
                'actualizado_en'    => $now,
            ],
            [
                'Codigo_Empleado'   => 'EMP-ATTEMPT-01',
                'Nombre'            => 'Usuario Dos Fallos Test',
                'Usuario'           => 'user_2attempts',
                'Contrasena'        => $defaultPasswordHash,
                'Id_Pais'           => 1,
                'telefono'          => '50378888888',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 2,
                'bloqueado'         => 0,
                'ultimo_fallo'      => $now,
                'ultimo_login'      => null,
                'creado_en'         => $now,
                'actualizado_en'    => $now,
            ],
        ];

        $builder = $this->db->table('Usuario');

        foreach ($users as $u) {
            $existing = $builder->where('Codigo_Empleado', $u['Codigo_Empleado'])->get()->getRow();
            if ($existing) {
                $builder->where('Codigo_Empleado', $u['Codigo_Empleado'])->update($u);
            } else {
                $builder->insert($u);
            }
        }
    }
}
