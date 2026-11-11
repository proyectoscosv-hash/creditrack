<?php

namespace Tests\Unit;

use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * Unit & Feature Test Suite for AuthController, Home, and Authentication Lifecycle.
 *
 * Runs against SQLite :memory: with zero .env reliance.
 *
 * @internal
 */
final class AuthControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $seed        = \App\Database\Seeds\AuthSeeder::class;

    /**
     * R3: GET /login renders login page when not authenticated.
     */
    public function testGetLoginRendersPage(): void
    {
        $result = $this->get('login');
        $result->assertStatus(200);
        $result->assertSee('<form', 'body');
        $result->assertSee('usuario', 'body');
        $result->assertSee('contrasena', 'body');
    }

    /**
     * R3: GET /login redirects to home.php if already authenticated.
     */
    public function testGetLoginRedirectsIfAlreadyAuthenticated(): void
    {
        $result = $this->withSession([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-SV-001',
            'usuario'         => 'admin_sv',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ])->get('login');

        $result->assertStatus(302);
        $result->assertRedirectTo('home.php');
    }

    /**
     * R1: Non-existent user login fails and redirects back with error.
     */
    public function testLoginNonExistentUserFails(): void
    {
        $result = $this->post('login', [
            'usuario'    => 'nonexistent_user_xyz',
            'contrasena' => 'SomePassword123!',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('login');
        $result->assertSessionHas('error');
        $this->assertFalse((bool) (session('isLoggedIn') ?? false));
    }

    /**
     * R1 & R2: Wrong password on 1st attempt increments failure count to 1.
     */
    public function testLoginWrongPasswordFirstAttempt(): void
    {
        $result = $this->post('login', [
            'usuario'    => 'admin_sv',
            'contrasena' => 'WrongPassword123!',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('login');
        $result->assertSessionHas('error');

        $userModel = new UserModel();
        $user      = $userModel->find('EMP-SV-001');
        $this->assertSame(1, (int) $user['intentos_fallidos']);
        $this->assertSame(0, (int) $user['bloqueado']);
        $this->assertNotNull($user['ultimo_fallo']);
    }

    /**
     * R2: Wrong password on 2nd attempt increments failure count to 2.
     */
    public function testLoginWrongPasswordSecondAttempt(): void
    {
        $userModel = new UserModel();
        $userModel->recordFailedAttempt('EMP-SV-001', 0); // now at 1

        $result = $this->post('login', [
            'usuario'    => 'admin_sv',
            'contrasena' => 'WrongPassword123!',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('login');

        $user = $userModel->find('EMP-SV-001');
        $this->assertSame(2, (int) $user['intentos_fallidos']);
        $this->assertSame(0, (int) $user['bloqueado']);
    }

    /**
     * R2: 3rd consecutive failed attempt locks account immediately (bloqueado = 1).
     */
    public function testLoginWrongPasswordThirdAttemptLocksAccount(): void
    {
        // EMP-ATTEMPT-01 / user_2attempts starts with intentos_fallidos = 2
        $result = $this->post('login', [
            'usuario'    => 'user_2attempts',
            'contrasena' => 'WrongPassword123!',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('login');
        $result->assertSessionHas('error');

        $userModel = new UserModel();
        $user      = $userModel->find('EMP-ATTEMPT-01');
        $this->assertSame(3, (int) $user['intentos_fallidos']);
        $this->assertSame(1, (int) $user['bloqueado']);
        $this->assertNotNull($user['ultimo_fallo']);
    }

    /**
     * R2: Locked account rejected immediately without verifying password.
     */
    public function testLoginLockedAccountRejectedImmediatelyEvenWithCorrectPassword(): void
    {
        // EMP-LOCKED-01 / user_locked is pre-seeded with bloqueado = 1
        $result = $this->post('login', [
            'usuario'    => 'user_locked',
            'contrasena' => 'Password123!', // Correct password
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('login');
        $result->assertSessionHas('error');
        $this->assertFalse((bool) (session('isLoggedIn') ?? false));

        $userModel = new UserModel();
        $user      = $userModel->find('EMP-LOCKED-01');
        $this->assertSame(1, (int) $user['bloqueado']);
    }

    /**
     * R1 & R3: Valid login redirects to home.php and establishes complete session payload.
     */
    public function testLoginSuccessEstablishesSessionAndRedirects(): void
    {
        $result = $this->post('login', [
            'usuario'    => 'admin_sv',
            'contrasena' => 'Password123!',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('home.php');

        // Session payload verification (Features 9 & 10)
        $this->assertTrue((bool) session()->get('isLoggedIn'));
        $this->assertSame('EMP-SV-001', session()->get('codigo_empleado'));
        $this->assertSame('admin_sv', session()->get('usuario'));
        $this->assertSame('Administrador', session()->get('rol'));
        $this->assertSame(1, (int) session()->get('id_pais'));
        $this->assertSame('El Salvador', session()->get('pais_nombre'));
        $this->assertNotNull(session()->get('login_time'));

        // Fixation Defense verification
        /** @var \CodeIgniter\Test\Mock\MockSession $session */
        $session = Services::session();
        $this->assertTrue($session->didRegenerate);
    }

    /**
     * R2: Valid login resets failed attempt counter and clears lockout status.
     */
    public function testLoginSuccessResetsPreviousFailedAttempts(): void
    {
        // EMP-ATTEMPT-01 has 2 prior failed attempts
        $result = $this->post('login', [
            'usuario'    => 'user_2attempts',
            'contrasena' => 'Password123!',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('home.php');

        $userModel = new UserModel();
        $user      = $userModel->find('EMP-ATTEMPT-01');
        $this->assertSame(0, (int) $user['intentos_fallidos']);
        $this->assertSame(0, (int) $user['bloqueado']);
        $this->assertNotNull($user['ultimo_login']);
    }

    /**
     * R1: Login works across all country and role test accounts.
     *
     * @dataProvider multiCountryUserProvider
     */
    public function testLoginMultiCountryUsers(string $username, string $expectedRole, int $expectedCountryId, string $expectedCountryName): void
    {
        $result = $this->post('login', [
            'usuario'    => $username,
            'contrasena' => 'Password123!',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('home.php');
        $this->assertTrue((bool) session()->get('isLoggedIn'));
        $this->assertSame($expectedRole, session()->get('rol'));
        $this->assertSame($expectedCountryId, (int) session()->get('id_pais'));
        $this->assertSame($expectedCountryName, session()->get('pais_nombre'));
    }

    public static function multiCountryUserProvider(): array
    {
        return [
            ['admin_sv', 'Administrador', 1, 'El Salvador'],
            ['supervisor_gt', 'Supervisor', 2, 'Guatemala'],
            ['asesor_hn', 'Asesor', 3, 'Honduras'],
        ];
    }

    /**
     * R3: Secure logout terminates session and redirects to login.
     */
    public function testLogoutDestroysSessionAndRedirects(): void
    {
        $result = $this->withSession([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-SV-001',
            'usuario'         => 'admin_sv',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
        ])->get('logout');

        $result->assertStatus(302);
        $result->assertRedirectTo('login');
        $this->assertFalse((bool) (session('isLoggedIn') ?? false));
        $this->assertNull(session('usuario'));
        $this->assertNull(session('id_pais'));
    }

    /**
     * Validation: Empty input submission redirects back with validation error.
     */
    public function testEmptyCredentialsRedirectsBack(): void
    {
        $result = $this->post('login', [
            'usuario'    => '',
            'contrasena' => '',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('login');
        $result->assertSessionHas('error');
    }

    /**
     * Validation: Whitespace-only input submission redirects back with validation error.
     */
    public function testWhitespaceCredentialsRedirectsBack(): void
    {
        $result = $this->post('login', [
            'usuario'    => '   ',
            'contrasena' => '   ',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('login');
        $result->assertSessionHas('error');
    }

    /**
     * R3: Home index renders landing view confirming session for authenticated user.
     */
    public function testHomeRendersLandingPageWhenAuthenticated(): void
    {
        $result = $this->withSession([
            'isLoggedIn'      => true,
            'codigo_empleado' => 'EMP-SV-001',
            'usuario'         => 'admin_sv',
            'nombre'          => 'Carlos Administrador SV',
            'rol'             => 'Administrador',
            'id_pais'         => 1,
            'pais_nombre'     => 'El Salvador',
            'login_time'      => time(),
        ])->get('home.php');

        $result->assertStatus(200);
        $result->assertSee('EMP-SV-001', 'body');
        $result->assertSee('Administrador', 'body');
        $result->assertSee('El Salvador', 'body');
    }

    /**
     * R1 & R3: Home redirects unauthenticated access to login.
     */
    public function testHomeRedirectsUnauthenticated(): void
    {
        $result = $this->withSession([])->get('home.php');

        $result->assertStatus(302);
        $result->assertRedirectTo('login');
    }
}
