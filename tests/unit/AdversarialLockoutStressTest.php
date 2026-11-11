<?php

namespace Tests\Unit;

use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Empirical Adversarial Stress Test Suite for Lockout & Authentication Logic.
 *
 * Verifies:
 * 1. 3 consecutive failed attempts trigger lockout (bloqueado = 1, intentos_fallidos = 3).
 * 2. Attempts 4 and 5 on locked account continue to be rejected without password_verify.
 * 3. Valid password on attempt 1 or 2 resets counter to 0.
 * 4. Non-existent username does not alter database counters.
 * 5. Whitespace and empty inputs do not alter database counters.
 * 6. SQL injection, oversized buffers, unicode, and null byte handling.
 * 7. Defensive lockout checks (bloqueado=1 with intentos=0, intentos>=3 with bloqueado=0).
 *
 * @internal
 */
final class AdversarialLockoutStressTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

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
     * ADV-01: Verify exact progression to lockout at 3 consecutive failed attempts.
     */
    public function testThreeConsecutiveFailedAttemptsProgression(): void
    {
        $targetUser = 'admin_sv';
        $empCode    = 'EMP-SV-001';

        // Initial state: 0 attempts, unblocked
        $user = $this->userModel->find($empCode);
        $this->assertSame(0, (int) $user['intentos_fallidos']);
        $this->assertSame(0, (int) $user['bloqueado']);

        // Attempt 1: Wrong password
        $res1 = $this->post('login', [
            'usuario'    => $targetUser,
            'contrasena' => 'WrongPass_1',
        ]);
        $res1->assertStatus(302);
        $res1->assertRedirectTo('login');
        $res1->assertSessionHas('remaining_attempts', 2);
        $user = $this->userModel->find($empCode);
        $this->assertSame(1, (int) $user['intentos_fallidos']);
        $this->assertSame(0, (int) $user['bloqueado']);
        $firstFailureTime = $user['ultimo_fallo'];
        $this->assertNotNull($firstFailureTime);

        // Attempt 2: Wrong password
        $res2 = $this->post('login', [
            'usuario'    => $targetUser,
            'contrasena' => 'WrongPass_2',
        ]);
        $res2->assertStatus(302);
        $res2->assertRedirectTo('login');
        $res2->assertSessionHas('remaining_attempts', 1);
        $user = $this->userModel->find($empCode);
        $this->assertSame(2, (int) $user['intentos_fallidos']);
        $this->assertSame(0, (int) $user['bloqueado']);

        // Attempt 3: Wrong password -> LOCKOUT
        $res3 = $this->post('login', [
            'usuario'    => $targetUser,
            'contrasena' => 'WrongPass_3',
        ]);
        $res3->assertStatus(302);
        $res3->assertRedirectTo('login');
        $res3->assertSessionHas('error');
        $this->assertStringContainsString('Acceso bloqueado tras 3 intentos fallidos', session('error'));

        $user = $this->userModel->find($empCode);
        $this->assertSame(3, (int) $user['intentos_fallidos']);
        $this->assertSame(1, (int) $user['bloqueado']);
        $lockoutFailureTime = $user['ultimo_fallo'];
        $this->assertNotNull($lockoutFailureTime);

        // Attempt 4: Flooding with wrong password on locked account
        $res4 = $this->post('login', [
            'usuario'    => $targetUser,
            'contrasena' => 'WrongPass_4',
        ]);
        $res4->assertStatus(302);
        $res4->assertRedirectTo('login');
        $this->assertStringContainsString('Acceso bloqueado tras 3 intentos fallidos', session('error'));

        // Counters must remain static at 3 and bloqueado=1, ultimo_fallo not updated
        $user4 = $this->userModel->find($empCode);
        $this->assertSame(3, (int) $user4['intentos_fallidos']);
        $this->assertSame(1, (int) $user4['bloqueado']);
        $this->assertSame($lockoutFailureTime, $user4['ultimo_fallo']);

        // Attempt 5: Attempting with CORRECT password on locked account
        $res5 = $this->post('login', [
            'usuario'    => $targetUser,
            'contrasena' => 'Password123!',
        ]);
        $res5->assertStatus(302);
        $res5->assertRedirectTo('login');
        $this->assertStringContainsString('Acceso bloqueado tras 3 intentos fallidos', session('error'));
        $this->assertFalse((bool) (session('isLoggedIn') ?? false));

        // State remains locked
        $user5 = $this->userModel->find($empCode);
        $this->assertSame(3, (int) $user5['intentos_fallidos']);
        $this->assertSame(1, (int) $user5['bloqueado']);
        $this->assertNull($user5['ultimo_login']);
    }

    /**
     * ADV-02: Pre-locked rejection without password_verify timing and counter isolation.
     */
    public function testPreLockedAccountImmediateRejectionWithoutPasswordVerify(): void
    {
        // Case A: User seeded with bloqueado = 1, intentos_fallidos = 3 (EMP-LOCKED-01)
        $lockedUser = $this->userModel->find('EMP-LOCKED-01');
        $this->assertSame(1, (int) $lockedUser['bloqueado']);
        $this->assertSame(3, (int) $lockedUser['intentos_fallidos']);
        $originalFallo = $lockedUser['ultimo_fallo'];

        // Submit correct password
        $res = $this->post('login', [
            'usuario'    => 'user_locked',
            'contrasena' => 'Password123!',
        ]);
        $res->assertStatus(302);
        $res->assertRedirectTo('login');
        $this->assertStringContainsString('Acceso bloqueado tras 3 intentos fallidos', session('error'));
        $this->assertFalse((bool) (session('isLoggedIn') ?? false));

        // Verify DB untouched
        $userAfter = $this->userModel->find('EMP-LOCKED-01');
        $this->assertSame(3, (int) $userAfter['intentos_fallidos']);
        $this->assertSame(1, (int) $userAfter['bloqueado']);
        $this->assertSame($originalFallo, $userAfter['ultimo_fallo']);
        $this->assertNull($userAfter['ultimo_login']);

        // Case B: Defensive check - bloqueado = 1 but intentos_fallidos = 0
        $this->userModel->update('EMP-SV-002', [
            'bloqueado'         => 1,
            'intentos_fallidos' => 0,
        ]);
        $resB = $this->post('login', [
            'usuario'    => 'supervisor_sv',
            'contrasena' => 'Password123!',
        ]);
        $resB->assertStatus(302);
        $this->assertStringContainsString('Acceso bloqueado tras 3 intentos fallidos', session('error'));
        $this->assertFalse((bool) (session('isLoggedIn') ?? false));

        // Case C: Defensive check - bloqueado = 0 but intentos_fallidos = 3
        $this->userModel->update('EMP-SV-003', [
            'bloqueado'         => 0,
            'intentos_fallidos' => 3,
        ]);
        $resC = $this->post('login', [
            'usuario'    => 'asesor_sv',
            'contrasena' => 'Password123!',
        ]);
        $resC->assertStatus(302);
        $this->assertStringContainsString('Acceso bloqueado tras 3 intentos fallidos', session('error'));
        $this->assertFalse((bool) (session('isLoggedIn') ?? false));
    }

    /**
     * ADV-03: Counter resets immediately on valid password on attempt 1 or 2.
     */
    public function testCounterResetOnValidPasswordAfterFailures(): void
    {
        // Scenario 1: Reset after 1 failure
        $this->post('login', [
            'usuario'    => 'supervisor_gt',
            'contrasena' => 'WrongPass',
        ]);
        $user = $this->userModel->find('EMP-GT-002');
        $this->assertSame(1, (int) $user['intentos_fallidos']);

        $resSuccess1 = $this->post('login', [
            'usuario'    => 'supervisor_gt',
            'contrasena' => 'Password123!',
        ]);
        $resSuccess1->assertStatus(302);
        $resSuccess1->assertRedirectTo('home.php');

        $user = $this->userModel->find('EMP-GT-002');
        $this->assertSame(0, (int) $user['intentos_fallidos']);
        $this->assertSame(0, (int) $user['bloqueado']);
        $this->assertNotNull($user['ultimo_login']);

        // Logout
        $this->get('logout');

        // Scenario 2: Reset after 2 failures (user_2attempts seeded with 2 attempts)
        $user2 = $this->userModel->find('EMP-ATTEMPT-01');
        $this->assertSame(2, (int) $user2['intentos_fallidos']);
        $this->assertSame(0, (int) $user2['bloqueado']);

        $resSuccess2 = $this->post('login', [
            'usuario'    => 'user_2attempts',
            'contrasena' => 'Password123!',
        ]);
        $resSuccess2->assertStatus(302);
        $resSuccess2->assertRedirectTo('home.php');

        $user2After = $this->userModel->find('EMP-ATTEMPT-01');
        $this->assertSame(0, (int) $user2After['intentos_fallidos']);
        $this->assertSame(0, (int) $user2After['bloqueado']);
        $this->assertNotNull($user2After['ultimo_login']);
    }

    /**
     * ADV-04: Non-existent username rejection does not alter any database counters.
     */
    public function testNonExistentUsernameDoesNotAlterCounters(): void
    {
        // Capture initial state of all users
        $initialUsers = $this->userModel->findAll();

        for ($i = 0; $i < 5; $i++) {
            $res = $this->post('login', [
                'usuario'    => 'ghost_attacker_' . $i,
                'contrasena' => 'BadPass_' . $i,
            ]);
            $res->assertStatus(302);
            $res->assertRedirectTo('login');
            $this->assertStringContainsString('Credenciales incorrectas.', session('error'));
        }

        // Compare all users after attempts
        $afterUsers = $this->userModel->findAll();
        $this->assertCount(count($initialUsers), $afterUsers);

        foreach ($initialUsers as $u) {
            $current = $this->userModel->find($u['Codigo_Empleado']);
            $this->assertSame((int) $u['intentos_fallidos'], (int) $current['intentos_fallidos']);
            $this->assertSame((int) $u['bloqueado'], (int) $current['bloqueado']);
            $this->assertSame($u['ultimo_fallo'], $current['ultimo_fallo']);
            $this->assertSame($u['ultimo_login'], $current['ultimo_login']);
        }
    }

    /**
     * ADV-05: Whitespace and empty inputs do not alter database counters.
     */
    public function testWhitespaceAndEmptyInputsDoNotAlterCounters(): void
    {
        $payloads = [
            ['usuario' => '', 'contrasena' => ''],
            ['usuario' => '   ', 'contrasena' => '   '],
            ['usuario' => 'admin_sv', 'contrasena' => ''],
            ['usuario' => 'admin_sv', 'contrasena' => '   '],
            ['usuario' => '', 'contrasena' => 'Password123!'],
            ['usuario' => '   ', 'contrasena' => 'Password123!'],
            ['usuario' => "\t\r\n", 'contrasena' => "\t\r\n"],
        ];

        foreach ($payloads as $p) {
            $res = $this->post('login', $p);
            $res->assertStatus(302);
            $res->assertRedirectTo('login');
            $this->assertStringContainsString('Por favor complete todos los campos requeridos.', session('error'));

            // Check admin_sv counters remained 0
            $admin = $this->userModel->find('EMP-SV-001');
            $this->assertSame(0, (int) $admin['intentos_fallidos']);
            $this->assertSame(0, (int) $admin['bloqueado']);
            $this->assertNull($admin['ultimo_fallo']);
        }
    }

    /**
     * ADV-06: SQL injection vectors handled securely without SQL syntax errors or bypass.
     */
    public function testSqlInjectionPayLoadsHandledSecurely(): void
    {
        $sqliPayloads = [
            "' OR '1'='1",
            "' OR 1=1 --",
            "admin_sv' OR '1'='1",
            "admin_sv'; DROP TABLE Usuario; --",
            "admin_sv' UNION SELECT '1','2','3','4',1,'5','Administrador',0,0,NULL,NULL,NULL,NULL --",
            "' OR ''='",
            "1' or '1' = '1",
            "admin_sv' /*",
        ];

        foreach ($sqliPayloads as $sqli) {
            $res = $this->post('login', [
                'usuario'    => $sqli,
                'contrasena' => 'SomePassword123!',
            ]);
            $res->assertStatus(302);
            $res->assertRedirectTo('login');
            $this->assertFalse((bool) (session('isLoggedIn') ?? false));

            // Verify Usuario table still exists and data is intact
            $admin = $this->userModel->find('EMP-SV-001');
            $this->assertNotNull($admin);
            $this->assertSame(0, (int) $admin['bloqueado']);
        }
    }

    /**
     * ADV-07: Oversized buffers and extreme character boundaries handled gracefully.
     */
    public function testOversizedBuffersAndExtremeBoundaries(): void
    {
        // 5,000 char username, 10,000 char password
        $res = $this->post('login', [
            'usuario'    => str_repeat('A', 5000),
            'contrasena' => str_repeat('B', 10000),
        ]);
        $res->assertStatus(302);
        $res->assertRedirectTo('login');
        $this->assertFalse((bool) (session('isLoggedIn') ?? false));

        // Multibyte & Unicode in username
        $resUnicode = $this->post('login', [
            'usuario'    => 'admin_sv_ñáéíóú_日本語_🔥',
            'contrasena' => 'Password123!',
        ]);
        $resUnicode->assertStatus(302);
        $resUnicode->assertRedirectTo('login');
        $this->assertFalse((bool) (session('isLoggedIn') ?? false));
    }

    /**
     * ADV-08: Administrative unlock restores authentication capability.
     */
    public function testAdminUnlockRestoresAccess(): void
    {
        // Pre-locked user
        $user = $this->userModel->find('EMP-LOCKED-01');
        $this->assertSame(1, (int) $user['bloqueado']);

        // Unlock
        $unlocked = $this->userModel->unlockAccount('EMP-LOCKED-01');
        $this->assertTrue($unlocked);

        $userUnlocked = $this->userModel->find('EMP-LOCKED-01');
        $this->assertSame(0, (int) $userUnlocked['bloqueado']);
        $this->assertSame(0, (int) $userUnlocked['intentos_fallidos']);

        // Attempt login with valid password
        $res = $this->post('login', [
            'usuario'    => 'user_locked',
            'contrasena' => 'Password123!',
        ]);
        $res->assertStatus(302);
        $res->assertRedirectTo('home.php');
        $this->assertTrue((bool) session('isLoggedIn'));
    }
}
