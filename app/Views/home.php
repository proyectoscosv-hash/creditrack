<?php
/**
 * CrediTrack - Landing View (home.php)
 * Authenticated user dashboard confirming session status, role, and country boundaries.
 *
 * NOTE: Country names are strictly dynamic via $pais_nombre to preserve cross-country isolation.
 */

// Defensive initialization with safe fallbacks
$isLoggedIn      = $isLoggedIn ?? (bool) (session('isLoggedIn') ?? false);
$codigo_empleado = $codigo_empleado ?? (string) (session('codigo_empleado') ?? '');
$usuario         = $usuario ?? (string) (session('usuario') ?? '');
$nombre          = $nombre ?? (string) (session('nombre') ?? '');
$rol             = $rol ?? (string) (session('rol') ?? '');
$id_pais         = $id_pais ?? (int) (session('id_pais') ?? 0);
$pais_nombre     = $pais_nombre ?? (string) (session('pais_nombre') ?? '');
$login_time      = $login_time ?? (int) (session('login_time') ?: time());
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>CrediTrack - Panel Principal</title>
    <style>
        /* Corporate 5-Color Palette */
        :root {
            --color-red: #BF0426;
            --color-blue: #0511F2;
            --color-navy: #023373;
            --color-brick: #A61717;
            --color-wine: #8D253C;
            --color-white: #ffffff;
            --color-bg-light: #f4f6f9;
            --color-text-dark: #0f172a;
            --color-border: #cbd5e1;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f4f6f9;
            color: #0f172a;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
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

        /* Header Navigation */
        .app-header {
            background-color: #023373;
            color: #ffffff;
            padding: 1rem 1.5rem;
            box-shadow: 0 2px 8px rgba(2, 51, 115, 0.25);
            border-bottom: 3px solid #8D253C;
        }

        .header-container {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .brand-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .brand-badge {
            font-size: 0.75rem;
            background-color: #8D253C;
            color: #ffffff;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .btn-logout {
            display: inline-block;
            background-color: #BF0426;
            color: #ffffff;
            text-decoration: none;
            padding: 0.5rem 1.1rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: background-color 0.2s ease, transform 0.1s ease;
            border: 1px solid #A61717;
        }

        .btn-logout:hover, .btn-logout:focus {
            background-color: #A61717;
            color: #ffffff;
            transform: translateY(-1px);
        }

        /* Main Workspace Container */
        .main-container {
            max-width: 1100px;
            width: 100%;
            margin: 2rem auto;
            padding: 0 1.5rem;
            flex: 1;
        }

        /* Confirmation Banner */
        .confirmation-banner {
            background-color: #ffffff;
            border-left: 6px solid #BF0426;
            border-top: 1px solid #cbd5e1;
            border-right: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 12px rgba(2, 51, 115, 0.08);
        }

        .banner-title {
            color: #023373;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.4rem;
        }

        .banner-subtitle {
            color: #475569;
            font-size: 1.05rem;
        }

        /* Session Health Badge Container */
        .session-health-container {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 0.8rem;
            border-top: 1px solid #e2e8f0;
        }

        .badge-active {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background-color: #0511F2;
            color: #ffffff;
            border: 1px solid #023373;
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background-color: #ffffff;
            border-radius: 50%;
            box-shadow: 0 0 4px #ffffff;
        }

        .session-timestamp {
            font-size: 0.85rem;
            color: #64748b;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Profile Card */
        .card {
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(2, 51, 115, 0.06);
        }

        .card-header {
            margin-bottom: 1.2rem;
            padding-bottom: 0.8rem;
            border-bottom: 2px solid #8D253C;
        }

        .card-title {
            color: #023373;
            font-size: 1.2rem;
            font-weight: 700;
        }

        .profile-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }

        .profile-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.4rem 0;
            border-bottom: 1px dashed #e2e8f0;
        }

        .profile-label {
            font-weight: 600;
            color: #475569;
            font-size: 0.95rem;
        }

        .profile-val {
            font-weight: 500;
            color: #0f172a;
        }

        .code-pill {
            background-color: #023373;
            color: #ffffff;
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.9rem;
        }

        /* Badges Row */
        .badges-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
            margin-bottom: 1.2rem;
        }

        .badge-role {
            background-color: #8D253C;
            color: #ffffff;
            padding: 0.4rem 0.85rem;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(141, 37, 60, 0.2);
        }

        .badge-country {
            background-color: #023373;
            color: #ffffff;
            padding: 0.4rem 0.85rem;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(2, 51, 115, 0.2);
        }

        /* Access Boundary Callout */
        .boundary-box {
            background-color: #ffffff;
            border: 1px solid #A61717;
            border-left: 5px solid #BF0426;
            border-radius: 6px;
            padding: 1.1rem;
            margin-top: 1rem;
        }

        .boundary-title {
            color: #A61717;
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 0.35rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .boundary-text {
            color: #0f172a;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        /* Bottom Action Bar */
        .action-bar {
            display: flex;
            justify-content: flex-end;
            margin-top: 1rem;
        }

        /* Semantic Footer with Author Attribution */
        .app-footer {
            background-color: #023373;
            color: #ffffff;
            border-top: 3px solid #8D253C;
            padding: 1.5rem 1rem;
            margin-top: auto;
            font-size: 0.9rem;
            text-align: center;
        }

        .footer-container {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .footer-copyright {
            color: #cbd5e1;
        }

        .attribution-team {
            color: #ffffff;
            font-size: 0.88rem;
            line-height: 1.5;
        }

        .attribution-team strong {
            color: #ffffff;
        }

        .attribution-roles {
            color: #cbd5e1;
            font-size: 0.82rem;
        }
    </style>
</head>
<body>

    <div class="palette-bar" aria-hidden="true">
        <span class="palette-swatch-1"></span>
        <span class="palette-swatch-2"></span>
        <span class="palette-swatch-3"></span>
        <span class="palette-swatch-4"></span>
        <span class="palette-swatch-5"></span>
    </div>

    <!-- Header Navigation -->
    <header class="app-header">
        <div class="header-container">
            <div class="brand-title">
                <span>CrediTrack</span>
                <span class="brand-badge">Acceso Seguro</span>
            </div>
            <div class="header-actions">
                <a href="<?= site_url('logout') ?>" class="btn-logout" role="button" title="Cerrar sesion / Salir">
                    Cerrar Sesión
                </a>
            </div>
        </div>
    </header>

    <!-- Main Workspace -->
    <main class="main-container">

        <!-- Confirmation Banner (Requirement R3, ViewUITest T1-REDIR-02, T1-REDIR-03) -->
        <section class="confirmation-banner" role="alert">
            <h1 class="banner-title">¡Inicio de sesión exitoso!</h1>
            <p class="banner-subtitle">Bienvenido al sistema CrediTrack.</p>

            <div class="session-health-container">
                <span class="badge-active" title="Estado de la sesion: Activa">
                    <span class="status-dot"></span>
                    <span>Estado de la sesion: Activa</span>
                    <span class="badge-sub-status" style="font-size:0.85rem; opacity:0.95;">(Sesion Activa)</span>
                </span>
                <span class="session-timestamp">
                    Sesión iniciada: <?= esc(date('d/m/Y H:i:s', $login_time > 0 ? $login_time : time())) ?>
                </span>
            </div>
        </section>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">

            <!-- Profile Info Card (Requirement R1, ViewUITest T1-REDIR-04, T2-REDIR-02) -->
            <section class="card profile-card">
                <div class="card-header">
                    <h2 class="card-title">Perfil del Usuario</h2>
                </div>
                <ul class="profile-list">
                    <li class="profile-item">
                        <span class="profile-label">Código de Empleado:</span>
                        <span class="profile-val"><code class="code-pill"><?= esc($codigo_empleado) ?></code></span>
                    </li>
                    <li class="profile-item">
                        <span class="profile-label">Nombre Completo:</span>
                        <span class="profile-val"><?= esc($nombre) ?></span>
                    </li>
                    <li class="profile-item">
                        <span class="profile-label">Usuario:</span>
                        <span class="profile-val"><code><?= esc($usuario) ?></code></span>
                    </li>
                </ul>
            </section>

            <!-- Roles, Jurisdiction, and Access Boundary Card (Requirement R1, ViewUITest T1-REDIR-05, T1-REDIR-06) -->
            <section class="card boundary-card">
                <div class="card-header">
                    <h2 class="card-title">Jurisdicción y Privilegios</h2>
                </div>

                <div class="badges-group">
                    <span class="badge-role">
                        <strong>Rol asignado: <?= esc($rol) ?></strong>
                    </span>
                    <span class="badge-country">
                        <strong>Pais asignado: <?= !empty($pais_nombre) ? esc($pais_nombre) : 'No especificado' ?></strong>
                    </span>
                </div>

                <div class="boundary-box">
                    <h3 class="boundary-title">Limite Territorial y Operativo</h3>
                    <p class="boundary-text">
                        Acceso restringido exclusivamente a información y operaciones de <?= !empty($pais_nombre) ? esc($pais_nombre) : 'la jurisdicción asignada' ?> con privilegios de <?= esc($rol) ?>.
                    </p>
                </div>
            </section>

        </div>

        <!-- Additional Logout Action in Body (ViewUITest T2-REDIR-04) -->
        <div class="action-bar">
            <a href="<?= site_url('logout') ?>" class="btn-logout" role="button" title="Cerrar sesion / Salir">
                Cerrar Sesión
            </a>
        </div>

    </main>

    <!-- Semantic Footer with Visible Manual Author Attribution (Requirement R4, ViewUITest T1-UI-07, T2-UI-02, T2-UI-03) -->
    <footer class="app-footer creditos-desarrollo attribution" role="contentinfo">
        <div class="footer-container">
            <p class="footer-copyright">&copy; <?= date('Y') ?> CrediTrack Inc. Todos los derechos reservados.</p>
            <p class="attribution-team">
                Sistema diseñado y desarrollado por el <strong>Equipo de Desarrollo</strong> de CrediTrack.
            </p>
            <p class="attribution-roles">
                Autores y Diseñadores de la Arquitectura de Seguridad y Control Territorial.
            </p>
        </div>
    </footer>

</body>
</html>
