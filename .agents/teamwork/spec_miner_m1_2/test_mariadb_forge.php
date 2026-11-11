<?php
require __DIR__ . '/../../../system/Test/bootstrap.php';

// Test MariaDB connection
$mysqli = @new mysqli('127.0.0.1', 'root', '');
if ($mysqli->connect_error) {
    echo "MariaDB connection error: " . $mysqli->connect_error . "\n";
    exit(1);
}
echo "Connected to MariaDB server successfully.\n";

// Check if creditrack DB exists
$res = $mysqli->query("SHOW DATABASES LIKE 'creditrack'");
if ($res->num_rows === 0) {
    echo "Database creditrack does not exist yet. Creating it for testing...\n";
    $mysqli->query("CREATE DATABASE IF NOT EXISTS creditrack CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;");
    echo "Database creditrack created.\n";
} else {
    echo "Database creditrack already exists.\n";
}

// Now test CI4 Database with default group config
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

echo "Connected via CI4 Database MySQLi.\n";

// Drop tables
$forge->dropTable('Usuario', true);
$forge->dropTable('Pais', true);

// Create Pais
$forge->addField([
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
$forge->addPrimaryKey('Id_pais');
$forge->createTable('Pais', true);
echo "Pais table created in MariaDB.\n";

// Create Usuario
$forge->addField([
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
$forge->addPrimaryKey('Codigo_Empleado');
$forge->addForeignKey('Id_Pais', 'Pais', 'Id_pais', 'CASCADE', 'RESTRICT');
$forge->createTable('Usuario', true);
echo "Usuario table created in MariaDB.\n";

// Check tables
$tables = $db->listTables();
print_r($tables);
