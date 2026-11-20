<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CrediTrack - <?= $mode === 'crear' ? 'Registrar Agencia' : 'Modificar Agencia' ?></title>
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
        .form-card {
            background-color: #ffffff;
            border: 1px solid var(--color-border);
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .form-title {
            color: #023373;
            font-size: 1.6rem;
            font-weight: 700;
            border-left: 5px solid #BF0426;
            padding-left: 0.75rem;
            margin-bottom: 1.5rem;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }
        @media (max-width: 768px) {
            .grid-2 { grid-template-columns: 1fr; }
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            margin-bottom: 1.25rem;
        }
        .form-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #023373;
        }
        .form-label .required { color: #BF0426; }
        .form-input, .form-select, .form-textarea {
            padding: 0.65rem 0.85rem;
            border: 1px solid var(--color-border);
            border-radius: 6px;
            font-size: 0.95rem;
            font-family: inherit;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #0511F2;
            box-shadow: 0 0 0 3px rgba(5, 17, 242, 0.15);
        }
        .form-textarea { min-height: 80px; resize: vertical; }

        .brands-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 0.75rem;
            background-color: #f8fafc;
            border: 1px solid var(--color-border);
            border-radius: 6px;
            padding: 1rem;
        }
        .brand-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            cursor: pointer;
        }

        #map {
            height: 380px;
            width: 100%;
            border-radius: 8px;
            border: 1px solid var(--color-border);
            margin-top: 0.5rem;
        }
        .map-hint {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 0.4rem;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--color-border);
        }
        .btn-submit {
            background-color: #0511F2;
            color: #ffffff;
            border: none;
            padding: 0.75rem 1.75rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-submit:hover { background-color: #023373; }
        .btn-cancel {
            background-color: #e2e8f0;
            color: #334155;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 1rem;
            display: inline-block;
        }
        .alert-error { background-color: #fee2e2; color: #991b1b; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1.5rem; }
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

        <div class="form-card">
            <h1 class="form-title"><?= $mode === 'crear' ? 'Registrar Nueva Agencia' : 'Modificar Agencia' ?></h1>

            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert-error"><?= esc(session()->getFlashdata('error')) ?></div>
            <?php endif; ?>

            <!-- Metadata for dynamic country district requirement -->
            <div id="requiere-distrito-container" data-requiere-distrito="<?= $requiere_distrito ? '1' : '0' ?>" style="display: none;">
                <span id="requiere_distrito_flag"><?= $requiere_distrito ? '1' : '0' ?></span>
            </div>

            <form action="<?= esc($actionUrl) ?>" method="POST" id="agenciaForm">
                <?= csrf_field() ?>

                <!-- General Info -->
                <div class="grid-2">
                    <div class="form-group">
                        <label for="nombre_agencia" class="form-label">Nombre de la Agencia <span class="required">*</span></label>
                        <input type="text" name="nombre_agencia" id="nombre_agencia" class="form-input" 
                               value="<?= esc($agency['nombre_agencia'] ?? '') ?>" required minlength="3" maxlength="150"
                               placeholder="Ej. Agencia Central Santa Tecla">
                    </div>

                    <div class="form-group">
                        <label for="id_pais" class="form-label">País Asignado <span class="required">*</span></label>
                        <select name="id_pais" id="id_pais" class="form-select" required>
                            <option value="<?= esc($pais['id_pais'] ?? $pais['Id_pais']) ?>" selected>
                                <?= esc($pais['pais'] ?? $pais['Pais']) ?>
                            </option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="direccion" class="form-label">Dirección Física Completa <span class="required">*</span></label>
                    <textarea name="direccion" id="direccion" class="form-textarea" required 
                              placeholder="Calle principal, número de local, referencias..."><?= esc($agency['direccion'] ?? '') ?></textarea>
                </div>

                <!-- Geographic Hierarchy (Cascading Selectors) -->
                <div class="grid-2">
                    <div class="form-group">
                        <label for="id_departamento" class="form-label">Departamento <span class="required">*</span></label>
                        <select name="id_departamento" id="id_departamento" class="form-select" required>
                            <option value="">-- Seleccione un departamento --</option>
                            <?php foreach ($departamentos as $depto): ?>
                                <?php $dId = (int) ($depto['id_departamento'] ?? $depto['Id_departamento']); ?>
                                <option value="<?= $dId ?>" <?= ((int) ($agency['id_departamento'] ?? 0)) === $dId ? 'selected' : '' ?>>
                                    <?= esc($depto['departamento'] ?? $depto['Departamento']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="id_municipio" class="form-label">Municipio <span class="required">*</span></label>
                        <select name="id_municipio" id="id_municipio" class="form-select" required>
                            <option value="">-- Seleccione un municipio --</option>
                            <?php if (!empty($municipios)): ?>
                                <?php foreach ($municipios as $muni): ?>
                                    <?php $mId = (int) ($muni['id_municipio'] ?? $muni['Id_municipio']); ?>
                                    <option value="<?= $mId ?>" <?= ((int) ($agency['id_municipio'] ?? 0)) === $mId ? 'selected' : '' ?>>
                                        <?= esc($muni['municipio'] ?? $muni['Municipio']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" id="distrito-group" style="<?= $requiere_distrito ? '' : 'display:none;' ?>">
                    <label for="id_distrito" class="form-label">
                        Distrito <?= $requiere_distrito ? '<span class="required">*</span>' : '<span style="color:#64748b; font-weight:normal;">(Opcional)</span>' ?>
                    </label>
                    <select name="id_distrito" id="id_distrito" class="form-select" <?= $requiere_distrito ? 'required' : '' ?>>
                        <option value="">-- Seleccione un distrito --</option>
                        <?php if (!empty($distritos)): ?>
                            <?php foreach ($distritos as $dist): ?>
                                <?php $distId = (int) ($dist['id_distrito'] ?? $dist['Id_distrito']); ?>
                                <option value="<?= $distId ?>" <?= ((int) ($agency['id_distrito'] ?? 0)) === $distId ? 'selected' : '' ?>>
                                    <?= esc($dist['distrito'] ?? $dist['Distrito']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Brands Associated -->
                <div class="form-group">
                    <label class="form-label">Marcas de Motocicletas Asociadas <span class="required">*</span></label>
                    <div class="brands-container">
                        <?php 
                            $selectedMarcaIds = [];
                            if (!empty($agency['marca_ids'])) {
                                $selectedMarcaIds = array_map('intval', (array) $agency['marca_ids']);
                            } elseif (!empty($agency['marcas'])) {
                                foreach ($agency['marcas'] as $mItem) {
                                    if (is_array($mItem)) {
                                        $selectedMarcaIds[] = (int) ($mItem['id_marca'] ?? $mItem['Id_marca']);
                                    } elseif (is_numeric($mItem)) {
                                        $selectedMarcaIds[] = (int) $mItem;
                                    }
                                }
                            }
                        ?>
                        <?php foreach ($marcas as $marca): ?>
                            <?php 
                                $marcaId = (int) ($marca['id_marca'] ?? $marca['Id_marca']);
                                $checked = in_array($marcaId, $selectedMarcaIds, true);
                            ?>
                            <label class="brand-label">
                                <input type="checkbox" name="marcas[]" value="<?= $marcaId ?>" <?= $checked ? 'checked' : '' ?>>
                                <span><?= esc($marca['marca'] ?? $marca['Marca']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Coordinates & Interactive Leaflet Map -->
                <div class="grid-2">
                    <div class="form-group">
                        <label for="latitud" class="form-label">Latitud <span class="required">*</span></label>
                        <input type="text" name="latitud" id="latitud" class="form-input" 
                               value="<?= esc((isset($agency['latitud']) && $agency['latitud'] !== null && (string)$agency['latitud'] !== 'null' && (string)$agency['latitud'] !== 'NaN') ? (string)$agency['latitud'] : '') ?>" required
                               placeholder="Ej. 13.69294000">
                    </div>

                    <div class="form-group">
                        <label for="longitud" class="form-label">Longitud <span class="required">*</span></label>
                        <input type="text" name="longitud" id="longitud" class="form-input" 
                               value="<?= esc((isset($agency['longitud']) && $agency['longitud'] !== null && (string)$agency['longitud'] !== 'null' && (string)$agency['longitud'] !== 'NaN') ? (string)$agency['longitud'] : '') ?>" required
                               placeholder="Ej. -89.21819000">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Selección Geográfica en Mapa</label>
                    <div id="map"></div>
                    <p class="map-hint">Haga clic sobre el mapa para fijar automáticamente las coordenadas de latitud y longitud.</p>
                </div>

                <!-- Action Buttons -->
                <div class="form-actions">
                    <a href="<?= site_url('agencias') ?>" class="btn-cancel">Cancelar</a>
                    <button type="submit" class="btn-submit"><?= $mode === 'crear' ? 'Guardar Agencia' : 'Actualizar Agencia' ?></button>
                </div>
            </form>
        </div>

    </main>

    <footer class="footer">
        <p>© 2026 CrediTrack Inc. - Gestión y Administración Territorial de Agencias.</p>
    </footer>

    <!-- Local Vendored Leaflet JS -->
    <script src="<?= base_url('assets/leaflet/leaflet.js') ?>"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Geographic cascading endpoints: api/geo/departamentos, api/geo/municipios, api/geo/distritos
            var geoEndpoints = {
                departamentos: '<?= site_url('api/geo/departamentos') ?>',
                municipios: '<?= site_url('api/geo/municipios') ?>',
                distritos: '<?= site_url('api/geo/distritos') ?>'
            };

            // Cascading Geographic Selectors
            var deptoSelect = document.getElementById('id_departamento');
            var muniSelect = document.getElementById('id_municipio');
            var distSelect = document.getElementById('id_distrito');
            var reqDistMeta = document.getElementById('requiere-distrito-container');
            var requiereDistrito = reqDistMeta && reqDistMeta.getAttribute('data-requiere-distrito') === '1';

            if (deptoSelect) {
                deptoSelect.addEventListener('change', function () {
                    var deptoId = this.value;
                    muniSelect.innerHTML = '<option value="">-- Seleccione un municipio --</option>';
                    if (distSelect) {
                        distSelect.innerHTML = '<option value="">-- Seleccione un distrito --</option>';
                    }
                    if (!deptoId) return;

                    fetch('<?= site_url('api/geo/municipios') ?>?id_departamento=' + encodeURIComponent(deptoId))
                        .then(function (res) { return res.json(); })
                        .then(function (data) {
                            if (data.status === 'ok' && Array.isArray(data.municipios)) {
                                data.municipios.forEach(function (muni) {
                                    var opt = document.createElement('option');
                                    opt.value = muni.id_municipio;
                                    opt.textContent = muni.municipio;
                                    muniSelect.appendChild(opt);
                                });
                            }
                        })
                        .catch(function (err) { console.error('Error cargando municipios:', err); });
                });
            }

            if (muniSelect && distSelect) {
                muniSelect.addEventListener('change', function () {
                    var muniId = this.value;
                    distSelect.innerHTML = '<option value="">-- Seleccione un distrito --</option>';
                    if (!muniId) return;

                    fetch('<?= site_url('api/geo/distritos') ?>?id_municipio=' + encodeURIComponent(muniId))
                        .then(function (res) { return res.json(); })
                        .then(function (data) {
                            if (data.status === 'ok' && Array.isArray(data.distritos)) {
                                data.distritos.forEach(function (dist) {
                                    var opt = document.createElement('option');
                                    opt.value = dist.id_distrito;
                                    opt.textContent = dist.distrito;
                                    distSelect.appendChild(opt);
                                });
                            }
                        })
                        .catch(function (err) { console.error('Error cargando distritos:', err); });
                });
            }

            // Interactive Leaflet Map & Click-to-Set Coordinates
            var latInput = document.getElementById('latitud');
            var lngInput = document.getElementById('longitud');

            // Country default center
            <?php
            $defCountryLat = 13.69294;
            $defCountryLng = -89.21819;
            $cpIdForm = (int) ($pais['id_pais'] ?? $pais['Id_pais'] ?? session('id_pais') ?? 1);
            if ($cpIdForm === 2) {
                $defCountryLat = 14.63491;
                $defCountryLng = -90.50688;
            } elseif ($cpIdForm === 3) {
                $defCountryLat = 14.07227;
                $defCountryLng = -87.19213;
            }
            ?>
            var defaultCountryLat = <?= (float) $defCountryLat ?>;
            var defaultCountryLng = <?= (float) $defCountryLng ?>;

            var latVal = latInput ? latInput.value.trim() : '';
            var lngVal = lngInput ? lngInput.value.trim() : '';
            var initialLat = latVal !== '' && latVal !== 'null' && latVal !== 'NaN' ? parseFloat(latVal) : NaN;
            var initialLng = lngVal !== '' && lngVal !== 'null' && lngVal !== 'NaN' ? parseFloat(lngVal) : NaN;

            var hasValidCoords = !isNaN(initialLat) && !isNaN(initialLng) && (initialLat !== 0 || initialLng !== 0) && initialLat >= -90 && initialLat <= 90 && initialLng >= -180 && initialLng <= 180;
            var centerLat = hasValidCoords ? initialLat : defaultCountryLat;
            var centerLng = hasValidCoords ? initialLng : defaultCountryLng;
            var initialZoom = hasValidCoords ? 15 : 12;

            var map = L.map('map').setView([centerLat, centerLng], initialZoom);

            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            var marker = null;
            if (hasValidCoords) {
                marker = L.marker([initialLat, initialLng], { draggable: true }).addTo(map);
                marker.on('dragend', function (ev) {
                    var pos = ev.target.getLatLng();
                    if (latInput) latInput.value = pos.lat.toFixed(8);
                    if (lngInput) lngInput.value = pos.lng.toFixed(8);
                });
            }

            // Click-to-set logic updating latitud/longitud and marker position
            map.on('click', function (e) {
                var lat = e.latlng.lat.toFixed(8);
                var lng = e.latlng.lng.toFixed(8);

                if (latInput) latInput.value = lat;
                if (lngInput) lngInput.value = lng;

                if (marker) {
                    marker.setLatLng(e.latlng);
                } else {
                    marker = L.marker(e.latlng, { draggable: true }).addTo(map);
                    marker.on('dragend', function (ev) {
                        var pos = ev.target.getLatLng();
                        if (latInput) latInput.value = pos.lat.toFixed(8);
                        if (lngInput) lngInput.value = pos.lng.toFixed(8);
                    });
                }
            });
        });
    </script>

</body>
</html>
