<?php
// Test edge cases on SQLite3
require __DIR__ . '/../../../system/Test/bootstrap.php';

$db = \Config\Database::connect('tests');
$forge = \Config\Database::forge('tests');

// Setup tables
$forge->dropTable('Usuario', true);
$forge->dropTable('Pais', true);

$forge->addField([
    'Id_pais' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
    'Pais'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
]);
$forge->addPrimaryKey('Id_pais');
$forge->createTable('Pais', true);

$forge->addField([
    'Codigo_Empleado'   => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => false],
    'Nombre'            => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => false],
    'Usuario'           => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => false, 'unique' => true],
    'Contrasena'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
    'Id_Pais'           => ['type' => 'INT', 'constraint' => 11, 'null' => false],
    'telefono'          => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
    'rol'               => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => false],
    'intentos_fallidos' => ['type' => 'INT', 'constraint' => 11, 'default' => 0, 'null' => false],
    'bloqueado'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'null' => false],
    'ultimo_fallo'      => ['type' => 'DATETIME', 'null' => true],
    'ultimo_login'      => ['type' => 'DATETIME', 'null' => true],
    'creado_en'         => ['type' => 'DATETIME', 'null' => true],
    'actualizado_en'    => ['type' => 'DATETIME', 'null' => true],
]);
$forge->addPrimaryKey('Codigo_Empleado');
$forge->addForeignKey('Id_Pais', 'Pais', 'Id_pais', 'CASCADE', 'RESTRICT');
$forge->createTable('Usuario', true);

// Edge Case 1: FK violation (insert user with non-existent Id_Pais 999)
echo "Edge Case 1: Foreign key violation test...\n";
try {
    $db->table('Usuario')->insert([
        'Codigo_Empleado' => 'FAIL01',
        'Nombre'          => 'Fail User',
        'Usuario'         => 'fail_user',
        'Contrasena'      => 'hash',
        'Id_Pais'         => 999,
        'rol'             => 'Asesor',
    ]);
    echo "FAIL: Foreign key was not enforced!\n";
} catch (\Throwable $e) {
    echo "SUCCESS (Expected Exception): " . $e->getMessage() . "\n";
}

// Edge Case 2: Duplicate username
echo "Edge Case 2: Duplicate username test...\n";
$db->table('Pais')->insert(['Id_pais' => 1, 'Pais' => 'El Salvador']);
$db->table('Usuario')->insert([
    'Codigo_Empleado' => 'U1',
    'Nombre'          => 'User One',
    'Usuario'         => 'duplicate_user',
    'Contrasena'      => 'hash',
    'Id_Pais'         => 1,
    'rol'             => 'Asesor',
]);
try {
    $db->table('Usuario')->insert([
        'Codigo_Empleado' => 'U2',
        'Nombre'          => 'User Two',
        'Usuario'         => 'duplicate_user',
        'Contrasena'      => 'hash',
        'Id_Pais'         => 1,
        'rol'             => 'Asesor',
    ]);
    echo "FAIL: Duplicate username was allowed!\n";
} catch (\Throwable $e) {
    echo "SUCCESS (Expected Exception): " . $e->getMessage() . "\n";
}

// Edge Case 3: Explicit Id_pais insertion and auto-increment continuation
echo "Edge Case 3: Explicit Id_pais insertion...\n";
$db->table('Pais')->insert(['Id_pais' => 2, 'Pais' => 'Guatemala']);
$db->table('Pais')->insert(['Id_pais' => 3, 'Pais' => 'Honduras']);
$db->table('Pais')->insert(['Pais' => 'Nicaragua']); // Should get 4
$row = $db->table('Pais')->where('Pais', 'Nicaragua')->get()->getRowArray();
echo "Auto-increment after explicit IDs: Id_pais = " . $row['Id_pais'] . "\n";

// Edge Case 4: Nullable fields handling
echo "Edge Case 4: Nullable fields defaults...\n";
$db->table('Usuario')->insert([
    'Codigo_Empleado' => 'U3',
    'Nombre'          => 'Null Test',
    'Usuario'         => 'null_test',
    'Contrasena'      => 'hash',
    'Id_Pais'         => 1,
    'rol'             => 'Supervisor',
]);
$u3 = $db->table('Usuario')->where('Codigo_Empleado', 'U3')->get()->getRowArray();
echo "intentos_fallidos default: " . var_export($u3['intentos_fallidos'], true) . "\n";
echo "bloqueado default: " . var_export($u3['bloqueado'], true) . "\n";
echo "ultimo_fallo default: " . var_export($u3['ultimo_fallo'], true) . "\n";
echo "telefono default: " . var_export($u3['telefono'], true) . "\n";

// Edge Case 5: Drop table order with FK
echo "Edge Case 5: Drop order test...\n";
try {
    // If we try to drop Pais first while Usuario still references it:
    // With foreign_keys = ON in SQLite, dropping referenced table
    $forge->dropTable('Pais', true);
    echo "Pais dropped directly without error.\n";
} catch (\Throwable $e) {
    echo "Drop Pais failed as expected: " . $e->getMessage() . "\n";
}
