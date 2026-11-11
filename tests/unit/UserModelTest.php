<?php

namespace Tests\Unit;

use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Unit Test Suite for App\Models\UserModel
 *
 * Verifies UserModel methods, security attempt management, lockout rules,
 * country joins, and mass assignment protections using SQLite :memory:.
 *
 * @internal
 */
final class UserModelTest extends CIUnitTestCase
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
     * Test model properties and default configurations
     */
    public function testModelProperties(): void
    {
        $this->assertSame('Usuario', $this->userModel->getTable());
        $this->assertSame('Codigo_Empleado', $this->userModel->primaryKey);
        $this->assertSame('array', $this->userModel->returnType);
        $this->assertContains('intentos_fallidos', $this->userModel->allowedFields);
        $this->assertContains('bloqueado', $this->userModel->allowedFields);
        $this->assertContains('ultimo_fallo', $this->userModel->allowedFields);
        $this->assertContains('ultimo_login', $this->userModel->allowedFields);
    }

    /**
     * Test findByUsernameWithCountry returns complete user record with joined country name
     */
    public function testFindByUsernameWithCountrySuccess(): void
    {
        $user = $this->userModel->findByUsernameWithCountry('admin_sv');

        $this->assertNotNull($user);
        $this->assertSame('admin_sv', $user['Usuario']);
        $this->assertSame('EMP-SV-001', $user['Codigo_Empleado']);
        $this->assertSame('Administrador', $user['rol']);
        $this->assertSame(1, (int) $user['Id_Pais']);
        $this->assertSame('El Salvador', $user['pais_nombre']);
        $this->assertArrayHasKey('Contrasena', $user);
        $this->assertArrayHasKey('intentos_fallidos', $user);
        $this->assertArrayHasKey('bloqueado', $user);
    }

    /**
     * Test findByUsernameWithCountry returns null for non-existent username
     */
    public function testFindByUsernameWithCountryNotFound(): void
    {
        $user = $this->userModel->findByUsernameWithCountry('nonexistent_user_xyz');

        $this->assertNull($user);
    }

    /**
     * Test recordFailedAttempt increments from 0 to 1 without locking
     */
    public function testRecordFailedAttemptFirst(): void
    {
        $newAttempts = $this->userModel->recordFailedAttempt('EMP-SV-001', 0);

        $this->assertSame(1, $newAttempts);

        $user = $this->userModel->find('EMP-SV-001');
        $this->assertSame(1, (int) $user['intentos_fallidos']);
        $this->assertSame(0, (int) $user['bloqueado']);
        $this->assertNotNull($user['ultimo_fallo']);
    }

    /**
     * Test recordFailedAttempt increments from 1 to 2 without locking
     */
    public function testRecordFailedAttemptSecond(): void
    {
        $newAttempts = $this->userModel->recordFailedAttempt('EMP-SV-001', 1);

        $this->assertSame(2, $newAttempts);

        $user = $this->userModel->find('EMP-SV-001');
        $this->assertSame(2, (int) $user['intentos_fallidos']);
        $this->assertSame(0, (int) $user['bloqueado']);
    }

    /**
     * Test recordFailedAttempt locks account on 3rd failure (R2 compliance)
     */
    public function testRecordFailedAttemptThirdLocksAccount(): void
    {
        $newAttempts = $this->userModel->recordFailedAttempt('EMP-SV-001', 2);

        $this->assertSame(3, $newAttempts);

        $user = $this->userModel->find('EMP-SV-001');
        $this->assertSame(3, (int) $user['intentos_fallidos']);
        $this->assertSame(1, (int) $user['bloqueado']);
        $this->assertNotNull($user['ultimo_fallo']);
    }

    /**
     * Test recordFailedAttempt beyond 3 keeps account locked
     */
    public function testRecordFailedAttemptBeyondThree(): void
    {
        $newAttempts = $this->userModel->recordFailedAttempt('EMP-SV-001', 3);

        $this->assertSame(4, $newAttempts);

        $user = $this->userModel->find('EMP-SV-001');
        $this->assertSame(4, (int) $user['intentos_fallidos']);
        $this->assertSame(1, (int) $user['bloqueado']);
    }

    /**
     * Test resetFailedAttempts resets counter and lockout status and updates ultimo_login
     */
    public function testResetFailedAttempts(): void
    {
        // First increment attempts
        $this->userModel->recordFailedAttempt('EMP-SV-001', 0);
        $this->userModel->recordFailedAttempt('EMP-SV-001', 1);

        $userBefore = $this->userModel->find('EMP-SV-001');
        $this->assertSame(2, (int) $userBefore['intentos_fallidos']);

        // Reset
        $result = $this->userModel->resetFailedAttempts('EMP-SV-001');

        $this->assertTrue($result);

        $userAfter = $this->userModel->find('EMP-SV-001');
        $this->assertSame(0, (int) $userAfter['intentos_fallidos']);
        $this->assertSame(0, (int) $userAfter['bloqueado']);
        $this->assertNotNull($userAfter['ultimo_login']);
    }

    /**
     * Test unlockAccount clears lockout and failed attempts without touching ultimo_login
     */
    public function testUnlockAccount(): void
    {
        // Use pre-locked user from seeder
        $lockedUser = $this->userModel->find('EMP-LOCKED-01');
        $this->assertSame(1, (int) $lockedUser['bloqueado']);
        $this->assertSame(3, (int) $lockedUser['intentos_fallidos']);
        $loginBefore = $lockedUser['ultimo_login'];

        $result = $this->userModel->unlockAccount('EMP-LOCKED-01');

        $this->assertTrue($result);

        $unlockedUser = $this->userModel->find('EMP-LOCKED-01');
        $this->assertSame(0, (int) $unlockedUser['bloqueado']);
        $this->assertSame(0, (int) $unlockedUser['intentos_fallidos']);
        $this->assertSame($loginBefore, $unlockedUser['ultimo_login']);
    }

    /**
     * Test mass assignment protection rejects unexpected fields
     */
    public function testMassAssignmentProtection(): void
    {
        $this->userModel->update('EMP-SV-001', [
            'unauthorized_column' => 'malicious_payload',
            'intentos_fallidos'   => 0,
        ]);

        $user = $this->userModel->find('EMP-SV-001');
        $this->assertArrayNotHasKey('unauthorized_column', $user);
    }
}
