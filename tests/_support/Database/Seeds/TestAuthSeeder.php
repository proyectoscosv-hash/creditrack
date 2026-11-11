<?php

namespace Tests\Support\Database\Seeds;

use CodeIgniter\Database\Seeder;

class TestAuthSeeder extends Seeder
{
    /**
     * Cached password hashes to ensure fast, deterministic test execution
     * while utilizing genuine bcrypt password hashes.
     *
     * @var array<string, string>|null
     */
    private static ?array $cachedHashes = null;

    /**
     * Get pre-computed bcrypt hashes for seed passwords.
     *
     * @return array<string, string>
     */
    private static function getHashes(): array
    {
        if (self::$cachedHashes === null) {
            self::$cachedHashes = [
                'admin_default' => password_hash('AdminPass123!', PASSWORD_BCRYPT),
                'super_sv'      => password_hash('SuperSv123!', PASSWORD_BCRYPT),
                'super_gt'      => password_hash('SuperGt123!', PASSWORD_BCRYPT),
                'asesor_sv'     => password_hash('AsesorSv123!', PASSWORD_BCRYPT),
                'asesor_gt'     => password_hash('AsesorGt123!', PASSWORD_BCRYPT),
                'asesor_hn'     => password_hash('AsesorHn123!', PASSWORD_BCRYPT),
                'warn'          => password_hash('WarnPass123!', PASSWORD_BCRYPT),
                'locked'        => password_hash('LockedPass123!', PASSWORD_BCRYPT),
                'special'       => password_hash('P@$$w0rd!#%^&*()_+ñáéíóú', PASSWORD_BCRYPT),
            ];
        }

        return self::$cachedHashes;
    }

    public function run(): void
    {
        // 1. Ensure schema exists using Forge (automatically applies DBPrefix)
        $this->createSchema();

        // 2. Clear tables to guarantee test isolation between runs
        $this->db->table('Usuario')->emptyTable();
        $this->db->table('Pais')->emptyTable();

        // 3. Seed Countries (Pais)
        $paises = [
            ['Id_pais' => 1, 'Pais' => 'El Salvador'],
            ['Id_pais' => 2, 'Pais' => 'Guatemala'],
            ['Id_pais' => 3, 'Pais' => 'Honduras'],
        ];
        $this->db->table('Pais')->insertBatch($paises);

        // 4. Seed Users (Usuario) with bcrypt password hashes
        $hashes = self::getHashes();

        $usuarios = [
            // 1. Admin El Salvador
            [
                'Codigo_Empleado'   => 'EMP-ADM-01',
                'Nombre'            => 'Carlos Admin',
                'Usuario'           => 'admin_sv',
                'Contrasena'        => $hashes['admin_default'],
                'Id_Pais'           => 1,
                'telefono'          => '555-0100',
                'rol'               => 'Administrador',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => '2026-11-10 10:00:00',
                'actualizado_en'    => '2026-11-10 10:00:00',
            ],
            // 2. Alias admin_user for cross-feature workflows
            [
                'Codigo_Empleado'   => 'EMP-ADM-02',
                'Nombre'            => 'Carlos Morales',
                'Usuario'           => 'admin_user',
                'Contrasena'        => $hashes['admin_default'],
                'Id_Pais'           => 1,
                'telefono'          => '555-0101',
                'rol'               => 'Administrador',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => '2026-11-10 10:00:00',
                'actualizado_en'    => '2026-11-10 10:00:00',
            ],
            // 3. Supervisor El Salvador
            [
                'Codigo_Empleado'   => 'EMP-SUP-01',
                'Nombre'            => 'Elena Rostova',
                'Usuario'           => 'supervisor_sv',
                'Contrasena'        => $hashes['super_sv'],
                'Id_Pais'           => 1,
                'telefono'          => '555-0201',
                'rol'               => 'Supervisor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => '2026-11-10 10:00:00',
                'actualizado_en'    => '2026-11-10 10:00:00',
            ],
            // 4. Supervisor Guatemala
            [
                'Codigo_Empleado'   => 'EMP-SUP-02',
                'Nombre'            => 'Beatriz Supervisor',
                'Usuario'           => 'supervisor_gt',
                'Contrasena'        => $hashes['super_gt'],
                'Id_Pais'           => 2,
                'telefono'          => '555-0202',
                'rol'               => 'Supervisor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => '2026-11-10 10:00:00',
                'actualizado_en'    => '2026-11-10 10:00:00',
            ],
            // 5. Asesor El Salvador
            [
                'Codigo_Empleado'   => 'EMP-ASE-01',
                'Nombre'            => 'Juan Perez',
                'Usuario'           => 'asesor_sv',
                'Contrasena'        => $hashes['asesor_sv'],
                'Id_Pais'           => 1,
                'telefono'          => '555-0301',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => '2026-11-10 10:00:00',
                'actualizado_en'    => '2026-11-10 10:00:00',
            ],
            // 6. Asesor Guatemala
            [
                'Codigo_Empleado'   => 'EMP-ASE-02',
                'Nombre'            => 'Ana Gomez',
                'Usuario'           => 'asesor_gt',
                'Contrasena'        => $hashes['asesor_gt'],
                'Id_Pais'           => 2,
                'telefono'          => '555-0302',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => '2026-11-10 10:00:00',
                'actualizado_en'    => '2026-11-10 10:00:00',
            ],
            // 7. Asesor Honduras
            [
                'Codigo_Empleado'   => 'EMP-ASE-03',
                'Nombre'            => 'Alejandro Asesor',
                'Usuario'           => 'asesor_hn',
                'Contrasena'        => $hashes['asesor_hn'],
                'Id_Pais'           => 3,
                'telefono'          => '555-0303',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => '2026-11-10 10:00:00',
                'actualizado_en'    => '2026-11-10 10:00:00',
            ],
            // 8. User with 2 prior failed attempts (for reset on success & 3rd lockout tests)
            [
                'Codigo_Empleado'   => 'EMP-WRN-01',
                'Nombre'            => 'Wendy Advertencia',
                'Usuario'           => 'user_warning',
                'Contrasena'        => $hashes['warn'],
                'Id_Pais'           => 1,
                'telefono'          => '555-0401',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 2,
                'bloqueado'         => 0,
                'ultimo_fallo'      => '2026-11-10 10:00:00',
                'ultimo_login'      => null,
                'creado_en'         => '2026-11-10 10:00:00',
                'actualizado_en'    => '2026-11-10 10:00:00',
            ],
            // 9. User pre-locked (3 attempts, bloqueado = 1) for 4th attempt rejection test
            [
                'Codigo_Empleado'   => 'EMP-LCK-01',
                'Nombre'            => 'Lorenzo Bloqueado',
                'Usuario'           => 'locked_user',
                'Contrasena'        => $hashes['locked'],
                'Id_Pais'           => 1,
                'telefono'          => '555-0501',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 3,
                'bloqueado'         => 1,
                'ultimo_fallo'      => '2026-11-10 10:00:00',
                'ultimo_login'      => null,
                'creado_en'         => '2026-11-10 10:00:00',
                'actualizado_en'    => '2026-11-10 10:00:00',
            ],
            // 10. User with special / multibyte characters in password
            [
                'Codigo_Empleado'   => 'EMP-SPC-01',
                'Nombre'            => 'Silvia Especial',
                'Usuario'           => 'special_pwd',
                'Contrasena'        => $hashes['special'],
                'Id_Pais'           => 1,
                'telefono'          => '555-0601',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => '2026-11-10 10:00:00',
                'actualizado_en'    => '2026-11-10 10:00:00',
            ],
            // 11. Clean account for sequential lockout progression
            [
                'Codigo_Empleado'   => 'EMP-CLN-01',
                'Nombre'            => 'Clara Limpia',
                'Usuario'           => 'user_clean',
                'Contrasena'        => $hashes['admin_default'],
                'Id_Pais'           => 1,
                'telefono'          => '555-0701',
                'rol'               => 'Supervisor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => '2026-11-10 10:00:00',
                'actualizado_en'    => '2026-11-10 10:00:00',
            ],
            // 12. XSS escaping test fixture
            [
                'Codigo_Empleado'   => 'EMP-XSS-01',
                'Nombre'            => 'Juan <script>alert(1)</script>',
                'Usuario'           => 'xss_user',
                'Contrasena'        => $hashes['admin_default'],
                'Id_Pais'           => 1,
                'telefono'          => '555-0801',
                'rol'               => 'Asesor',
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_fallo'      => null,
                'ultimo_login'      => null,
                'creado_en'         => '2026-11-10 10:00:00',
                'actualizado_en'    => '2026-11-10 10:00:00',
            ],
        ];

        $this->db->table('Usuario')->insertBatch($usuarios);
    }

    private function createSchema(): void
    {
        // Pais table
        $this->forge->addField([
            'Id_pais' => [
                'type'           => 'INTEGER',
                'auto_increment' => true,
            ],
            'Pais' => [
                'type' => 'TEXT',
                'null' => false,
            ],
        ]);
        $this->forge->addPrimaryKey('Id_pais');
        $this->forge->createTable('Pais', true);

        // Usuario table
        $this->forge->addField([
            'Codigo_Empleado' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'Nombre' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'Usuario' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'Contrasena' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'Id_Pais' => [
                'type' => 'INTEGER',
                'null' => false,
            ],
            'telefono' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'rol' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'intentos_fallidos' => [
                'type'    => 'INTEGER',
                'default' => 0,
                'null'    => false,
            ],
            'bloqueado' => [
                'type'    => 'INTEGER',
                'default' => 0,
                'null'    => false,
            ],
            'ultimo_fallo' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'ultimo_login' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'creado_en' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'actualizado_en' => [
                'type' => 'TEXT',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('Codigo_Empleado');
        $this->forge->addUniqueKey('Usuario');
        $this->forge->createTable('Usuario', true);
    }
}
