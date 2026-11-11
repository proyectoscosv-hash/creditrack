<?php
/**
 * Milestone 1 Stress Test Harness - Challenger 2
 *
 * Covers:
 * 1. Migration & Seeder Idempotency (up -> down -> up, seed twice)
 * 2. Cross-Country Boundary Stress Test (users linked to Pais 1, 2, 3 with exact names, FK constraint)
 * 3. Password Hash Robustness (positive & negative password verification)
 * 4. High Volume Attempt Cycle & Lockout (5 attempts, lock at 3, persist 4 & 5, unlock, reset)
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Setup paths
$projectRoot = dirname(__DIR__, 2);
define('FCPATH', $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);

chdir(FCPATH);

require_once FCPATH . '../vendor/autoload.php';
require_once FCPATH . '../app/Config/Paths.php';

$paths = new \Config\Paths();

// Ensure SYSTEM_PATH and other constants
if (!defined('SYSTEMPATH')) {
    define('SYSTEMPATH', realpath($paths->systemDirectory) . DIRECTORY_SEPARATOR);
}
if (!defined('APPPATH')) {
    define('APPPATH', realpath($paths->appDirectory) . DIRECTORY_SEPARATOR);
}
if (!defined('WRITEPATH')) {
    define('WRITEPATH', realpath($paths->writableDirectory) . DIRECTORY_SEPARATOR);
}
if (!defined('ROOTPATH')) {
    define('ROOTPATH', realpath($projectRoot) . DIRECTORY_SEPARATOR);
}
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'testing');
}

// Load CodeIgniter bootstrap without loading .env
require_once SYSTEMPATH . 'Common.php';
require_once SYSTEMPATH . 'Autoloader/Autoloader.php';
require_once SYSTEMPATH . 'Config/BaseService.php';
require_once SYSTEMPATH . 'Config/Services.php';

// Register autoloader for App
$autoloader = \Config\Services::autoloader();
$autoloader->initialize(new \Config\Autoload(), new \Config\Modules());
$autoloader->register();

// Test reporter
class StressTestReporter
{
    private int $passed = 0;
    private int $failed = 0;
    private array $errors = [];

    public function assert(bool $condition, string $description, string $detail = ''): void
    {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$description}\n";
        } else {
            $this->failed++;
            $msg = "  [FAIL] {$description}" . ($detail ? " (Detail: {$detail})" : "");
            echo $msg . "\n";
            $this->errors[] = $msg;
        }
    }

    public function summary(): bool
    {
        echo "\n=======================================================\n";
        echo "TEST RESULTS SUMMARY:\n";
        echo "Total: " . ($this->passed + $this->failed) . " | Passed: {$this->passed} | Failed: {$this->failed}\n";
        if ($this->failed > 0) {
            echo "\nFailed Assertions:\n";
            foreach ($this->errors as $err) {
                echo "  {$err}\n";
            }
            echo "=======================================================\n";
            return false;
        }
        echo "ALL STRESS TESTS PASSED SUCCESSFULLY!\n";
        echo "=======================================================\n";
        return true;
    }
}

$reporter = new StressTestReporter();

echo "=======================================================\n";
echo "CREDITRACK MILESTONE 1 ADVERSARIAL STRESS TEST SUITE\n";
echo "Agent: Challenger 2 (challenger_m1_2)\n";
echo "=======================================================\n\n";

// Connect to SQLite :memory: via Config\Database 'tests' group
$dbConfig = new \Config\Database();
$db = \Config\Database::connect('tests');
$forge = \Config\Database::forge('tests');

echo "--- 1. MIGRATION IDEMPOTENCY & SCHEMA INTEGRITY ---\n";
require_once APPPATH . 'Database/Migrations/2026-11-10-000001_CreateAuthTables.php';
$migration = new \App\Database\Migrations\CreateAuthTables($forge);

// Step 1: Run UP
try {
    $migration->up();
    $reporter->assert(true, "Initial migration up() executed without exceptions");
} catch (\Throwable $e) {
    $reporter->assert(false, "Initial migration up() failed", $e->getMessage());
}

$reporter->assert($db->tableExists('Pais'), "Table 'Pais' exists after up()");
$reporter->assert($db->tableExists('Usuario'), "Table 'Usuario' exists after up()");

// Step 2: Run DOWN (Reverse order rollback)
try {
    $migration->down();
    $reporter->assert(true, "Migration down() executed without exceptions");
} catch (\Throwable $e) {
    $reporter->assert(false, "Migration down() failed", $e->getMessage());
}

$reporter->assert(!$db->tableExists('Usuario'), "Table 'Usuario' dropped after down()");
$reporter->assert(!$db->tableExists('Pais'), "Table 'Pais' dropped after down()");

// Step 3: Run UP again (Verify Clean Re-creation)
try {
    $migration->up();
    $reporter->assert(true, "Second migration up() executed cleanly");
} catch (\Throwable $e) {
    $reporter->assert(false, "Second migration up() failed", $e->getMessage());
}

$reporter->assert($db->tableExists('Pais'), "Table 'Pais' exists after second up()");
$reporter->assert($db->tableExists('Usuario'), "Table 'Usuario' exists after second up()");

echo "\n--- 2. SEEDER IDEMPOTENCY STRESS TEST ---\n";
require_once APPPATH . 'Database/Seeds/PaisSeeder.php';
require_once APPPATH . 'Database/Seeds/UsuarioSeeder.php';
require_once APPPATH . 'Database/Seeds/AuthSeeder.php';

$seeder = \Config\Database::seeder('tests');

// First Seed
try {
    $seeder->call(\App\Database\Seeds\AuthSeeder::class);
    $reporter->assert(true, "First AuthSeeder call succeeded");
} catch (\Throwable $e) {
    $reporter->assert(false, "First AuthSeeder call failed", $e->getMessage());
}

$countPais1 = $db->table('Pais')->countAllResults();
$countUsers1 = $db->table('Usuario')->countAllResults();
$reporter->assert($countPais1 === 3, "Pais count after seed 1 is 3 (found {$countPais1})");
$reporter->assert($countUsers1 === 11, "Usuario count after seed 1 is 11 (found {$countUsers1})");

// Second Seed (Idempotency check)
try {
    $seeder->call(\App\Database\Seeds\AuthSeeder::class);
    $reporter->assert(true, "Second AuthSeeder call succeeded (no duplicate key / crash)");
} catch (\Throwable $e) {
    $reporter->assert(false, "Second AuthSeeder call failed", $e->getMessage());
}

$countPais2 = $db->table('Pais')->countAllResults();
$countUsers2 = $db->table('Usuario')->countAllResults();
$reporter->assert($countPais2 === 3, "Pais count after seed 2 remains 3 (no duplication)");
$reporter->assert($countUsers2 === 11, "Usuario count after seed 2 remains 11 (no duplication)");

echo "\n--- 3. CROSS-COUNTRY BOUNDARY STRESS TEST ---\n";
require_once APPPATH . 'Models/UserModel.php';
$userModel = new \App\Models\UserModel($db);

$expectedCountryUsers = [
    // El Salvador (1)
    'admin_sv'        => ['id_pais' => 1, 'pais' => 'El Salvador', 'rol' => 'Administrador'],
    'supervisor_sv'   => ['id_pais' => 1, 'pais' => 'El Salvador', 'rol' => 'Supervisor'],
    'asesor_sv'       => ['id_pais' => 1, 'pais' => 'El Salvador', 'rol' => 'Asesor'],
    'user_locked'     => ['id_pais' => 1, 'pais' => 'El Salvador', 'rol' => 'Asesor'],
    'user_2attempts'  => ['id_pais' => 1, 'pais' => 'El Salvador', 'rol' => 'Asesor'],

    // Guatemala (2)
    'admin_gt'        => ['id_pais' => 2, 'pais' => 'Guatemala', 'rol' => 'Administrador'],
    'supervisor_gt'   => ['id_pais' => 2, 'pais' => 'Guatemala', 'rol' => 'Supervisor'],
    'asesor_gt'       => ['id_pais' => 2, 'pais' => 'Guatemala', 'rol' => 'Asesor'],

    // Honduras (3)
    'admin_hn'        => ['id_pais' => 3, 'pais' => 'Honduras', 'rol' => 'Administrador'],
    'supervisor_hn'   => ['id_pais' => 3, 'pais' => 'Honduras', 'rol' => 'Supervisor'],
    'asesor_hn'       => ['id_pais' => 3, 'pais' => 'Honduras', 'rol' => 'Asesor'],
];

foreach ($expectedCountryUsers as $username => $expected) {
    $user = $userModel->findByUsernameWithCountry($username);
    $reporter->assert($user !== null, "User '{$username}' found by findByUsernameWithCountry()");
    if ($user !== null) {
        $reporter->assert(
            (int)$user['Id_Pais'] === $expected['id_pais'],
            "User '{$username}' linked to Id_Pais {$expected['id_pais']} (found {$user['Id_Pais']})"
        );
        $reporter->assert(
            $user['pais_nombre'] === $expected['pais'],
            "User '{$username}' returns exact pais_nombre '{$expected['pais']}' (found '{$user['pais_nombre']}')"
        );
        $reporter->assert(
            $user['rol'] === $expected['rol'],
            "User '{$username}' has expected role '{$expected['rol']}' (found '{$user['rol']}')"
        );
    }
}

// Edge case: Non-existent user
$nonExistent = $userModel->findByUsernameWithCountry('non_existent_user_999');
$reporter->assert($nonExistent === null, "Non-existent username returns null");

// Edge case: SQL injection pattern in username
$injected = $userModel->findByUsernameWithCountry("admin_sv' OR '1'='1");
$reporter->assert($injected === null, "SQL injection username payload safely handled and returns null");

echo "\n--- 4. PASSWORD HASH ROBUSTNESS ---\n";
foreach ($expectedCountryUsers as $username => $expected) {
    $user = $userModel->findByUsernameWithCountry($username);
    if ($user !== null) {
        // Positive check
        $valid = password_verify('Password123!', $user['Contrasena']);
        $reporter->assert($valid, "User '{$username}' password_verify('Password123!') evaluates to true");

        // Negative check 1: missing special char
        $neg1 = password_verify('Password123', $user['Contrasena']);
        $reporter->assert(!$neg1, "User '{$username}' rejects 'Password123'");

        // Negative check 2: wrong case
        $neg2 = password_verify('password123!', $user['Contrasena']);
        $reporter->assert(!$neg2, "User '{$username}' rejects 'password123!' (case sensitive)");

        // Negative check 3: empty password
        $neg3 = password_verify('', $user['Contrasena']);
        $reporter->assert(!$neg3, "User '{$username}' rejects empty password");

        // Negative check 4: trailing whitespace
        $neg4 = password_verify('Password123! ', $user['Contrasena']);
        $reporter->assert(!$neg4, "User '{$username}' rejects trailing whitespace");
    }
}

echo "\n--- 5. HIGH VOLUME ATTEMPT CYCLE & LOCKOUT STRESS TEST ---\n";
// Use 'asesor_sv' for high volume failure cycle
$testEmpCode = 'EMP-SV-003';
$asesor = $userModel->find($testEmpCode);
$reporter->assert((int)$asesor['intentos_fallidos'] === 0, "Initial intentos_fallidos is 0");
$reporter->assert((int)$asesor['bloqueado'] === 0, "Initial bloqueado is 0");

// Attempt 1
$att1 = $userModel->recordFailedAttempt($testEmpCode, 0);
$u1 = $userModel->find($testEmpCode);
$reporter->assert($att1 === 1, "Attempt 1 returns 1");
$reporter->assert((int)$u1['intentos_fallidos'] === 1, "Attempt 1 DB count is 1");
$reporter->assert((int)$u1['bloqueado'] === 0, "Attempt 1 account NOT locked");
$reporter->assert(!empty($u1['ultimo_fallo']), "Attempt 1 sets ultimo_fallo");

// Attempt 2
$att2 = $userModel->recordFailedAttempt($testEmpCode, 1);
$u2 = $userModel->find($testEmpCode);
$reporter->assert($att2 === 2, "Attempt 2 returns 2");
$reporter->assert((int)$u2['intentos_fallidos'] === 2, "Attempt 2 DB count is 2");
$reporter->assert((int)$u2['bloqueado'] === 0, "Attempt 2 account NOT locked");

// Attempt 3 (Threshold: LOCKOUT MUST OCCUR)
$att3 = $userModel->recordFailedAttempt($testEmpCode, 2);
$u3 = $userModel->find($testEmpCode);
$reporter->assert($att3 === 3, "Attempt 3 returns 3");
$reporter->assert((int)$u3['intentos_fallidos'] === 3, "Attempt 3 DB count is 3");
$reporter->assert((int)$u3['bloqueado'] === 1, "Attempt 3 account IS LOCKED (R2 Compliance)");

// Attempt 4 (Lockout must persist)
$att4 = $userModel->recordFailedAttempt($testEmpCode, 3);
$u4 = $userModel->find($testEmpCode);
$reporter->assert($att4 === 4, "Attempt 4 returns 4");
$reporter->assert((int)$u4['intentos_fallidos'] === 4, "Attempt 4 DB count is 4");
$reporter->assert((int)$u4['bloqueado'] === 1, "Attempt 4 account PERSISTS in locked state");

// Attempt 5 (Lockout must persist)
$att5 = $userModel->recordFailedAttempt($testEmpCode, 4);
$u5 = $userModel->find($testEmpCode);
$reporter->assert($att5 === 5, "Attempt 5 returns 5");
$reporter->assert((int)$u5['intentos_fallidos'] === 5, "Attempt 5 DB count is 5");
$reporter->assert((int)$u5['bloqueado'] === 1, "Attempt 5 account PERSISTS in locked state");

// Simulate Administrative Unlock
$unlocked = $userModel->unlockAccount($testEmpCode);
$reporter->assert($unlocked === true, "unlockAccount() returns true");
$uUnlocked = $userModel->find($testEmpCode);
$reporter->assert((int)$uUnlocked['intentos_fallidos'] === 0, "After unlock, intentos_fallidos is reset to 0");
$reporter->assert((int)$uUnlocked['bloqueado'] === 0, "After unlock, bloqueado is reset to 0");

// Immediate successful login simulation: resetFailedAttempts
$reset = $userModel->resetFailedAttempts($testEmpCode);
$reporter->assert($reset === true, "resetFailedAttempts() returns true");
$uSuccess = $userModel->find($testEmpCode);
$reporter->assert((int)$uSuccess['intentos_fallidos'] === 0, "After success, intentos_fallidos is 0");
$reporter->assert((int)$uSuccess['bloqueado'] === 0, "After success, bloqueado is 0");
$reporter->assert(!empty($uSuccess['ultimo_login']), "After success, ultimo_login is populated with current timestamp");

// Final report
$allPassed = $reporter->summary();
exit($allPassed ? 0 : 1);
