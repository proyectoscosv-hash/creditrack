<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CrediTrack - Gestión de Agencias</title>
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
            max-width: 1200px;
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
            max-width: 1200px;
            width: 100%;
            margin: 2rem auto;
            padding: 0 1.5rem;
            flex: 1;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .page-title {
            color: #023373;
            font-size: 1.8rem;
            font-weight: 700;
            border-left: 5px solid #BF0426;
            padding-left: 0.75rem;
        }
        .btn-create {
            background-color: #0511F2;
            color: #ffffff;
            text-decoration: none;
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            font-weight: 600;
            display: inline-block;
            transition: opacity 0.2s;
        }
        .btn-create:hover { opacity: 0.9; }

        .filters-card {
            background-color: #ffffff;
            border: 1px solid var(--color-border);
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
        }
        .filters-form {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            flex: 1;
            min-width: 180px;
        }
        .filter-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #023373;
        }
        .filter-input, .filter-select {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--color-border);
            border-radius: 6px;
            font-size: 0.9rem;
        }
        .btn-filter {
            background-color: #023373;
            color: #ffffff;
            border: none;
            padding: 0.55rem 1.2rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
        }

        .alert-success { background-color: #d1fae5; color: #065f46; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .alert-error { background-color: #fee2e2; color: #991b1b; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }

        .table-container {
            background-color: #ffffff;
            border: 1px solid var(--color-border);
            border-radius: 8px;
            overflow-x: auto;
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
        }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th {
            background-color: #023373;
            color: #ffffff;
            padding: 0.75rem 1rem;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--color-border);
            font-size: 0.9rem;
        }
        tr:hover { background-color: #f8fafc; }
        .badge-active { background-color: #dcfce7; color: #166534; padding: 0.2rem 0.6rem; border-radius: 9999px; font-weight: 600; font-size: 0.75rem; }
        .badge-inactive { background-color: #fee2e2; color: #991b1b; padding: 0.2rem 0.6rem; border-radius: 9999px; font-weight: 600; font-size: 0.75rem; }
        .brand-pill { background-color: #e0f2fe; color: #0369a1; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.75rem; margin-right: 0.25rem; display: inline-block; margin-bottom: 0.2rem; }
        .actions-cell { display: flex; gap: 0.5rem; align-items: center; }
        .btn-view { color: #023373; text-decoration: none; font-weight: 600; }
        .btn-edit { color: #0511F2; text-decoration: none; font-weight: 600; }
        .btn-disable { background: none; border: none; color: #BF0426; font-weight: 600; cursor: pointer; padding: 0; font-size: 0.9rem; }

        .empty-state {
            padding: 3rem 1.5rem;
            text-align: center;
            color: #64748b;
            font-size: 1.1rem;
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
    </style>
</head>
<body>

    <!-- Shared Authenticated Header Partial -->
    <?= $this->include('layouts/app_header') ?>

    <!-- Main Workspace -->
    <main class="main-container">

        <div class="page-header">
            <h1 class="page-title">Gestión de agencias - <?= esc($paisNombre) ?></h1>
            <a href="<?= site_url('agencias/crear') ?>" class="btn-create">+ Registrar Nueva Agencia</a>
        </div>

        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert-error"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>

        <!-- Search & Filter Form -->
        <section class="filters-card">
            <form action="<?= site_url('agencias') ?>" method="GET" class="filters-form">
                <div class="filter-group">
                    <label for="q" class="filter-label">Buscar agencia</label>
                    <input type="text" name="q" id="q" class="filter-input" placeholder="Nombre o dirección..." value="<?= esc($filtros['q'] ?? '') ?>">
                </div>

                <div class="filter-group">
                    <label for="estado" class="filter-label">Estado</label>
                    <select name="estado" id="estado" class="filter-select">
                        <option value="activas" <?= ($filtros['estado'] ?? 'activas') === 'activas' ? 'selected' : '' ?>>Activas</option>
                        <option value="inactivas" <?= ($filtros['estado'] ?? '') === 'inactivas' ? 'selected' : '' ?>>Inactivas</option>
                        <option value="todas" <?= ($filtros['estado'] ?? '') === 'todas' ? 'selected' : '' ?>>Todas</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="id_departamento" class="filter-label">Departamento</label>
                    <select name="id_departamento" id="id_departamento" class="filter-select">
                        <option value="">Todos los departamentos</option>
                        <?php foreach ($departamentos as $depto): ?>
                            <?php $dId = (int) ($depto['id_departamento'] ?? $depto['Id_departamento']); ?>
                            <option value="<?= $dId ?>" <?= ((int) ($filtros['id_departamento'] ?? 0)) === $dId ? 'selected' : '' ?>>
                                <?= esc($depto['departamento'] ?? $depto['Departamento']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="id_marca" class="filter-label">Marca</label>
                    <select name="id_marca" id="id_marca" class="filter-select">
                        <option value="">Todas las marcas</option>
                        <?php foreach ($marcas as $marca): ?>
                            <?php $mId = (int) ($marca['id_marca'] ?? $marca['Id_marca']); ?>
                            <option value="<?= $mId ?>" <?= ((int) ($filtros['id_marca'] ?? 0)) === $mId ? 'selected' : '' ?>>
                                <?= esc($marca['marca'] ?? $marca['Marca']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn-filter">Filtrar</button>
            </form>
        </section>

        <!-- Agencies Table -->
        <section class="table-container">
            <?php if (empty($agencias)): ?>
                <div class="empty-state">
                    <p>No se encontraron agencias registradas con los filtros seleccionados.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Agencia</th>
                            <th>Dirección</th>
                            <th>Ubicación</th>
                            <th>Marcas</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agencias as $agencia): ?>
                            <?php $id = (int) ($agencia['idagencia'] ?? $agencia['Idagencia']); ?>
                            <tr>
                                <td><?= $id ?></td>
                                <td><strong><?= esc($agencia['nombre_agencia']) ?></strong></td>
                                <td><?= esc($agencia['direccion']) ?></td>
                                <td>
                                    <?= esc($agencia['departamento_nombre'] ?? '') ?>
                                    <?php if (!empty($agencia['municipio_nombre'])): ?>
                                        / <?= esc($agencia['municipio_nombre']) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php foreach ($agencia['marca_nombres'] ?? [] as $mName): ?>
                                        <span class="brand-pill"><?= esc($mName) ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <?php if ((int) $agencia['activo'] === 1): ?>
                                        <span class="badge-active">Activa</span>
                                    <?php else: ?>
                                        <span class="badge-inactive">Inactiva</span>
                                        <?php if (!empty($agencia['motivo_deshabilitacion'])): ?>
                                            <div style="font-size:0.75rem; color:#64748b; margin-top:2px;">
                                                <?= html_entity_decode((string) $agencia['motivo_deshabilitacion'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="actions-cell">
                                    <a href="<?= site_url('agencias/ver/' . $id) ?>" class="btn-view">Ver</a>
                                    <?php if ((int) $agencia['activo'] === 1): ?>
                                        | <a href="<?= site_url('agencias/editar/' . $id) ?>" class="btn-edit">Editar</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

    </main>

    <footer class="footer">
        <p>© 2026 CrediTrack Inc. - Gestión y Administración Territorial de Agencias.</p>
    </footer>

</body>
</html>
