<?php

namespace App\Agents\ChallengerM1;

use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Adversarial Unit Tests for UserModel and Auth Tables
 * Authored by Challenger M1 (challenger_m1_1)
 *
 * Verifies edge cases, boundary conditions, SQL injection immunity,
 * and schema constraint behaviors against live CodeIgniter Model.
 */
final class UserModelAdversarialTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $seed        = \App\Database\Seeds\AuthSeeder::class;

    protected UserModel $userModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userModel = new UserModel();
    }

    /**
     * 1.1 Test negative attempt count input (-1 and -99) normalizes safely to 1
     */
    public function testRecordFailedAttemptNegativeInputNormalizesToOne(): void
    {
        $newAttempts = $this->userModel->recordFailedAttempt('EMP-SV-001', -1);
        $this->assertSame(1, $newAttempts);

        $user = $this->userModel->find('EMP-SV-001');
        $this->assertSame(1, (int) $user['intentos_fallidos']);
        $this->assertSame(0, (int) $user['bloqueado']);

        // Extreme negative
        $extremeAttempts = $this->userModel->recordFailedAttempt('EMP-SV-001', -999);
        $this->assertSame(1, $extremeAttempts);
    }

    /**
     * 1.2 Test boundary condition transition exactly at attempt 2 -> 3
     */
    public function testRecordFailedAttemptExactBoundaryLocksAccount(): void
    {
        // Transition from 2 to 3
        $newAttempts = $this->userModel->recordFailedAttempt('EMP-ATTEMPT-01', 2);
        $this->assertSame(3, $newAttempts);

        $user = $this->userModel->find('EMP-ATTEMPT-01');
        $this->assertSame(3, (int) $user['intentos_fallidos']);
        $this->assertSame(1, (int) $user['bloqueado']);
        $this->assertNotNull($user['ultimo_fallo']);
    }

    /**
     * 1.3 Test attempt count beyond 3 maintains locked state
     */
    public function testRecordFailedAttemptOverflowRemainsLocked(): void
    {
        $newAttempts = $this->userModel->recordFailedAttempt('EMP-LOCKED-01', 3);
        $this->assertSame(4, $newAttempts);

        $user = $this->userModel->find('EMP-LOCKED-01');
        $this->assertSame(4, (int) $user['intentos_fallidos']);
        $this->assertSame(1, (int) $user['bloqueado']);

        // High count
        $highCount = $this->userModel->recordFailedAttempt('EMP-LOCKED-01', 50);
        $this->assertSame(51, $highCount);

        $userHigh = $this->userModel->find('EMP-LOCKED-01');
        $this->assertSame(1, (int) $userHigh['bloqueado']);
    }

    /**
     * 1.4 Test non-existent Codigo_Empleado does not crash
     */
    public function testRecordFailedAttemptNonExistentUser(): void
    {
        $result = $this->userModel->recordFailedAttempt('EMP-NON-EXISTENT', 0);
        $this->assertSame(1, $result);

        $user = $this->userModel->find('EMP-NON-EXISTENT');
        $this->assertNull($user);
    }

    /**
     * 2.1 Test resetFailedAttempts resets counter and lockout status
     */
    public function testResetFailedAttemptsClearsLockoutAndSetsLogin(): void
    {
        // First lock
        $this->userModel->recordFailedAttempt('EMP-SV-001', 2);
        $locked = $this->userModel->find('EMP-SV-001');
        $this->assertSame(1, (int) $locked['bloqueado']);

        // Reset
        $resetResult = $this->userModel->resetFailedAttempts('EMP-SV-001');
        $this->assertTrue($resetResult);

        $unlocked = $this->userModel->find('EMP-SV-001');
        $this->assertSame(0, (int) $unlocked['intentos_fallidos']);
        $this->assertSame(0, (int) $unlocked['bloqueado']);
        $this->assertNotNull($unlocked['ultimo_login']);
    }

    /**
     * 2.2 Test unlockAccount clears lockout and failed attempts leaving ultimo_login intact
     */
    public function testUnlockAccountLeavesUltimoLoginUntouched(): void
    {
        $userBefore = $this->userModel->find('EMP-LOCKED-01');
        $priorLogin = $userBefore['ultimo_login'];

        $result = $this->userModel->unlockAccount('EMP-LOCKED-01');
        $this->assertTrue($result);

        $userAfter = $this->userModel->find('EMP-LOCKED-01');
        $this->assertSame(0, (int) $userAfter['intentos_fallidos']);
        $this->assertSame(0, (int) $userAfter['bloqueado']);
        $this->assertSame($priorLogin, $userAfter['ultimo_login']);
    }

    /**
     * 3.1 Test SQL injection payloads in findByUsernameWithCountry
     */
    public function testFindByUsernameWithCountrySQLInjectionImmunity(): void
    {
        $payloads = [
            "' OR '1'='1",
            "admin' --",
            "admin_sv' /*",
            "' UNION SELECT 1,2,3,4,5,6,7,8,9,10,11,12,13,14 --",
            "'; DROP TABLE Usuario; --",
            "\" OR \"1\"=\"1",
            "1' OR '1'='1' UNION SELECT NULL, NULL, NULL --",
        ];

        foreach ($payloads as $payload) {
            $result = $this->userModel->findByUsernameWithCountry($payload);
            $this->assertNull($result, "Failed to reject SQL injection payload: {$payload}");
        }
    }

    /**
     * 3.2 Test empty and whitespace usernames
     */
    public function testFindByUsernameWithCountryWhitespaceAndEmpty(): void
    {
        $this->assertNull($this->userModel->findByUsernameWithCountry(''));
        $this->assertNull($this->userModel->findByUsernameWithCountry(' '));
        $this->assertNull($this->userModel->findByUsernameWithCountry(' admin_sv '));
        $this->assertNull($this->userModel->findByUsernameWithCountry("admin_sv\0"));
    }

    /**
     * 4.1 Schema foreign key constraint enforcement
     */
    public function testSchemaForeignKeyConstraintRejection(): void
    {
        $db = \Config\Database::connect('tests');

        $this->expectException(\Throwable::class);
        $db->table('Usuario')->insert([
            'Codigo_Empleado' => 'EMP-FK-ERR',
            'Nombre'          => 'Invalid FK Test',
            'Usuario'         => 'invalid_fk_user',
            'Contrasena'      => 'hash',
            'Id_Pais'         => 9999, // Non-existent country ID
            'rol'             => 'Asesor',
        ]);
    }

    /**
     * 4.2 Schema unique constraint on Usuario
     */
    public function testSchemaDuplicateUsernameRejection(): void
    {
        $db = \Config\Database::connect('tests');

        $this->expectException(\Throwable::class);
        $db->table('Usuario')->insert([
            'Codigo_Empleado' => 'EMP-DUP-USER',
            'Nombre'          => 'Duplicate Username Test',
            'Usuario'         => 'admin_sv', // Already exists in seed
            'Contrasena'      => 'hash',
            'Id_Pais'         => 1,
            'rol'             => 'Asesor',
        ]);
    }
}
