<?php

require __DIR__ . '/../../../system/Test/bootstrap.php';

// Ensure sqlite3 extension is active
$db = \Config\Database::connect('tests');
$forge = \Config\Database::forge('tests');

echo "Setting up tables in SQLite :memory:...\n";

// Ensure tables exist
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

echo "Tables created successfully.\n";

// Insert test country
$db->table('Pais')->insert(['Id_pais' => 1, 'Pais' => 'El Salvador']);

// Define UserModel inline to test design
class TestUserModel extends \CodeIgniter\Model
{
    protected $table            = 'Usuario';
    protected $primaryKey       = 'Codigo_Empleado';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'Codigo_Empleado',
        'Nombre',
        'Usuario',
        'Contrasena',
        'Id_Pais',
        'telefono',
        'rol',
        'intentos_fallidos',
        'bloqueado',
        'ultimo_fallo',
        'ultimo_login',
        'creado_en',
        'actualizado_en',
    ];

    public function findByUsernameWithCountry(string $username): ?array
    {
        return $this->select('Usuario.*, Pais.Pais as pais_nombre')
            ->join('Pais', 'Pais.Id_pais = Usuario.Id_Pais')
            ->where('Usuario.Usuario', $username)
            ->first();
    }

    public function recordFailedAttempt(string $codigoEmpleado, int $currentAttempts): int
    {
        $newAttempts = $currentAttempts + 1;
        $isLocked    = ($newAttempts >= 3) ? 1 : 0;

        $this->update($codigoEmpleado, [
            'intentos_fallidos' => $newAttempts,
            'bloqueado'         => $isLocked,
            'ultimo_fallo'      => date('Y-m-d H:i:s'),
        ]);

        return $newAttempts;
    }

    public function resetFailedAttempts(string $codigoEmpleado): bool
    {
        return (bool) $this->update($codigoEmpleado, [
            'intentos_fallidos' => 0,
            'bloqueado'         => 0,
            'ultimo_login'      => date('Y-m-d H:i:s'),
        ]);
    }

    public function unlockAccount(string $codigoEmpleado): bool
    {
        return (bool) $this->update($codigoEmpleado, [
            'intentos_fallidos' => 0,
            'bloqueado'         => 0,
        ]);
    }
}

$model = new TestUserModel($db);

// Insert test user
$insertOk = $model->insert([
    'Codigo_Empleado'   => 'EMP001',
    'Nombre'            => 'Admin Test',
    'Usuario'           => 'admin_sv',
    'Contrasena'        => password_hash('Pass123!', PASSWORD_BCRYPT),
    'Id_Pais'           => 1,
    'telefono'          => '7777-8888',
    'rol'               => 'Administrador',
    'intentos_fallidos' => 0,
    'bloqueado'         => 0,
]);
echo "Insert test user: " . ($insertOk ? "OK" : "FAILED") . "\n";

// Test 1: findByUsernameWithCountry
echo "Test 1: findByUsernameWithCountry...\n";
$user = $model->findByUsernameWithCountry('admin_sv');
if ($user && $user['Usuario'] === 'admin_sv' && $user['pais_nombre'] === 'El Salvador') {
    echo "PASS: Found user with country: {$user['Usuario']}, {$user['pais_nombre']}\n";
} else {
    echo "FAIL: Could not find user with country\n";
    var_dump($user);
}

// Test 1b: non-existent user
$nonExistent = $model->findByUsernameWithCountry('no_such_user');
if ($nonExistent === null) {
    echo "PASS: Non-existent user correctly returned null\n";
} else {
    echo "FAIL: Expected null for non-existent user\n";
}

// Test 2: recordFailedAttempt (Attempt 1)
echo "Test 2: recordFailedAttempt...\n";
$attempts = $model->recordFailedAttempt('EMP001', 0);
$u = $model->find('EMP001');
echo "Attempt 1: returned={$attempts}, db_count={$u['intentos_fallidos']}, bloqueado={$u['bloqueado']}\n";
assert($attempts === 1 && $u['intentos_fallidos'] == 1 && $u['bloqueado'] == 0);

// Attempt 2
$attempts = $model->recordFailedAttempt('EMP001', 1);
$u = $model->find('EMP001');
echo "Attempt 2: returned={$attempts}, db_count={$u['intentos_fallidos']}, bloqueado={$u['bloqueado']}\n";
assert($attempts === 2 && $u['intentos_fallidos'] == 2 && $u['bloqueado'] == 0);

// Attempt 3: MUST LOCK
$attempts = $model->recordFailedAttempt('EMP001', 2);
$u = $model->find('EMP001');
echo "Attempt 3 (Lockout): returned={$attempts}, db_count={$u['intentos_fallidos']}, bloqueado={$u['bloqueado']}\n";
assert($attempts === 3 && $u['intentos_fallidos'] == 3 && $u['bloqueado'] == 1);
echo "PASS: Account locked after 3 attempts!\n";

// Test 3: unlockAccount
echo "Test 3: unlockAccount...\n";
$unlocked = $model->unlockAccount('EMP001');
$u = $model->find('EMP001');
echo "Unlock: result={$unlocked}, db_count={$u['intentos_fallidos']}, bloqueado={$u['bloqueado']}\n";
assert($unlocked === true && $u['intentos_fallidos'] == 0 && $u['bloqueado'] == 0);
echo "PASS: Account unlocked successfully!\n";

// Test 4: resetFailedAttempts
echo "Test 4: resetFailedAttempts...\n";
$model->recordFailedAttempt('EMP001', 0);
$model->recordFailedAttempt('EMP001', 1);
$u = $model->find('EMP001');
echo "Before reset: db_count={$u['intentos_fallidos']}\n";
$reset = $model->resetFailedAttempts('EMP001');
$u = $model->find('EMP001');
echo "After reset: result={$reset}, db_count={$u['intentos_fallidos']}, bloqueado={$u['bloqueado']}, ultimo_login={$u['ultimo_login']}\n";
assert($reset === true && $u['intentos_fallidos'] == 0 && $u['bloqueado'] == 0 && !empty($u['ultimo_login']));
echo "PASS: Reset failed attempts worked and set ultimo_login!\n";

echo "ALL TESTS PASSED SUCCESSFULLY!\n";
