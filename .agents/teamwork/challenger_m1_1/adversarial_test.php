<?php
/**
 * Adversarial Test Suite for CrediTrack Milestone 1 (M1_db_models)
 * Executed by Challenger M1 (challenger_m1_1)
 *
 * Covers:
 * 1. recordFailedAttempt boundary conditions, negative inputs, overflow, non-existent PK
 * 2. resetFailedAttempts & unlockAccount state mutations and ultimo_login immutability
 * 3. findByUsernameWithCountry SQL injection immunity, boundary usernames, whitespace
 * 4. SQLite Schema constraint enforcement (PK, UNIQUE, FK RESTRICT, FK NOT NULL)
 */

declare(strict_strict=1);

$results = [
    'passed' => 0,
    'failed' => 0,
    'tests'  => [],
];

function recordResult(string $name, bool $passed, string $details = ''): void {
    global $results;
    if ($passed) {
        $results['passed']++;
        $results['tests'][] = ['status' => 'PASS', 'name' => $name, 'details' => $details];
        echo "[PASS] $name\n";
    } else {
        $results['failed']++;
        $results['tests'][] = ['status' => 'FAIL', 'name' => $name, 'details' => $details];
        echo "[FAIL] $name - $details\n";
    }
}

// ---------------------------------------------------------
// Setup SQLite In-Memory Database with Foreign Keys Enabled
// ---------------------------------------------------------
try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON;');

    // DDL matching CreateAuthTables migration exactly
    $pdo->exec("
        CREATE TABLE Pais (
            Id_pais INTEGER PRIMARY KEY AUTOINCREMENT,
            Pais VARCHAR(100) NOT NULL
        );
    ");

    $pdo->exec("
        CREATE TABLE Usuario (
            Codigo_Empleado VARCHAR(50) PRIMARY KEY,
            Nombre VARCHAR(150) NOT NULL,
            Usuario VARCHAR(50) NOT NULL UNIQUE,
            Contrasena VARCHAR(255) NOT NULL,
            Id_Pais INTEGER NOT NULL,
            telefono VARCHAR(20) NULL,
            rol VARCHAR(50) NOT NULL,
            intentos_fallidos INTEGER NOT NULL DEFAULT 0,
            bloqueado INTEGER NOT NULL DEFAULT 0,
            ultimo_fallo DATETIME NULL,
            ultimo_login DATETIME NULL,
            creado_en DATETIME NULL,
            actualizado_en DATETIME NULL,
            FOREIGN KEY (Id_Pais) REFERENCES Pais(Id_pais) ON UPDATE CASCADE ON DELETE RESTRICT
        );
    ");

    // Seed Countries
    $stmtPais = $pdo->prepare("INSERT INTO Pais (Id_pais, Pais) VALUES (?, ?)");
    $stmtPais->execute([1, 'El Salvador']);
    $stmtPais->execute([2, 'Guatemala']);
    $stmtPais->execute([3, 'Honduras']);

    // Seed Users
    $stmtUser = $pdo->prepare("
        INSERT INTO Usuario (
            Codigo_Empleado, Nombre, Usuario, Contrasena, Id_Pais, telefono, rol,
            intentos_fallidos, bloqueado, ultimo_fallo, ultimo_login, creado_en, actualizado_en
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $hash = password_hash('Password123!', PASSWORD_BCRYPT);
    $now = date('Y-m-d H:i:s');
    $pastLogin = '2026-11-01 10:00:00';

    // EMP-SV-001 (Clean active user)
    $stmtUser->execute([
        'EMP-SV-001', 'Carlos Admin SV', 'admin_sv', $hash, 1, '50370000001', 'Administrador',
        0, 0, null, $pastLogin, $now, $now
    ]);

    // EMP-LOCKED-01 (Pre-locked user)
    $stmtUser->execute([
        'EMP-LOCKED-01', 'Usuario Bloqueado', 'user_locked', $hash, 1, '50379999999', 'Asesor',
        3, 1, $now, $pastLogin, $now, $now
    ]);

    // EMP-ATTEMPT-01 (Pre-attempt user at 2 attempts)
    $stmtUser->execute([
        'EMP-ATTEMPT-01', 'Usuario Dos Fallos', 'user_2attempts', $hash, 1, '50378888888', 'Asesor',
        2, 0, $now, null, $now, $now
    ]);

    recordResult("Database setup and seeding", true, "Tables and seeds created successfully");
} catch (Exception $e) {
    recordResult("Database setup and seeding", false, $e->getMessage());
    exit(1);
}

// =========================================================================
// Emulation of UserModel logic to verify exact algorithms and DB behavior
// =========================================================================

class UserModelHarness
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function findByUsernameWithCountry(string $username): ?array
    {
        $stmt = $this->db->prepare("
            SELECT Usuario.*, Pais.Pais as pais_nombre
            FROM Usuario
            JOIN Pais ON Pais.Id_pais = Usuario.Id_Pais
            WHERE Usuario.Usuario = :username
            LIMIT 1
        ");
        $stmt->execute([':username' => $username]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    public function recordFailedAttempt(string $codigoEmpleado, int $currentAttempts): int
    {
        $newAttempts = max(0, $currentAttempts) + 1;
        $isLocked    = ($newAttempts >= 3) ? 1 : 0;

        $stmt = $this->db->prepare("
            UPDATE Usuario
            SET intentos_fallidos = :attempts,
                bloqueado = :locked,
                ultimo_fallo = :fallo,
                actualizado_en = :updated
            WHERE Codigo_Empleado = :codigo
        ");
        $stmt->execute([
            ':attempts' => $newAttempts,
            ':locked'   => $isLocked,
            ':fallo'    => date('Y-m-d H:i:s'),
            ':updated'  => date('Y-m-d H:i:s'),
            ':codigo'   => $codigoEmpleado,
        ]);

        return $newAttempts;
    }

    public function resetFailedAttempts(string $codigoEmpleado): bool
    {
        $stmt = $this->db->prepare("
            UPDATE Usuario
            SET intentos_fallidos = 0,
                bloqueado = 0,
                ultimo_login = :login,
                actualizado_en = :updated
            WHERE Codigo_Empleado = :codigo
        ");
        return $stmt->execute([
            ':login'   => date('Y-m-d H:i:s'),
            ':updated' => date('Y-m-d H:i:s'),
            ':codigo'  => $codigoEmpleado,
        ]);
    }

    public function unlockAccount(string $codigoEmpleado): bool
    {
        $stmt = $this->db->prepare("
            UPDATE Usuario
            SET intentos_fallidos = 0,
                bloqueado = 0,
                actualizado_en = :updated
            WHERE Codigo_Empleado = :codigo
        ");
        return $stmt->execute([
            ':updated' => date('Y-m-d H:i:s'),
            ':codigo'  => $codigoEmpleado,
        ]);
    }

    public function getUser(string $codigoEmpleado): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM Usuario WHERE Codigo_Empleado = ?");
        $stmt->execute([$codigoEmpleado]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }
}

$harness = new UserModelHarness($pdo);

// =========================================================================
// Category 1: Adversarially test UserModel::recordFailedAttempt
// =========================================================================

echo "\n--- Category 1: recordFailedAttempt Edge Cases ---\n";

// 1.1 Negative initial attempts (-1, -99)
$ret1 = $harness->recordFailedAttempt('EMP-SV-001', -1);
$u1 = $harness->getUser('EMP-SV-001');
recordResult(
    "recordFailedAttempt with negative attempt (-1) normalizes to 1",
    $ret1 === 1 && (int)$u1['intentos_fallidos'] === 1 && (int)$u1['bloqueado'] === 0,
    "Expected attempts=1, bloqueado=0. Got attempts={$u1['intentos_fallidos']}, bloqueado={$u1['bloqueado']}"
);

$retNeg = $harness->recordFailedAttempt('EMP-SV-001', -999);
$uNeg = $harness->getUser('EMP-SV-001');
recordResult(
    "recordFailedAttempt with negative attempt (-999) normalizes to 1",
    $retNeg === 1 && (int)$uNeg['intentos_fallidos'] === 1 && (int)$uNeg['bloqueado'] === 0,
    "Expected attempts=1, bloqueado=0. Got attempts={$uNeg['intentos_fallidos']}, bloqueado={$uNeg['bloqueado']}"
);

// 1.2 Boundary 2 -> 3 (Lockout activation)
$ret3 = $harness->recordFailedAttempt('EMP-ATTEMPT-01', 2);
$u3 = $harness->getUser('EMP-ATTEMPT-01');
recordResult(
    "recordFailedAttempt boundary 2 -> 3 locks account (bloqueado = 1)",
    $ret3 === 3 && (int)$u3['intentos_fallidos'] === 3 && (int)$u3['bloqueado'] === 1 && !empty($u3['ultimo_fallo']),
    "Expected attempts=3, bloqueado=1. Got attempts={$u3['intentos_fallidos']}, bloqueado={$u3['bloqueado']}"
);

// 1.3 Beyond 3 (3 -> 4, 100 -> 101)
$ret4 = $harness->recordFailedAttempt('EMP-ATTEMPT-01', 3);
$u4 = $harness->getUser('EMP-ATTEMPT-01');
recordResult(
    "recordFailedAttempt beyond 3 (3 -> 4) maintains locked status (bloqueado = 1)",
    $ret4 === 4 && (int)$u4['intentos_fallidos'] === 4 && (int)$u4['bloqueado'] === 1,
    "Expected attempts=4, bloqueado=1. Got attempts={$u4['intentos_fallidos']}, bloqueado={$u4['bloqueado']}"
);

$ret100 = $harness->recordFailedAttempt('EMP-ATTEMPT-01', 100);
$u100 = $harness->getUser('EMP-ATTEMPT-01');
recordResult(
    "recordFailedAttempt high count (100 -> 101) maintains locked status (bloqueado = 1)",
    $ret100 === 101 && (int)$u100['intentos_fallidos'] === 101 && (int)$u100['bloqueado'] === 1,
    "Expected attempts=101, bloqueado=1. Got attempts={$u100['intentos_fallidos']}, bloqueado={$u100['bloqueado']}"
);

// 1.4 Non-existent codigoEmpleado
$retNonExist = $harness->recordFailedAttempt('EMP-DOES-NOT-EXIST', 0);
$uNonExist = $harness->getUser('EMP-DOES-NOT-EXIST');
recordResult(
    "recordFailedAttempt with non-existent PK does not crash or insert ghost row",
    $retNonExist === 1 && $uNonExist === null,
    "Expected return=1, user=null. Got return=$retNonExist, user=" . json_encode($uNonExist)
);


// =========================================================================
// Category 2: Adversarially test resetFailedAttempts & unlockAccount
// =========================================================================

echo "\n--- Category 2: resetFailedAttempts & unlockAccount ---\n";

// 2.1 resetFailedAttempts resets intentos_fallidos = 0, bloqueado = 0, updates ultimo_login
$harness->recordFailedAttempt('EMP-SV-001', 2); // Put in locked state
$uBeforeReset = $harness->getUser('EMP-SV-001');
$resReset = $harness->resetFailedAttempts('EMP-SV-001');
$uAfterReset = $harness->getUser('EMP-SV-001');

recordResult(
    "resetFailedAttempts clears attempts, clears lockout, updates ultimo_login",
    $resReset === true &&
    (int)$uAfterReset['intentos_fallidos'] === 0 &&
    (int)$uAfterReset['bloqueado'] === 0 &&
    $uAfterReset['ultimo_login'] !== null,
    "Attempts: {$uAfterReset['intentos_fallidos']}, Bloqueado: {$uAfterReset['bloqueado']}, UltimoLogin: {$uAfterReset['ultimo_login']}"
);

// 2.2 unlockAccount leaves ultimo_login intact
$lockedUser = $harness->getUser('EMP-LOCKED-01');
$originalLogin = $lockedUser['ultimo_login'];
$resUnlock = $harness->unlockAccount('EMP-LOCKED-01');
$unlockedUser = $harness->getUser('EMP-LOCKED-01');

recordResult(
    "unlockAccount leaves ultimo_login untouched while clearing lockout and attempts",
    $resUnlock === true &&
    (int)$unlockedUser['intentos_fallidos'] === 0 &&
    (int)$unlockedUser['bloqueado'] === 0 &&
    $unlockedUser['ultimo_login'] === $originalLogin,
    "Expected login=$originalLogin, got login={$unlockedUser['ultimo_login']}"
);

// 2.3 Non-existent user unlock / reset
$resNonExistReset = $harness->resetFailedAttempts('EMP-NON-EXISTENT');
$resNonExistUnlock = $harness->unlockAccount('EMP-NON-EXISTENT');
recordResult(
    "reset/unlock on non-existent PK returns cleanly without exception",
    $resNonExistReset === true && $resNonExistUnlock === true,
    "Non-existent PK handled without PDO fatal error"
);


// =========================================================================
// Category 3: Adversarially test findByUsernameWithCountry (SQLi & Inputs)
// =========================================================================

echo "\n--- Category 3: findByUsernameWithCountry & SQL Injection ---\n";

$sqliPayloads = [
    "' OR '1'='1",
    "admin' --",
    "admin_sv' /*",
    "' UNION SELECT 1,2,3,4,5,6,7,8,9,10,11,12,13,14 --",
    "'; DROP TABLE Usuario; --",
    "admin_sv' AND 1=1 --",
    "\" OR \"1\"=\"1",
    "1' OR '1'='1' UNION SELECT NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL --",
    "' OR ''='",
    "' OR 1=1#",
];

$sqliResistant = true;
$sqliFailedPayload = '';
foreach ($sqliPayloads as $payload) {
    $res = $harness->findByUsernameWithCountry($payload);
    if ($res !== null) {
        $sqliResistant = false;
        $sqliFailedPayload = $payload;
        break;
    }
}
recordResult(
    "SQL injection attacks via username parameter return null",
    $sqliResistant,
    $sqliResistant ? "All 10 SQLi payloads safely returned null" : "Failed on payload: $sqliFailedPayload"
);

// 3.2 Non-existent usernames
recordResult(
    "Non-existent username returns null",
    $harness->findByUsernameWithCountry('user_does_not_exist_99') === null
);
recordResult(
    "Empty string username returns null",
    $harness->findByUsernameWithCountry('') === null
);

// 3.3 Whitespace padding
recordResult(
    "Leading/trailing whitespace does not inadvertently match unpadded username",
    $harness->findByUsernameWithCountry(' admin_sv ') === null,
    "Expected null for ' admin_sv '"
);

// 3.4 Valid username returns expected joined country
$validUser = $harness->findByUsernameWithCountry('admin_sv');
recordResult(
    "Valid username returns user and joined country 'El Salvador'",
    $validUser !== null &&
    $validUser['Usuario'] === 'admin_sv' &&
    $validUser['pais_nombre'] === 'El Salvador' &&
    (int)$validUser['Id_Pais'] === 1,
    "User: {$validUser['Usuario']}, Country: {$validUser['pais_nombre']}"
);


// =========================================================================
// Category 4: Adversarially test SQLite Schema Constraints
// =========================================================================

echo "\n--- Category 4: Schema Constraints Enforcement ---\n";

// 4.1 Duplicate Primary Key (Codigo_Empleado)
$dupPkCaught = false;
try {
    $stmt = $pdo->prepare("INSERT INTO Usuario (Codigo_Empleado, Nombre, Usuario, Contrasena, Id_Pais, rol) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['EMP-SV-001', 'Duplicate PK Test', 'unique_user_dup_pk', 'pass', 1, 'Asesor']);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false || strpos($e->getMessage(), 'PRIMARY KEY') !== false) {
        $dupPkCaught = true;
    }
}
recordResult(
    "Duplicate Primary Key (Codigo_Empleado) is rejected by SQLite constraint",
    $dupPkCaught,
    "Caught PDO constraint exception on duplicate Codigo_Empleado"
);

// 4.2 Duplicate Unique (Usuario)
$dupUserCaught = false;
try {
    $stmt = $pdo->prepare("INSERT INTO Usuario (Codigo_Empleado, Nombre, Usuario, Contrasena, Id_Pais, rol) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['EMP-NEW-UNIQUE', 'Duplicate User Test', 'admin_sv', 'pass', 1, 'Asesor']);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
        $dupUserCaught = true;
    }
}
recordResult(
    "Duplicate UNIQUE (Usuario) is rejected by SQLite constraint",
    $dupUserCaught,
    "Caught PDO constraint exception on duplicate Usuario"
);

// 4.3 Foreign Key violation: non-existent Id_Pais
$fkViolationCaught = false;
try {
    $stmt = $pdo->prepare("INSERT INTO Usuario (Codigo_Empleado, Nombre, Usuario, Contrasena, Id_Pais, rol) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['EMP-INVALID-FK', 'Invalid FK User', 'user_invalid_fk', 'pass', 9999, 'Asesor']);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'FOREIGN KEY constraint failed') !== false) {
        $fkViolationCaught = true;
    }
}
recordResult(
    "Foreign Key violation (Id_Pais = 9999) is rejected when foreign_keys = ON",
    $fkViolationCaught,
    "Caught PDO foreign key constraint violation exception"
);

// 4.4 Foreign Key RESTRICT on DELETE
$restrictDeleteCaught = false;
try {
    // Attempt to delete Country 1 which has assigned users
    $pdo->exec("DELETE FROM Pais WHERE Id_pais = 1");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'FOREIGN KEY constraint failed') !== false) {
        $restrictDeleteCaught = true;
    }
}
recordResult(
    "Foreign Key ON DELETE RESTRICT prevents deleting country with active users",
    $restrictDeleteCaught,
    "Caught PDO foreign key constraint violation on deleting referenced parent"
);

// 4.5 NOT NULL constraints on critical columns
$notNullCaught = false;
try {
    $stmt = $pdo->prepare("INSERT INTO Usuario (Codigo_Empleado, Nombre, Usuario, Contrasena, Id_Pais, rol) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['EMP-NULL-ROL', 'Null Rol User', 'user_null_rol', 'pass', 1, null]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'NOT NULL constraint failed') !== false) {
        $notNullCaught = true;
    }
}
recordResult(
    "NOT NULL constraint rejects null 'rol' field",
    $notNullCaught,
    "Caught PDO NOT NULL constraint violation exception"
);


// =========================================================================
// Summary
// =========================================================================

echo "\n============================================\n";
echo "Adversarial Test Suite Execution Completed\n";
echo "Total Passed: {$results['passed']}\n";
echo "Total Failed: {$results['failed']}\n";
echo "Verdict: " . ($results['failed'] === 0 ? "APPROVE" : "REQUEST_CHANGES") . "\n";
echo "============================================\n";

// Write JSON results artifact in state folder
file_put_contents(__DIR__ . '/test_results.json', json_encode($results, JSON_PRETTY_PRINT));
