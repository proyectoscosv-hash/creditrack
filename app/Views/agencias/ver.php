<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CrediTrack - Detalle de Agencia</title>
    <!-- Local Vendored Leaflet CSS -->
    <link rel="stylesheet" href="<?= base_url('assets/leaflet/leaflet.css') ?>">
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
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--color-bg-light);
            color: var(--color-text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .palette-bar { display: flex; height: 5px; width: 100%; }
        .palette-swatch-1 { background-color: #BF0426; flex: 1; }
        .palette-swatch-2 { background-color: #0511F2; flex: 1; }
        .palette-swatch-3 { background-color: #023373; flex: 1; }
        .palette-swatch-4 { background-color: #A61717; flex: 1; }
        .palette-swatch-5 { background-color: #8D253C; flex: 1; }

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
            text-decoration: none;
        }
        .header-nav { display: flex; align-items: center; gap: 1rem; }
        .dropdown { position: relative; display: inline-block; }
        .dropdown-btn {
            background-color: #BF0426;
            color: #ffffff;
            border: 1px solid #A61717;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
        }
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: #ffffff;
            min-width: 180px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
            border-radius: 6px;
            z-index: 1000;
            border: 1px solid var(--color-border);
            overflow: hidden;
        }
        .dropdown-content a {
            color: #023373;
            padding: 0.75rem 1rem;
            text-decoration: none;
            display: block;
            font-weight: 500;
        }
        .dropdown-content a:hover { background-color: #f1f5f9; color: #BF0426; }
        .dropdown:hover .dropdown-content { display: block; }
        .btn-logout {
            background-color: #8D253C;
            color: #ffffff;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            border: 1px solid #A61717;
        }

        .main-container {
            max-width: 1100px;
            width: 100%;
            margin: 2rem auto;
            padding: 0 1.5rem;
            flex: 1;
        }
        .detail-card {
            background-color: #ffffff;
            border: 1px solid var(--color-border);
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        .card-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .agency-title {
            color: #023373;
            font-size: 1.8rem;
            font-weight: 700;
            border-left: 5px solid #BF0426;
            padding-left: 0.75rem;
        }
        .badge-active { background-color: #dcfce7; color: #166534; padding: 0.35rem 0.8rem; border-radius: 9999px; font-weight: 600; font-size: 0.85rem; }
        .badge-inactive { background-color: #fee2e2; color: #991b1b; padding: 0.35rem 0.8rem; border-radius: 9999px; font-weight: 600; font-size: 0.85rem; }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .info-item {
            background-color: #f8fafc;
            border: 1px solid var(--color-border);
            border-radius: 6px;
            padding: 0.85rem 1rem;
        }
        .info-label {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 0.25rem;
        }
        .info-value {
            font-size: 1rem;
            color: #023373;
            font-weight: 500;
        }
        .brand-pill { background-color: #0511F2; color: #ffffff; padding: 0.25rem 0.65rem; border-radius: 4px; font-size: 0.85rem; margin-right: 0.35rem; display: inline-block; }

        .disable-warning-box {
            background-color: #fff1f2;
            border-left: 4px solid #BF0426;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }
        .disable-warning-box h4 { color: #8D253C; margin-bottom: 0.25rem; }

        #detail-map {
            height: 300px;
            width: 100%;
            border-radius: 8px;
            border: 1px solid var(--color-border);
            margin-bottom: 1.5rem;
        }

        .actions-bar {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--color-border);
        }
        .btn-edit-action {
            background-color: #0511F2;
            color: #ffffff;
            text-decoration: none;
            padding: 0.6rem 1.25rem;
            border-radius: 6px;
            font-weight: 600;
            display: inline-block;
        }
        .btn-back-action {
            background-color: #e2e8f0;
            color: #334155;
            text-decoration: none;
            padding: 0.6rem 1.25rem;
            border-radius: 6px;
            font-weight: 600;
            display: inline-block;
        }

        /* Disable Form Box */
        .disable-card {
            background-color: #ffffff;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }
        .disable-card h3 { color: #BF0426; margin-bottom: 0.75rem; font-size: 1.1rem; }
        .disable-textarea {
            width: 100%;
            padding: 0.65rem 0.85rem;
            border: 1px solid var(--color-border);
            border-radius: 6px;
            font-family: inherit;
            margin-bottom: 0.75rem;
            min-height: 70px;
        }
        .btn-danger-disable {
            background-color: #BF0426;
            color: #ffffff;
            border: 1px solid #A61717;
            padding: 0.6rem 1.25rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
        }

        /* Audit Trail Table */
        .audit-card {
            background-color: #ffffff;
            border: 1px solid var(--color-border);
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .audit-title {
            color: #023373;
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 1rem;
            border-left: 4px solid #0511F2;
            padding-left: 0.6rem;
        }
        .audit-table { width: 100%; border-collapse: collapse; }
        .audit-table th {
            background-color: #023373;
            color: #ffffff;
            padding: 0.65rem 0.85rem;
            font-size: 0.8rem;
            text-transform: uppercase;
        }
        .audit-table td {
            padding: 0.65rem 0.85rem;
            border-bottom: 1px solid var(--color-border);
            font-size: 0.85rem;
        }
        .badge-op-crear { background-color: #dbeafe; color: #1e40af; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 600; }
        .badge-op-actualizar { background-color: #fef3c7; color: #92400e; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 600; }
        .badge-op-deshabilitar { background-color: #fee2e2; color: #991b1b; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 600; }

        .alert-success { background-color: #d1fae5; color: #065f46; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1.5rem; }
        .alert-error { background-color: #fee2e2; color: #991b1b; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1.5rem; }
        .alert-warning { background-color: #fef3c7; color: #92400e; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1.5rem; }

        .footer {
            margin-top: auto;
            background-color: #023373;
            color: #ffffff;
            text-align: center;
            padding: 1rem;
            font-size: 0.85rem;
            border-top: 3px solid #BF0426;
        }
    </style>
</head>
<body>

    <!-- Shared Authenticated Header Partial -->
    <?= $this->include('layouts/app_header') ?>

    <!-- Main Workspace -->
    <main class="main-container">

        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert-error"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('warning')): ?>
            <div class="alert-warning"><?= esc(session()->getFlashdata('warning')) ?></div>
        <?php endif; ?>

        <?php $idAgencia = (int) ($agency['idagencia'] ?? $agency['Idagencia']); ?>

        <div class="detail-card">
            <div class="card-header-bar">
                <h1 class="agency-title"><?= esc($agency['nombre_agencia']) ?></h1>
                <div>
                    <?php if ((int) $agency['activo'] === 1): ?>
                        <span class="badge-active">Activa</span>
                    <?php else: ?>
                        <span class="badge-inactive">Deshabilitada</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Inactive Details Banner -->
            <?php if ((int) $agency['activo'] === 0): ?>
                <div class="disable-warning-box">
                    <h4>Agencia Inactiva / Deshabilitada Lógicamente</h4>
                    <p><strong>Motivo de Deshabilitación:</strong> <?= html_entity_decode((string) ($agency['motivo_deshabilitacion'] ?? 'No especificado'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p style="font-size:0.85rem; color:#64748b; margin-top:0.35rem;">
                        <strong>Deshabilitado por:</strong> <?= esc($agency['deshabilitado_por'] ?? 'Sistema') ?> 
                        <?php if (!empty($agency['deshabilitado_en'])): ?>
                            | <strong>Fecha:</strong> <?= esc($agency['deshabilitado_en']) ?>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Information Grid -->
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">País</div>
                    <div class="info-value"><?= esc($agency['pais_nombre'] ?? $agency['pais'] ?? '') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Departamento</div>
                    <div class="info-value"><?= esc($agency['departamento_nombre'] ?? $agency['departamento'] ?? 'N/A') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Municipio</div>
                    <div class="info-value"><?= esc($agency['municipio_nombre'] ?? $agency['municipio'] ?? 'N/A') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Distrito</div>
                    <div class="info-value"><?= esc($agency['distrito_nombre'] ?? $agency['distrito'] ?? 'No aplica') ?></div>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-item" style="grid-column: 1 / -1;">
                    <div class="info-label">Dirección Física</div>
                    <div class="info-value"><?= esc($agency['direccion']) ?></div>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Coordenadas Geográficas</div>
                    <div class="info-value">
                        Latitud: <code><?= esc((string)$agency['latitud']) ?></code><br>
                        Longitud: <code><?= esc((string)$agency['longitud']) ?></code>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Marcas de Motocicletas Asociadas</div>
                    <div class="info-value">
                        <?php foreach ($agency['marca_nombres'] ?? [] as $mName): ?>
                            <span class="brand-pill"><?= esc($mName) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Leaflet Map Preview -->
            <?php if (!empty($agency['latitud']) && !empty($agency['longitud']) && ((float)$agency['latitud'] !== 0.0 || (float)$agency['longitud'] !== 0.0)): ?>
                <div id="detail-map"></div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="actions-bar">
                <a href="<?= site_url('agencias') ?>" class="btn-back-action">← Volver a la Lista</a>
                <?php if ((int) $agency['activo'] === 1): ?>
                    <a href="<?= site_url('agencias/editar/' . $idAgencia) ?>" class="btn-edit-action">Editar Agencia</a>
                <?php endif; ?>
            </div>

            <!-- Soft Disable Form (if active) -->
            <?php if ((int) $agency['activo'] === 1): ?>
                <div class="disable-card">
                    <h3>Deshabilitar Agencia Lógicamente</h3>
                    <p style="font-size:0.85rem; color:#64748b; margin-bottom:0.75rem;">
                        La deshabilitación lógica conserva el histórico de la agencia pero la excluye del mapa operativo y de las operaciones regulares.
                    </p>
                    <form action="<?= site_url('agencias/deshabilitar/' . $idAgencia) ?>" method="POST">
                        <?= csrf_field() ?>
                        <textarea name="motivo_deshabilitacion" class="disable-textarea" required 
                                  placeholder="Ingrese obligatoriamente el motivo de la deshabilitación..."></textarea>
                        <button type="submit" class="btn-danger-disable" 
                                onclick="return confirm('¿Está seguro de que desea deshabilitar lógicamente esta agencia?');">
                            Deshabilitar Agencia
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- Audit Trail Card -->
        <div class="audit-card">
            <h2 class="audit-title">Trazabilidad de Auditoría</h2>
            <?php if (empty($auditTrail)): ?>
                <p style="color:#64748b; font-size:0.9rem;">No hay registros de auditoría disponibles para esta agencia.</p>
            <?php else: ?>
                <table class="audit-table">
                    <thead>
                        <tr>
                            <th>Fecha y Hora</th>
                            <th>Operación</th>
                            <th>Usuario / Empleado</th>
                            <th>Motivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditTrail as $audit): ?>
                            <tr>
                                <td><?= esc($audit['fecha_hora']) ?></td>
                                <td>
                                    <?php 
                                        $op = strtoupper((string) ($audit['tipo_operacion'] ?? ''));
                                        $badgeClass = 'badge-op-actualizar';
                                        if ($op === 'CREAR') $badgeClass = 'badge-op-crear';
                                        if ($op === 'DESHABILITAR') $badgeClass = 'badge-op-deshabilitar';
                                    ?>
                                    <span class="<?= $badgeClass ?>"><?= esc($op) ?></span>
                                </td>
                                <td>
                                    <strong><?= esc($audit['codigo_empleado']) ?></strong>
                                    <?php if (!empty($audit['usuario_nombre'])): ?>
                                        (<?= esc($audit['usuario_nombre']) ?>)
                                    <?php endif; ?>
                                </td>
                                <td><?= esc($audit['motivo_deshabilitacion'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </main>

    <footer class="footer">
        <p>© 2026 CrediTrack Inc. - Gestión y Administración Territorial de Agencias.</p>
    </footer>

    <!-- Local Vendored Leaflet JS -->
    <script src="<?= base_url('assets/leaflet/leaflet.js') ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var mapEl = document.getElementById('detail-map');
            if (mapEl) {
                var lat = <?= (float) ($agency['latitud'] ?? 13.69294) ?>;
                var lng = <?= (float) ($agency['longitud'] ?? -89.21819) ?>;

                var map = L.map('detail-map').setView([lat, lng], 15);
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);

                var marker = L.marker([lat, lng]).addTo(map);
                var popupText = '<strong><?= esc($agency['nombre_agencia']) ?></strong><br><?= esc($agency['direccion']) ?>';
                marker.bindPopup(popupText).openPopup();
            }
        });
    </script>

</body>
</html>
