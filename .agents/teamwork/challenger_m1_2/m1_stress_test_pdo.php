<?php
/**
 * Milestone 1 Standalone Engine Stress Test - Challenger 2
 *
 * Empirical verification using SQLite PDO directly against the schema and seed definitions.
 * Tests:
 * 1. Migration up/down/up idempotency
 * 2. Seeder duplicate execution idempotency
 * 3. Cross-country boundaries and FK integrity
 * 4. Password hashing verification (positive and negative)
 * 5. High volume failed attempt cycles and lockout mechanics
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

class StandaloneStressTester
{
    private PDO $pdo;
    private int $passed = 0;
    private int $failed = 0;
    private array $errors = [];

    public function __construct()
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('PRAGMA foreign_keys = ON;');
    }

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

    public function tableExists(string $tableName): bool
    {
        $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = :name");
        $stmt->execute([':name' => $tableName]);
        return (bool)$stmt->fetch();
    }

    public function createSchema(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS Pais (
                Id_pais INTEGER PRIMARY KEY AUTOINCREMENT,
                Pais VARCHAR(100) NOT NULL
            );
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS Usuario (
                Codigo_Empleado VARCHAR(50) PRIMARY KEY,
                Nombre VARCHAR(150) NOT NULL,
                Usuario VARCHAR(50) NOT NULL UNIQUE,
                Contrasena VARCHAR(255) NOT NULL,
                Id_Pais INT NOT NULL,
                telefono VARCHAR(20) NULL,
                rol VARCHAR(50) NOT NULL,
                intentos_fallidos INT NOT NULL DEFAULT 0,
                bloqueado TINYINT NOT NULL DEFAULT 0,
                ultimo_fallo DATETIME NULL,
                ultimo_login DATETIME NULL,
                creado_en DATETIME NULL,
                actualizado_en DATETIME NULL,
                FOREIGN KEY (Id_Pais) REFERENCES Pais(Id_pais) ON UPDATE CASCADE ON DELETE RESTRICT
            );
        ");
    }

    public function dropSchema(): void
    {
        // Reverse order
        $this->pdo->exec("DROP TABLE IF EXISTS Usuario;");
        $this->pdo->exec("DROP TABLE IF EXISTS Pais;");
    }

    public function seedPais(): void
    {
        $countries = [
            ['Id_pais' => 1, 'Pais' => 'El Salvador'],
            ['Id_pais' => 2, 'Pais' => 'Guatemala'],
            ['Id_pais' => 3, 'Pais' => 'Honduras'],
        ];

        $selectStmt = $this->pdo->prepare("SELECT Id_pais FROM Pais WHERE Id_pais = :id");
        $updateStmt = $this->pdo->prepare("UPDATE Pais SET Pais = :pais WHERE Id_pais = :id");
        $insertStmt = $this->pdo->prepare("INSERT INTO Pais (Id_pais, Pais) VALUES (:id, :pais)");

        foreach ($countries as $c) {
            $selectStmt->execute([':id' => $c['Id_pais']]);
            if ($selectStmt->fetch()) {
                $updateStmt->execute([':pais' => $c['Pais'], ':id' => $c['Id_pais']]);
            } else {
                $insertStmt->execute([':id' => $c['Id_pais'], ':pais' => $c['Pais']]);
            }
        }
    }

    public function seedUsuario(): void
    {
        $defaultHash = password_hash('Password123!', PASSWORD_BCRYPT);
        $now = date('Y-m-d H:i:s');

        $users = [
            ['Codigo_Empleado' => 'EMP-SV-001', 'Nombre' => 'Carlos Administrador SV', 'Usuario' => 'admin_sv', 'Contrasena' => $defaultHash, 'Id_Pais' => 1, 'telefono' => '50370000001', 'rol' => 'Administrador', 'intentos_fallidos' => 0, 'bloqueado' => 0, 'ultimo_fallo' => null, 'ultimo_login' => null, 'creado_en' => $now, 'actualizado_en' => $now],
            ['Codigo_Empleado' => 'EMP-SV-002', 'Nombre' => 'Elena Supervisora SV', 'Usuario' => 'supervisor_sv', 'Contrasena' => $defaultHash, 'Id_Pais' => 1, 'telefono' => '50370000002', 'rol' => 'Supervisor', 'intentos_fallidos' => 0, 'bloqueado' => 0, 'ultimo_fallo' => null, 'ultimo_login' => null, 'creado_en' => $now, 'actualizado_en' => $now],
            ['Codigo_Empleado' => 'EMP-SV-003', 'Nombre' => 'Mario Asesor SV', 'Usuario' => 'asesor_sv', 'Contrasena' => $defaultHash, 'Id_Pais' => 1, 'telefono' => '50370000003', 'rol' => 'Asesor', 'intentos_fallidos' => 0, 'bloqueado' => 0, 'ultimo_fallo' => null, 'ultimo_login' => null, 'creado_en' => $now, 'actualizado_en' => $now],

            ['Codigo_Empleado' => 'EMP-GT-001', 'Nombre' => 'Ana Administradora GT', 'Usuario' => 'admin_gt', 'Contrasena' => $defaultHash, 'Id_Pais' => 2, 'telefono' => '50250000001', 'rol' => 'Administrador', 'intentos_fallidos' => 0, 'bloqueado' => 0, 'ultimo_fallo' => null, 'ultimo_login' => null, 'creado_en' => $now, 'actualizado_en' => $now],
            ['Codigo_Empleado' => 'EMP-GT-002', 'Nombre' => 'Jorge Supervisor GT', 'Usuario' => 'supervisor_gt', 'Contrasena' => $defaultHash, 'Id_Pais' => 2, 'telefono' => '50250000002', 'rol' => 'Supervisor', 'intentos_fallidos' => 0, 'bloqueado' => 0, 'ultimo_fallo' => null, 'ultimo_login' => null, 'creado_en' => $now, 'actualizado_en' => $now],
            ['Codigo_Empleado' => 'EMP-GT-003', 'Nombre' => 'Lucia Asesora GT', 'Usuario' => 'asesor_gt', 'Contrasena' => $defaultHash, 'Id_Pais' => 2, 'telefono' => '50250000003', 'rol' => 'Asesor', 'intentos_fallidos' => 0, 'bloqueado' => 0, 'ultimo_fallo' => null, 'ultimo_login' => null, 'creado_en' => $now, 'actualizado_en' => $now],

            ['Codigo_Empleado' => 'EMP-HN-001', 'Nombre' => 'Roberto Administrador HN', 'Usuario' => 'admin_hn', 'Contrasena' => $defaultHash, 'Id_Pais' => 3, 'telefono' => '50490000001', 'rol' => 'Administrador', 'intentos_fallidos' => 0, 'bloqueado' => 0, 'ultimo_fallo' => null, 'ultimo_login' => null, 'creado_en' => $now, 'actualizado_en' => $now],
            ['Codigo_Empleado' => 'EMP-HN-002', 'Nombre' => 'Claudia Supervisora HN', 'Usuario' => 'supervisor_hn', 'Contrasena' => $defaultHash, 'Id_Pais' => 3, 'telefono' => '50490000002', 'rol' => 'Supervisor', 'intentos_fallidos' => 0, 'bloqueado' => 0, 'ultimo_fallo' => null, 'ultimo_login' => null, 'creado_en' => $now, 'actualizado_en' => $now],
            ['Codigo_Empleado' => 'EMP-HN-003', 'Nombre' => 'David Asesor HN', 'Usuario' => 'asesor_hn', 'Contrasena' => $defaultHash, 'Id_Pais' => 3, 'telefono' => '50490000003', 'rol' => 'Asesor', 'intentos_fallidos' => 0, 'bloqueado' => 0, 'ultimo_fallo' => null, 'ultimo_login' => null, 'creado_en' => $now, 'actualizado_en' => $now],

            ['Codigo_Empleado' => 'EMP-LOCKED-01', 'Nombre' => 'Usuario Bloqueado Test', 'Usuario' => 'user_locked', 'Contrasena' => $defaultHash, 'Id_Pais' => 1, 'telefono' => '50379999999', 'rol' => 'Asesor', 'intentos_fallidos' => 3, 'bloqueado' => 1, 'ultimo_fallo' => $now, 'ultimo_login' => null, 'creado_en' => $now, 'actualizado_en' => $now],
            ['Codigo_Empleado' => 'EMP-ATTEMPT-01', 'Nombre' => 'Usuario Dos Fallos Test', 'Usuario' => 'user_2attempts', 'Contrasena' => $defaultHash, 'Id_Pais' => 1, 'telefono' => '50378888888', 'rol' => 'Asesor', 'intentos_fallidos' => 2, 'bloqueado' => 0, 'ultimo_fallo' => $now, 'ultimo_login' => null, 'creado_en' => $now, 'actualizado_en' => $now],
        ];

        $selectStmt = $this->pdo->prepare("SELECT Codigo_Empleado FROM Usuario WHERE Codigo_Empleado = :ce");
        $updateStmt = $this->pdo->prepare("
            UPDATE Usuario SET
                Nombre = :nombre, Usuario = :usuario, Contrasena = :pass, Id_Pais = :id_pais,
                telefono = :tel, rol = :rol, intentos_fallidos = :intentos, bloqueado = :bloq,
                ultimo_fallo = :fallo, ultimo_login = :login, actualizado_en = :actualizado
            WHERE Codigo_Empleado = :ce
        ");
        $insertStmt = $this->pdo->prepare("
            INSERT INTO Usuario (
                Codigo_Empleado, Nombre, Usuario, Contrasena, Id_Pais, telefono, rol,
                intentos_fallidos, bloqueado, ultimo_fallo, ultimo_login, creado_en, actualizado_en
            ) VALUES (
                :ce, :nombre, :usuario, :pass, :id_pais, :tel, :rol,
                :intentos, :bloq, :fallo, :login, :creado, :actualizado
            )
        ");

        foreach ($users as $u) {
            $selectStmt->execute([':ce' => $u['Codigo_Empleado']]);
            $params = [
                ':ce'          => $u['Codigo_Empleado'],
                ':nombre'      => $u['Nombre'],
                ':usuario'     => $u['Usuario'],
                ':pass'        => $u['Contrasena'],
                ':id_pais'     => $u['Id_Pais'],
                ':tel'         => $u['telefono'],
                ':rol'         => $u['rol'],
                ':intentos'    => $u['intentos_fallidos'],
                ':bloq'        => $u['bloqueado'],
                ':fallo'       => $u['ultimo_fallo'],
                ':login'       => $u['ultimo_login'],
                ':actualizado' => $u['actualizado_en'],
            ];

            if ($selectStmt->fetch()) {
                $updateStmt->execute($params);
            } else {
                $params[':creado'] = $u['creado_en'];
                $insertStmt->execute($params);
            }
        }
    }

    public function findByUsernameWithCountry(string $username): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT Usuario.*, Pais.Pais as pais_nombre
            FROM Usuario
            JOIN Pais ON Pais.Id_pais = Usuario.Id_Pais
            WHERE Usuario.Usuario = :user
            LIMIT 1
        ");
        $stmt->execute([':user' => $username]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function recordFailedAttempt(string $codigoEmpleado, int $currentAttempts): int
    {
        $newAttempts = max(0, $currentAttempts) + 1;
        $isLocked = ($newAttempts >= 3) ? 1 : 0;
        $now = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("
            UPDATE Usuario
            SET intentos_fallidos = :intentos, bloqueado = :bloq, ultimo_fallo = :fallo
            WHERE Codigo_Empleado = :ce
        ");
        $stmt->execute([
            ':intentos' => $newAttempts,
            ':bloq'     => $isLocked,
            ':fallo'    => $now,
            ':ce'       => $codigoEmpleado,
        ]);

        return $newAttempts;
    }

    public function resetFailedAttempts(string $codigoEmpleado): bool
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare("
            UPDATE Usuario
            SET intentos_fallidos = 0, bloqueado = 0, ultimo_login = :login
            WHERE Codigo_Empleado = :ce
        ");
        return $stmt->execute([':login' => $now, ':ce' => $codigoEmpleado]);
    }

    public function unlockAccount(string $codigoEmpleado): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE Usuario
            SET intentos_fallidos = 0, bloqueado = 0
            WHERE Codigo_Empleado = :ce
        ");
        return $stmt->execute([':ce' => $codigoEmpleado]);
    }

    public function runAll(): bool
    {
        echo "=======================================================\n";
        echo "STANDALONE EMPIRICAL STRESS TEST (SQLITE PDO)\n";
        echo "=======================================================\n\n";

        // 1. Migration up -> down -> up
        echo "--- 1. Migration Idempotency Test ---\n";
        $this->createSchema();
        $this->assert($this->tableExists('Pais'), "Table 'Pais' created");
        $this->assert($this->tableExists('Usuario'), "Table 'Usuario' created");

        $this->dropSchema();
        $this->assert(!$this->tableExists('Usuario'), "Table 'Usuario' dropped cleanly");
        $this->assert(!$this->tableExists('Pais'), "Table 'Pais' dropped cleanly");

        $this->createSchema();
        $this->assert($this->tableExists('Pais'), "Table 'Pais' re-created cleanly");
        $this->assert($this->tableExists('Usuario'), "Table 'Usuario' re-created cleanly");

        // 2. Seeder duplicate execution
        echo "\n--- 2. Seeder Idempotency Stress Test ---\n";
        $this->seedPais();
        $this->seedUsuario();
        $cntP1 = (int)$this->pdo->query("SELECT COUNT(*) FROM Pais")->fetchColumn();
        $cntU1 = (int)$this->pdo->query("SELECT COUNT(*) FROM Usuario")->fetchColumn();
        $this->assert($cntP1 === 3, "Pais count after run 1 is 3");
        $this->assert($cntU1 === 11, "Usuario count after run 1 is 11");

        // Re-run seeders
        $this->seedPais();
        $this->seedUsuario();
        $cntP2 = (int)$this->pdo->query("SELECT COUNT(*) FROM Pais")->fetchColumn();
        $cntU2 = (int)$this->pdo->query("SELECT COUNT(*) FROM Usuario")->fetchColumn();
        $this->assert($cntP2 === 3, "Pais count after run 2 remains 3 (no duplication)");
        $this->assert($cntU2 === 11, "Usuario count after run 2 remains 11 (no duplication)");

        // 3. Cross-Country Boundary Stress Test
        echo "\n--- 3. Cross-Country Boundary Stress Test ---\n";
        $expected = [
            'admin_sv'       => [1, 'El Salvador', 'Administrador'],
            'supervisor_sv'  => [1, 'El Salvador', 'Supervisor'],
            'asesor_sv'      => [1, 'El Salvador', 'Asesor'],
            'user_locked'    => [1, 'El Salvador', 'Asesor'],
            'user_2attempts' => [1, 'El Salvador', 'Asesor'],
            'admin_gt'       => [2, 'Guatemala', 'Administrador'],
            'supervisor_gt'  => [2, 'Guatemala', 'Supervisor'],
            'asesor_gt'      => [2, 'Guatemala', 'Asesor'],
            'admin_hn'       => [3, 'Honduras', 'Administrador'],
            'supervisor_hn'  => [3, 'Honduras', 'Supervisor'],
            'asesor_hn'      => [3, 'Honduras', 'Asesor'],
        ];

        foreach ($expected as $user => [$expPaisId, $expPaisNom, $expRol]) {
            $row = $this->findByUsernameWithCountry($user);
            $this->assert($row !== null, "User '{$user}' retrieved");
            if ($row) {
                $this->assert((int)$row['Id_Pais'] === $expPaisId, "User '{$user}' Id_Pais is {$expPaisId}");
                $this->assert($row['pais_nombre'] === $expPaisNom, "User '{$user}' pais_nombre is '{$expPaisNom}'");
                $this->assert($row['rol'] === $expRol, "User '{$user}' rol is '{$expRol}'");
            }
        }

        // Test Foreign Key Enforcement
        $fkFailed = false;
        try {
            $this->pdo->exec("
                INSERT INTO Usuario (
                    Codigo_Empleado, Nombre, Usuario, Contrasena, Id_Pais, rol
                ) VALUES (
                    'EMP-INVALID-FK', 'FK Test User', 'invalid_fk_user', 'hash', 999, 'Asesor'
                )
            ");
        } catch (\PDOException $e) {
            $fkFailed = true;
        }
        $this->assert($fkFailed, "Foreign key constraint blocks insert with invalid Id_Pais 999");

        // 4. Password Hash Robustness
        echo "\n--- 4. Password Hash Robustness ---\n";
        foreach (array_keys($expected) as $user) {
            $row = $this->findByUsernameWithCountry($user);
            $hash = $row['Contrasena'];
            $this->assert(password_verify('Password123!', $hash), "User '{$user}' accepts correct 'Password123!'");
            $this->assert(!password_verify('Password123', $hash), "User '{$user}' rejects missing symbol");
            $this->assert(!password_verify('password123!', $hash), "User '{$user}' rejects lowercase");
            $this->assert(!password_verify('', $hash), "User '{$user}' rejects empty password");
            $this->assert(!password_verify('Password123! ', $hash), "User '{$user}' rejects trailing space");
        }

        // 5. High Volume Attempt Cycle & Lockout
        echo "\n--- 5. High Volume Attempt Cycle & Lockout ---\n";
        $testCode = 'EMP-SV-003';
        $userRow = $this->findByUsernameWithCountry('asesor_sv');
        $this->assert((int)$userRow['intentos_fallidos'] === 0, "Initial attempts is 0");
        $this->assert((int)$userRow['bloqueado'] === 0, "Initial locked is 0");

        $a1 = $this->recordFailedAttempt($testCode, 0);
        $r1 = $this->findByUsernameWithCountry('asesor_sv');
        $this->assert($a1 === 1 && (int)$r1['intentos_fallidos'] === 1 && (int)$r1['bloqueado'] === 0, "Attempt 1: count 1, unlocked");

        $a2 = $this->recordFailedAttempt($testCode, 1);
        $r2 = $this->findByUsernameWithCountry('asesor_sv');
        $this->assert($a2 === 2 && (int)$r2['intentos_fallidos'] === 2 && (int)$r2['bloqueado'] === 0, "Attempt 2: count 2, unlocked");

        $a3 = $this->recordFailedAttempt($testCode, 2);
        $r3 = $this->findByUsernameWithCountry('asesor_sv');
        $this->assert($a3 === 3 && (int)$r3['intentos_fallidos'] === 3 && (int)$r3['bloqueado'] === 1, "Attempt 3: count 3, LOCKED (R2)");

        $a4 = $this->recordFailedAttempt($testCode, 3);
        $r4 = $this->findByUsernameWithCountry('asesor_sv');
        $this->assert($a4 === 4 && (int)$r4['intentos_fallidos'] === 4 && (int)$r4['bloqueado'] === 1, "Attempt 4: count 4, PERSISTS LOCKED");

        $a5 = $this->recordFailedAttempt($testCode, 4);
        $r5 = $this->findByUsernameWithCountry('asesor_sv');
        $this->assert($a5 === 5 && (int)$r5['intentos_fallidos'] === 5 && (int)$r5['bloqueado'] === 1, "Attempt 5: count 5, PERSISTS LOCKED");

        // Unlock
        $unlocked = $this->unlockAccount($testCode);
        $rUnl = $this->findByUsernameWithCountry('asesor_sv');
        $this->assert($unlocked && (int)$rUnl['intentos_fallidos'] === 0 && (int)$rUnl['bloqueado'] === 0, "unlockAccount() resets attempts to 0 and unlocks");

        // Reset on valid login
        $res = $this->resetFailedAttempts($testCode);
        $rRes = $this->findByUsernameWithCountry('asesor_sv');
        $this->assert($res && (int)$rRes['intentos_fallidos'] === 0 && (int)$rRes['bloqueado'] === 0 && !empty($rRes['ultimo_login']), "resetFailedAttempts() sets ultimo_login");

        echo "\n=======================================================\n";
        echo "TOTAL ASSERTIONS: " . ($this->passed + $this->failed) . " | PASSED: {$this->passed} | FAILED: {$this->failed}\n";
        echo "=======================================================\n";

        return $this->failed === 0;
    }
}

$tester = new StandaloneStressTester();
$ok = $tester->runAll();
exit($ok ? 0 : 1);
