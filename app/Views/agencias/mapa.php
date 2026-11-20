<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CrediTrack - Mapa Operativo de Agencias</title>
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
        .main-container {
            max-width: 1200px;
            width: 100%;
            margin: 1.5rem auto 2.5rem;
            padding: 0 1.5rem;
            flex: 1;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .page-title {
            color: #023373;
            font-size: 1.75rem;
            font-weight: 700;
            border-left: 5px solid #BF0426;
            padding-left: 0.75rem;
        }
        .country-badge {
            background-color: #023373;
            border: 1px solid #0511F2;
            color: #ffffff;
            padding: 0.4rem 0.9rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        /* Filter Bar */
        .filter-card {
            background-color: #ffffff;
            border: 1px solid var(--color-border);
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(2, 51, 115, 0.05);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1rem;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            flex: 1;
            min-width: 180px;
        }
        .filter-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #023373;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .filter-select, .filter-input {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--color-border);
            border-radius: 6px;
            font-size: 0.9rem;
            color: var(--color-text-dark);
            background-color: #ffffff;
            outline: none;
            transition: border-color 0.15s ease;
        }
        .filter-select:focus, .filter-input:focus {
            border-color: #0511F2;
            box-shadow: 0 0 0 2px rgba(5, 17, 242, 0.15);
        }

        .map-card {
            background-color: #ffffff;
            border: 1px solid var(--color-border);
            border-radius: 8px;
            padding: 1.25rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        #map {
            height: 600px;
            width: 100%;
            border-radius: 8px;
            border: 1px solid var(--color-border);
        }
        .map-status-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.75rem;
            font-size: 0.88rem;
            color: #64748b;
        }
        #agencias-count {
            font-weight: 600;
            color: #023373;
        }
        .footer {
            margin-top: auto;
            background-color: #023373;
            color: #ffffff;
            text-align: center;
            padding: 1rem;
            font-size: 0.85rem;
            border-top: 3px solid #BF0426;
        }
        .footer a {
            color: #cbd5e1;
            text-decoration: none;
        }
    </style>
</head>
<body>

    <!-- Shared Authenticated Header Partial -->
    <?= $this->include('layouts/app_header') ?>

    <!-- Main Workspace -->
    <main class="main-container">

        <div class="page-header">
            <h1 class="page-title">Mapa Operativo de Agencias</h1>
            <span class="country-badge">Jurisdicción: <?= esc($paisNombre ?? '') ?></span>
        </div>

        <!-- Filter Bar (CA-10 / R3) -->
        <section class="filter-card" aria-label="Filtros del mapa">
            <div class="filter-group">
                <label for="filtro-marca" class="filter-label">Marca</label>
                <select id="filtro-marca" class="filter-select">
                    <option value="">Todas las marcas</option>
                    <?php if (!empty($marcas) && is_array($marcas)): ?>
                        <?php foreach ($marcas as $m): ?>
                            <option value="<?= esc($m['Marca'] ?? $m['marca'] ?? '') ?>">
                                <?= esc($m['Marca'] ?? $m['marca'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="filtro-departamento" class="filter-label">Departamento</label>
                <select id="filtro-departamento" class="filter-select">
                    <option value="">Todos los departamentos</option>
                    <?php if (!empty($departamentos) && is_array($departamentos)): ?>
                        <?php foreach ($departamentos as $d): ?>
                            <option value="<?= esc($d['Departamento'] ?? $d['departamento'] ?? '') ?>">
                                <?= esc($d['Departamento'] ?? $d['departamento'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="filter-group" style="flex: 1.5;">
                <label for="filtro-busqueda" class="filter-label">Buscar agencia o dirección</label>
                <input type="text" id="filtro-busqueda" class="filter-input" placeholder="Nombre de agencia o dirección...">
            </div>
        </section>

        <!-- Operational Map Container -->
        <div class="map-card">
            <div id="map"></div>
            <div class="map-status-bar">
                <span id="agencias-count">Cargando agencias...</span>
                <span>OpenStreetMap &amp; Leaflet</span>
            </div>
        </div>

    </main>

    <footer class="footer">
        <p>&copy; <?= date('Y') ?> CrediTrack Inc. - Gestión y Administración Territorial de Agencias.</p>
    </footer>

    <!-- Embedded Agencies JSON (Ensures Server-Rendered Availability for Tier 3 E2E Tests) -->
    <script id="agencias-json" type="application/json">
        <?= json_encode($agencias ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
    </script>

    <!-- Local Vendored Leaflet JS -->
    <script src="<?= base_url('assets/leaflet/leaflet.js') ?>"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            <?php
            $defLat  = 13.69294;
            $defLng  = -89.21819;
            $defZoom = 9;
            $cpId    = (int) (session('id_pais') ?? ($idPais ?? 1));
            if ($cpId === 2) {
                $defLat  = 14.63491;
                $defLng  = -90.50688;
                $defZoom = 8;
            } elseif ($cpId === 3) {
                $defLat  = 14.07227;
                $defLng  = -87.19213;
                $defZoom = 8;
            }
            ?>
            var defaultLat = <?= (float) $defLat ?>;
            var defaultLng = <?= (float) $defLng ?>;
            var defaultZoom = <?= (int) $defZoom ?>;
            var paisNombre = <?= json_encode((string) ($paisNombre ?? '')) ?>;

            var map = L.map('map').setView([defaultLat, defaultLng], defaultZoom);

            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            var countEl = document.getElementById('agencias-count');
            var filtroMarca = document.getElementById('filtro-marca');
            var filtroDepto = document.getElementById('filtro-departamento');
            var filtroBusqueda = document.getElementById('filtro-busqueda');

            var allAgencias = [];
            var currentMarkers = [];

            // Load initial embedded agencies safely
            try {
                var jsonEl = document.getElementById('agencias-json');
                if (jsonEl && jsonEl.textContent) {
                    var parsed = JSON.parse(jsonEl.textContent.trim());
                    if (Array.isArray(parsed)) {
                        allAgencias = parsed;
                    }
                }
            } catch (e) {
                console.warn('No embedded agency payload or parse error:', e);
            }

            function renderMarkers(agenciasToRender) {
                // Clear existing markers
                currentMarkers.forEach(function (m) {
                    map.removeLayer(m);
                });
                currentMarkers = [];

                var bounds = [];
                var count = 0;

                agenciasToRender.forEach(function (agencia) {
                    var lat = parseFloat(agencia.latitud);
                    var lng = parseFloat(agencia.longitud);

                    // Strict exclusion of invalid/zero coordinates (CA-11)
                    if (isFinite(lat) && isFinite(lng) && (lat !== 0 || lng !== 0) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                        var marcasStr = Array.isArray(agencia.marcas) ? agencia.marcas.join(', ') : (agencia.marcas || '');
                        var agenciaId = agencia.id || agencia.idagencia || '';
                        var verUrl = '<?= site_url('agencias/ver/') ?>' + (agenciaId ? agenciaId : '');

                        var popupHtml = '<div style="font-size:0.95rem; line-height:1.45; min-width:200px;">'
                            + '<strong style="color:#023373; font-size:1.05rem; display:block; margin-bottom:4px;">' + escapeHtml(agencia.nombre || agencia.nombre_agencia || '') + '</strong>'
                            + '<b>Dirección:</b> ' + escapeHtml(agencia.direccion || '') + '<br>'
                            + '<b>País:</b> ' + escapeHtml(agencia.pais || paisNombre) + '<br>'
                            + '<b>Marcas:</b> ' + escapeHtml(marcasStr) + '<br>'
                            + (agenciaId ? '<a href="' + verUrl + '" style="color:#BF0426; font-weight:600; display:inline-block; margin-top:6px; text-decoration:underline;">Ver Detalle</a>' : '')
                            + '</div>';

                        var marker = L.marker([lat, lng]).addTo(map);
                        marker.bindPopup(popupHtml);
                        currentMarkers.push(marker);
                        bounds.push([lat, lng]);
                        count++;
                    }
                });

                if (countEl) {
                    countEl.textContent = count === 1 ? '1 agencia activa en mapa' : count + ' agencias activas en mapa';
                }

                if (bounds.length > 0) {
                    if (bounds.length === 1) {
                        map.setView(bounds[0], 14);
                    } else {
                        map.fitBounds(bounds, { padding: [40, 40] });
                    }
                } else {
                    map.setView([defaultLat, defaultLng], defaultZoom);
                }
            }

            function applyFilters() {
                var marcaVal = filtroMarca ? filtroMarca.value.toLowerCase().trim() : '';
                var deptoVal = filtroDepto ? filtroDepto.value.toLowerCase().trim() : '';
                var searchVal = filtroBusqueda ? filtroBusqueda.value.toLowerCase().trim() : '';

                var filtered = allAgencias.filter(function (agencia) {
                    if (marcaVal) {
                        var marcasList = Array.isArray(agencia.marcas)
                            ? agencia.marcas.map(function (m) { return String(m).toLowerCase(); })
                            : [String(agencia.marcas || '').toLowerCase()];
                        var matchesMarca = marcasList.some(function (m) { return m.indexOf(marcaVal) !== -1; });
                        if (!matchesMarca) return false;
                    }

                    if (deptoVal) {
                        var deptoStr = String(agencia.departamento || '').toLowerCase();
                        if (deptoStr.indexOf(deptoVal) === -1) return false;
                    }

                    if (searchVal) {
                        var nombreStr = String(agencia.nombre || agencia.nombre_agencia || '').toLowerCase();
                        var dirStr = String(agencia.direccion || '').toLowerCase();
                        if (nombreStr.indexOf(searchVal) === -1 && dirStr.indexOf(searchVal) === -1) {
                            return false;
                        }
                    }

                    return true;
                });

                renderMarkers(filtered);
            }

            if (filtroMarca) filtroMarca.addEventListener('change', applyFilters);
            if (filtroDepto) filtroDepto.addEventListener('change', applyFilters);
            if (filtroBusqueda) filtroBusqueda.addEventListener('input', applyFilters);

            // Initial render from embedded data
            renderMarkers(allAgencias);

            // Also synchronize with Geolocation JSON API if available
            fetch('<?= site_url('api/agencias/geolocalizacion') ?>')
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data && data.status === 'ok' && Array.isArray(data.agencias)) {
                        allAgencias = data.agencias;
                        applyFilters();
                    }
                })
                .catch(function (err) {
                    console.log('API sync skipped, using embedded server data.');
                });

            function escapeHtml(text) {
                if (!text) return '';
                var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
                return String(text).replace(/[&<>"']/g, function (m) { return map[m]; });
            }
        });
    </script>

</body>
</html>
