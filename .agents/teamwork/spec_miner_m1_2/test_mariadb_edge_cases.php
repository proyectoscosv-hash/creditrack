<?php
require __DIR__ . '/../../../system/Test/bootstrap.php';

$customConfig = [
    'DSN'          => '',
    'hostname'     => '127.0.0.1',
    'username'     => 'root',
    'password'     => '',
    'database'     => 'creditrack',
    'DBDriver'     => 'MySQLi',
    'DBPrefix'     => '',
    'pConnect'     => false,
    'DBDebug'      => true,
    'charset'      => 'utf8mb4',
    'DBCollat'     => 'utf8mb4_general_ci',
    'port'         => 3306,
];

$db = \CodeIgniter\Database\Config::connect($customConfig);
$forge = \CodeIgniter\Database\Config::forge($customConfig);

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

// Edge Case 1: FK violation
echo "MariaDB Edge Case 1: Foreign key violation test...\n";
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
echo "MariaDB Edge Case 2: Duplicate username test...\n";
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

// Edge Case 3: Drop order with FK in MariaDB
echo "MariaDB Edge Case 3: Drop order test...\n";
try {
    // In MariaDB with FK checks enabled: dropping Pais while Usuario references it MUST fail!
    $forge->dropTable('Pais', false);
    echo "MariaDB dropped Pais directly.\n";
} catch (\Throwable $e) {
    echo "MariaDB Drop Pais failed as expected with FK error: " . $e->getMessage() . "\n";
}
