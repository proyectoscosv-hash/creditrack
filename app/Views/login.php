<?php
/**
 * CrediTrack - Secure Authentication View (login.php)
 *
 * Requirements:
 * - R1: Form posting to /login with CSRF, usuario, and contrasena
 * - R2: Security lockout alert presentation when consecutive failed attempts reach 3
 * - R4: Strict implementation of corporate 5-color palette:
 *       #BF0426 (Crimson), #0511F2 (Electric Blue), #023373 (Navy Blue),
 *       #A61717 (Brick Red), #8D253C (Claret Wine)
 * - R4: Visible manual author and designer attribution markup
 */

$errorMsg   = session('error');
$warningMsg = session('warning');
$infoMsg    = session('message') ?? session('success');
$intentosFallidos = session('intentos_fallidos');
$remaining  = session('remaining_attempts') ?? ($intentosFallidos !== null ? max(0, 3 - (int) $intentosFallidos) : null);

$isLockout = (!empty($errorMsg) && (
    stripos($errorMsg, 'bloqueado') !== false ||
    stripos($errorMsg, '3 intentos fallidos') !== false
)) || (bool) session('is_locked') || ((int) session('intentos_fallidos') >= 3);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>CrediTrack - Iniciar Sesión</title>
    <style>
        :root {
            --color-navy: #023373;
            --color-electric-blue: #0511F2;
            --color-crimson: #BF0426;
            --color-brick-red: #A61717;
            --color-wine: #8D253C;
            --color-white: #ffffff;
            --color-light-bg: #f8fafc;
            --color-border: #cbd5e1;
            --color-text-dark: #0f172a;
            --color-text-muted: #475569;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #023373 0%, #0511F2 50%, #8D253C 100%);
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .login-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .login-container {
            width: 100%;
            max-width: 460px;
            background: #ffffff;
            color: #0f172a;
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(2, 51, 115, 0.35);
            overflow: hidden;
            border-top: 6px solid #023373;
            border-bottom: 5px solid #8D253C;
        }

        .card-header {
            background-color: #023373;
            color: #ffffff;
            padding: 2rem 1.5rem 1.5rem;
            text-align: center;
            border-bottom: 3px solid #0511F2;
        }

        .brand-title {
            font-size: 1.85rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #ffffff;
            margin-bottom: 0.35rem;
        }

        .brand-subtitle {
            font-size: 0.9rem;
            color: #cbd5e1;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .card-body {
            padding: 2rem 1.75rem;
            background-color: #ffffff;
            color: #0f172a;
        }

        .form-heading {
            font-size: 1.25rem;
            font-weight: 700;
            color: #023373;
            margin-bottom: 1.25rem;
            text-align: center;
        }

        /* Flash Alerts with High Accessible Contrast */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.925rem;
            line-height: 1.45;
            color: #ffffff;
        }

        .alert-lockout {
            background-color: #BF0426;
            color: #ffffff;
            border: 2px solid #A61717;
            box-shadow: 0 4px 12px rgba(191, 4, 38, 0.3);
        }

        .alert-danger {
            background-color: #BF0426;
            color: #ffffff;
            border: 2px solid #A61717;
        }

        .alert-warning {
            background-color: #A61717;
            color: #ffffff;
            border: 2px solid #8D253C;
        }

        .alert-info {
            background-color: #023373;
            color: #ffffff;
            border: 2px solid #0511F2;
        }

        .alert-title {
            font-weight: 700;
            margin-bottom: 0.35rem;
            display: block;
            color: #ffffff;
        }

        .attempts-counter {
            margin-top: 0.5rem;
            display: inline-block;
            background-color: #8D253C;
            color: #ffffff;
            padding: 0.25rem 0.65rem;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        /* Form Controls */
        .form-group {
            margin-bottom: 1.35rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.45rem;
            font-size: 0.9rem;
            font-weight: 600;
            color: #023373;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            border: 1.5px solid #cbd5e1;
            border-radius: 6px;
            background-color: #f8fafc;
            color: #0f172a;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: #0511F2;
            box-shadow: 0 0 0 3px rgba(5, 17, 242, 0.25);
            background-color: #ffffff;
        }

        .btn-primary {
            width: 100%;
            padding: 0.85rem 1.25rem;
            font-size: 1.05rem;
            font-weight: 700;
            color: #ffffff;
            background-color: #0511F2;
            border: 2px solid #023373;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.1s;
            margin-top: 0.5rem;
        }

        .btn-primary:hover {
            background-color: #023373;
            border-color: #0511F2;
            color: #ffffff;
        }

        .btn-primary:active {
            transform: scale(0.99);
        }

        /* Palette Swatch Visual Bar */
        .palette-bar {
            display: flex;
            height: 5px;
            width: 100%;
        }
        .palette-swatch-1 { background-color: #BF0426; flex: 1; }
        .palette-swatch-2 { background-color: #0511F2; flex: 1; }
        .palette-swatch-3 { background-color: #023373; flex: 1; }
        .palette-swatch-4 { background-color: #A61717; flex: 1; }
        .palette-swatch-5 { background-color: #8D253C; flex: 1; }

        /* Semantic Footer with Author Attribution */
        .app-footer {
            background-color: #023373;
            color: #ffffff;
            padding: 1.25rem 1rem;
            text-align: center;
            font-size: 0.85rem;
            line-height: 1.5;
            border-top: 3px solid #8D253C;
        }

        .footer-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .attribution-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: #ffffff;
        }

        .attribution-team {
            color: #cbd5e1;
            margin-bottom: 0.2rem;
        }

        .attribution-team strong {
            color: #ffffff;
        }

        .attribution-roles {
            color: #cbd5e1;
            font-size: 0.8rem;
        }

        @media (max-width: 480px) {
            .card-body {
                padding: 1.5rem 1.25rem;
            }
            .brand-title {
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>

<main class="login-wrapper">
    <div class="login-container">
        <div class="palette-bar" aria-hidden="true">
            <span class="palette-swatch-1"></span>
            <span class="palette-swatch-2"></span>
            <span class="palette-swatch-3"></span>
            <span class="palette-swatch-4"></span>
            <span class="palette-swatch-5"></span>
        </div>

        <div class="card-header">
            <h1 class="brand-title">CrediTrack</h1>
            <p class="brand-subtitle">Control de Acceso Territorial</p>
        </div>

        <div class="card-body">
            <h2 class="form-heading">Iniciar Sesión</h2>

            <?php if ($isLockout): ?>
                <!-- Security Lockout Alert (Requirement R2, Feature 6/7) -->
                <div class="alert alert-lockout" style="background-color: #BF0426; border: 2px solid #A61717; color: #ffffff;" role="alert" aria-live="assertive">
                    <span class="alert-title">Acceso Bloqueado por Seguridad</span>
                    <p><?= esc($errorMsg) ?></p>
                    <p style="margin-top: 0.35rem; font-size: 0.85rem; color: #ffffff;">
                        Comuníquese con el administrador del sistema para restablecer sus credenciales.
                    </p>
                </div>
            <?php elseif (!empty($errorMsg)): ?>
                <!-- Standard Authentication Error or Attempt Warning -->
                <div class="alert alert-danger" style="background-color: #BF0426; border: 2px solid #A61717; color: #ffffff;" role="alert" aria-live="assertive">
                    <span class="alert-title">Atención</span>
                    <p><?= esc($errorMsg) ?></p>
                    <?php if ($remaining !== null): ?>
                        <div class="attempts-counter" style="background-color: #8D253C; color: #ffffff;">
                            Intentos restantes: <strong><?= (int) $remaining ?></strong> de 3
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($warningMsg) && $warningMsg !== $errorMsg && !$isLockout): ?>
                <!-- Auxiliary Warning Alert -->
                <div class="alert alert-warning" style="background-color: #A61717; border: 2px solid #8D253C; color: #ffffff;" role="alert">
                    <span class="alert-title">Advertencia</span>
                    <p><?= esc($warningMsg) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($infoMsg)): ?>
                <!-- Informational Notification (e.g. Logout Success) -->
                <div class="alert alert-info" style="background-color: #023373; border: 2px solid #0511F2; color: #ffffff;" role="status">
                    <p><?= esc($infoMsg) ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= site_url('login') ?>" class="login-form">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="usuario" class="form-label">Usuario:</label>
                    <input type="text" 
                           id="usuario" 
                           name="usuario" 
                           class="form-control" 
                           value="<?= esc(old('usuario', '')) ?>" 
                           required 
                           autocomplete="username" 
                           placeholder="Ingrese su usuario" 
                           autofocus />
                </div>

                <div class="form-group">
                    <label for="contrasena" class="form-label">Contraseña:</label>
                    <input type="password" 
                           id="contrasena" 
                           name="contrasena" 
                           class="form-control" 
                           required 
                           autocomplete="current-password" 
                           placeholder="Ingrese su contraseña" />
                </div>

                <button type="submit" id="btn-login" class="btn-primary">Iniciar Sesión</button>
            </form>
        </div>
    </div>
</main>

<footer class="app-footer creditos-desarrollo attribution" role="contentinfo">
    <div class="footer-container">
        <p class="attribution-title">
            <strong>CrediTrack</strong> &copy; <?= date('Y') ?> &mdash; Sistema de Control de Acceso y Gestión de Crédito
        </p>
        <p class="attribution-team">
            Sistema diseñado y desarrollado por el <strong>Equipo de Desarrollo</strong> de CrediTrack.
        </p>
        <p class="attribution-roles">
            Autores y diseñadores de la arquitectura de autenticación y control territorial.
        </p>
    </div>
</footer>

</body>
</html>
