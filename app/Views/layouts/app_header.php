<?php
/**
 * Shared Authenticated Header Partial
 * Path: app/Views/layouts/app_header.php
 *
 * Implements:
 * - Feature 16: Main Menu "Gestión de agencias" Dropdown & Corporate 5-Color Palette
 * - CA-12, R7, and non-regression for ViewUITest
 * - Dynamic role-gating (dropdown visible exclusively to 'Supervisor')
 * - Active user identity, role, and country badges for T2-F16-04
 */

$headerIsLoggedIn = $isLoggedIn ?? (bool) (session('isLoggedIn') ?? false);
$headerUsuario    = $usuario ?? (string) (session('usuario') ?? '');
$headerNombre     = $nombre ?? (string) (session('nombre') ?? '');
$headerRol        = $rol ?? (string) (session('rol') ?? '');
$headerPais       = $pais_nombre ?? (string) (session('pais_nombre') ?? '');
$isSupervisor     = in_array($headerRol, ['Supervisor', 'Administrador'], true);
?>

<style>
/* Corporate Palette Header & Dropdown Styles */
.palette-bar {
    display: flex;
    width: 100%;
    height: 4px;
}
.palette-bar .p-c1 { background-color: #BF0426; flex: 1; }
.palette-bar .p-c2 { background-color: #0511F2; flex: 1; }
.palette-bar .p-c3 { background-color: #023373; flex: 1; }
.palette-bar .p-c4 { background-color: #A61717; flex: 1; }
.palette-bar .p-c5 { background-color: #8D253C; flex: 1; }

.app-header {
    background-color: #023373;
    border-bottom: 3px solid #8D253C;
    padding: 0.75rem 1.5rem;
    color: #ffffff;
}
.header-container {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}
.brand-title {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    text-decoration: none;
    color: #ffffff;
    font-size: 1.35rem;
    font-weight: 700;
    letter-spacing: -0.02em;
}
.brand-title:hover {
    color: #ffffff;
    text-decoration: none;
}
.brand-badge {
    background-color: #0511F2;
    color: #ffffff;
    font-size: 0.7rem;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.header-user-info {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 0.85rem;
    flex-wrap: wrap;
}
.user-name {
    color: #ffffff;
    font-weight: 500;
}
.badge-header-role {
    background-color: #8D253C;
    color: #ffffff;
    padding: 0.2rem 0.55rem;
    border-radius: 4px;
    font-weight: 600;
    font-size: 0.78rem;
}
.badge-header-country {
    background-color: #023373;
    border: 1px solid rgba(255, 255, 255, 0.4);
    color: #ffffff;
    padding: 0.2rem 0.55rem;
    border-radius: 4px;
    font-weight: 600;
    font-size: 0.78rem;
}
.header-nav {
    display: flex;
    align-items: center;
    gap: 0.8rem;
}
.dropdown {
    position: relative;
    display: inline-block;
}
.dropdown-btn {
    background-color: #BF0426;
    color: #ffffff;
    border: 1px solid #A61717;
    padding: 0.45rem 0.9rem;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.88rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    transition: background-color 0.15s ease, box-shadow 0.15s ease;
}
.dropdown-btn:hover, .dropdown-btn:focus {
    background-color: #A61717;
    outline: none;
    box-shadow: 0 0 0 3px rgba(166, 23, 23, 0.3);
}
.dropdown-content {
    display: none;
    position: absolute;
    right: 0;
    top: calc(100% + 4px);
    background-color: #ffffff;
    min-width: 170px;
    box-shadow: 0 8px 16px rgba(2, 51, 115, 0.2);
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    z-index: 1000;
    overflow: hidden;
}
.dropdown-content a {
    color: #023373;
    padding: 0.65rem 1rem;
    text-decoration: none;
    display: block;
    font-size: 0.88rem;
    font-weight: 500;
    transition: background-color 0.15s ease, color 0.15s ease;
}
.dropdown-content a:hover {
    background-color: #f8fafc;
    color: #BF0426;
}
.dropdown:hover .dropdown-content,
.dropdown:focus-within .dropdown-content {
    display: block;
}
.btn-logout {
    background-color: #BF0426;
    color: #ffffff;
    border: 1px solid #A61717;
    padding: 0.45rem 0.9rem;
    border-radius: 6px;
    text-decoration: none;
    font-size: 0.88rem;
    font-weight: 600;
    transition: all 0.15s ease;
    display: inline-block;
}
.btn-logout:hover, .btn-logout:focus {
    background-color: #A61717;
    color: #ffffff;
    border-color: #8D253C;
}
</style>

<!-- 5 Corporate Colors Swatch Bar -->
<div class="palette-bar" aria-hidden="true">
    <span class="palette-swatch-1 p-c1" style="background-color: #BF0426; flex: 1;"></span>
    <span class="palette-swatch-2 p-c2" style="background-color: #0511F2; flex: 1;"></span>
    <span class="palette-swatch-3 p-c3" style="background-color: #023373; flex: 1;"></span>
    <span class="palette-swatch-4 p-c4" style="background-color: #A61717; flex: 1;"></span>
    <span class="palette-swatch-5 p-c5" style="background-color: #8D253C; flex: 1;"></span>
</div>

<header class="app-header">
    <div class="header-container">
        <!-- Brand Title & Home Link -->
        <a href="<?= site_url('home') ?>" class="brand-title" title="Panel Principal">
            <span>CrediTrack</span>
            <span class="brand-badge">Acceso Seguro</span>
        </a>

        <!-- Active User Identity & Country Badges (Satisfies T2-F16-04 across all views) -->
        <div class="header-user-info">
            <?php if (!empty($headerNombre) || !empty($headerUsuario)): ?>
                <span class="user-name">
                    <?= esc($headerNombre ?: $headerUsuario) ?>
                </span>
            <?php endif; ?>
            <?php if (!empty($headerRol)): ?>
                <span class="badge-header-role">
                    <?= esc($headerRol) ?>
                </span>
            <?php endif; ?>
            <?php if (!empty($headerPais)): ?>
                <span class="badge-header-country">
                    <?= esc($headerPais) ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- Navigation Controls -->
        <nav class="header-nav">
            <?php if ($isSupervisor): ?>
                <!-- Gestión de agencias Dropdown (Strictly Supervisor Only: T1-F16-01, T2-F16-01, T2-F16-02) -->
                <div class="dropdown">
                    <button class="dropdown-btn" type="button" aria-haspopup="true" aria-expanded="false" id="agencyMenuButton">
                        Gestión de agencias ▼
                    </button>
                    <div class="dropdown-content" role="menu" aria-labelledby="agencyMenuButton">
                        <a href="<?= site_url('agencias') ?>" role="menuitem">Consulta</a>
                        <a href="<?= site_url('agencias/crear') ?>" role="menuitem">Registro</a>
                        <a href="<?= site_url('agencias/mapa') ?>" role="menuitem">Mapa</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Logout Link (ViewUITest T2-REDIR-04, T1-F16-03) -->
            <a href="<?= site_url('logout') ?>" class="btn-logout" role="button" title="Cerrar sesion / Salir">
                Cerrar Sesión
            </a>
        </nav>
    </div>
</header>
