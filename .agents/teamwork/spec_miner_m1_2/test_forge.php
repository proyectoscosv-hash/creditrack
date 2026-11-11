<?php
// Standalone script to test CI4 Forge schema generation for Pais and Usuario
require __DIR__ . '/../../../system/Test/bootstrap.php';

// Test 1: SQLite Forge
$dbTests = \Config\Database::connect('tests');
$forgeTests = \Config\Database::forge('tests');

echo "--- Testing SQLite3 Forge ---\n";
// Drop in reverse order
$forgeTests->dropTable('Usuario', true);
$forgeTests->dropTable('Pais', true);

// Create Pais
$forgeTests->addField([
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
$forgeTests->addPrimaryKey('Id_pais');
$forgeTests->createTable('Pais', true);
echo "Pais table created in SQLite3 successfully.\n";

// Create Usuario
$forgeTests->addField([
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
$forgeTests->addPrimaryKey('Codigo_Empleado');
$forgeTests->addForeignKey('Id_Pais', 'Pais', 'Id_pais', 'CASCADE', 'RESTRICT');
$forgeTests->createTable('Usuario', true);
echo "Usuario table created in SQLite3 successfully.\n";

// Verify tables in SQLite
$tables = $dbTests->listTables();
echo "Tables in SQLite3 tests DB:\n";
print_r($tables);

// Verify insert Pais
$dbTests->table('Pais')->insert(['Pais' => 'El Salvador']);
$paisId = $dbTests->insertID();
echo "Inserted Pais with ID: $paisId\n";

// Verify insert Usuario
$dbTests->table('Usuario')->insert([
    'Codigo_Empleado' => 'EMP001',
    'Nombre' => 'Admin Test',
    'Usuario' => 'admin_sv',
    'Contrasena' => password_hash('Password123!', PASSWORD_BCRYPT),
    'Id_Pais' => $paisId,
    'telefono' => '12345678',
    'rol' => 'Administrador',
    'intentos_fallidos' => 0,
    'bloqueado' => 0,
]);
echo "Inserted Usuario in SQLite3 successfully.\n";

$user = $dbTests->table('Usuario')
    ->select('db_Usuario.*, db_Pais.Pais as pais_nombre')
    ->join('Pais', 'Pais.Id_pais = Usuario.Id_Pais')
    ->get()
    ->getRowArray();
echo "Joined query result:\n";
print_r($user);
