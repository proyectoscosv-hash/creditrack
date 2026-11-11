# Milestone 3 (M3_views_ui) Automated Test Suite Investigation & UI Specifications

**Agent**: `explorer_m3_3`  
**Date**: 2026-11-10  
**Target Views**: `app/Views/login.php`, `app/Views/home.php`  
**Referenced Test Files**:
- `tests/_support/AuthTestCase.php`
- `tests/Feature/ViewUITest.php` (25 tests)
- `tests/Feature/WorkflowIntegrationTest.php` (17 tests total, 5 directly asserting views)

---

## 1. Executive Summary

Milestone 3 requires the implementation of two HTML5 views: `app/Views/login.php` and `app/Views/home.php`. The automated test suite applies rigorous verification across functional redirection, user session display, security attributes, responsive HTML structure, corporate 5-color palette adherence, manual author attribution, and XSS sanitization.

All tests run in-memory using SQLite without accessing or requiring `.env`. Both views must simultaneously satisfy all regular expressions and assertion methods defined in `tests/Feature/ViewUITest.php` and `tests/Feature/WorkflowIntegrationTest.php`.

---

## 2. Test Suite Inventory

### 2.1 `tests/_support/AuthTestCase.php`
Defines the base test case class, database seeding (`TestAuthSeeder`), and the corporate palette constant and assertion:
```php
protected const PALETTE = [
    '#BF0426',
    '#0511F2',
    '#023373',
    '#A61717',
    '#8D253C',
];

protected function assertCorporatePalettePresent(string $html): void
{
    foreach (self::PALETTE as $color) {
        $this->assertStringContainsStringIgnoringCase(
            $color,
            $html,
            "Corporate palette color {$color} was not found in response HTML."
        );
    }
}
```
**Key Takeaway**: Every single one of these 5 hex strings MUST be present in the returned HTML (case-insensitively) for both `login.php` and `home.php`.

---

### 2.2 `tests/Feature/ViewUITest.php` (25 Tests)

#### Tier 1: Feature Coverage (Happy Path & Core Requirements)
1. `testT1Redir01_Http302RedirectionToHomePhpPostAuth`: Verifies successful login issues HTTP 302 redirecting to `home.php` (`/home\.php/i`).
2. `testT1Redir02_SuccessfulAuthConfirmationBannerOnHomePhp`: Verifies `home.php` renders a prominent confirmation banner matching `/(¡?inicio de sesi[oó]n exitoso!?|bienvenido|sesi[oó]n iniciada)/i`.
3. `testT1Redir03_ActiveSessionHealthStatusBadgeOnHomePhp`: Verifies `home.php` renders session health status matching `/(estado de la sesi[oó]n:\s*activa|sesi[oó]n\s*activa)/i`.
4. `testT1Redir04_UserIdentityRenderingOnHomePhp`: Verifies `home.php` renders employee code (`EMP-ADM-01`), full name (`Carlos Admin`), and username (`admin_sv`).
5. `testT1Redir05_AssignedRoleAndCountryConfirmationBadges`: Verifies `home.php` displays role badge `/(rol|rol asignado):?\s*supervisor/i` and country badge `/(pa[ií]s|pa[ií]s asignado):?\s*guatemala/i`.
6. `testT1Redir06_AccessBoundaryDeclarationStatementOnHomePhp`: Verifies `home.php` displays access boundary statement `/(acceso restringido.*honduras|l[ií]mite territorial.*honduras|operaciones de honduras)/i`.
7. `testT1Ui01_CorporateColorBF0426PresenceInViews`: Verifies `/#BF0426/i` in both `login.php` and `home.php`.
8. `testT1Ui02_CorporateColor0511F2PresenceInViews`: Verifies `/#0511F2/i` in both `login.php` and `home.php`.
9. `testT1Ui03_CorporateColor023373PresenceInViews`: Verifies `/#023373/i` in both `login.php` and `home.php`.
10. `testT1Ui04_CorporateColorA61717PresenceInViews`: Verifies `/#A61717/i` in both `login.php` and `home.php`.
11. `testT1Ui05_CorporateColor8D253CPresenceInViews`: Verifies `/#8D253C/i` in both `login.php` and `home.php`.
12. `testT1Ui06_ManualAuthorAttributionMarkupOnLoginView`: Verifies `/(diseñad[oa]|desarrollad[oa]|diseñadores|equipo de desarrollo|autores)/i` in `login.php`.
13. `testT1Ui07_ManualAuthorAttributionMarkupOnLandingView`: Verifies `/(diseñad[oa]|desarrollad[oa]|diseñadores|equipo de desarrollo|autores)/i` in `home.php`.

#### Tier 2: Boundary & Corner Cases
14. `testT2Redir01_DirectRouteParityHomePhpVsHome`: Verifies both `/home.php` and `/home` render identically with active session.
15. `testT2Redir02_HtmlEscapingAndXssPreventionInProfile`: Injects `<script>alert(1)</script>` into profile `nombre`. Asserts raw `<script>` is NOT present, and escaped string `&lt;script&gt;alert(1)&lt;/script&gt;` is present.
16. `testT2Redir03_DirectAccessToHomePhpWithExpiredSession`: Expired session on `/home.php` redirects to `/login/i`.
17. `testT2Redir04_LogoutActionFunctionalLinkPresence`: `home.php` renders logout link/form `/(href="[^"]*logout[^"]*"|action="[^"]*logout[^"]*")/i` with label `/(cerrar sesi[oó]n|salir|logout)/i`.
18. `testT2Redir05_CircularRedirectionLoopPrevention`: Unauthenticated `/home.php` redirects to `/login`, which returns 200 OK without loops.
19. `testT2Redir06_MissingSessionMetadataFallbackGracefulness`: Null `pais_nombre` does not crash `home.php`; returns 200 OK and renders name.
20. `testT2Ui01_Complete5ColorCorporatePaletteAssertion`: Runs `assertCorporatePalettePresent()` against both `login.php` and `home.php`.
21. `testT2Ui02_AuthorAttributionSemanticVisibility`: Login view attribution must NOT be hidden with inline `display: none` or `visibility: hidden` (`/style\s*=\s*"[^"]*(display\s*:\s*none|visibility\s*:\s*hidden)[^"]*"(?:[^>]*)(?:diseñad|desarrollad|equipo de desarrollo)/i`), and container must match `/(footer|<div[^>]*(attribution|autor|creditos|designer)[^>]*>|diseñad|desarrollad)/i`.
22. `testT2Ui03_StaticManualAttributionVerification`: Raw response body of `login.php` contains `/(diseñad[oa]|desarrollad[oa]|diseñadores|equipo de desarrollo|autores)/i`.
23. `testT2Ui04_FlashErrorMessageVisualStylingCompliance`: Following bad password submission, `login.php` alert incorporates `/(#BF0426|#A61717)/i`.
24. `testT2Ui05_ViewportMetaAndMobileResponsivenessMarkup`: Both `login.php` and `home.php` declare `<meta name="viewport" content="width=device-width, initial-scale=1.0">` matching `/<meta[^>]+name=["\']viewport["\'][^>]+content=["\'][^"\']*width=device-width/i`.
25. `testT2Ui06_VisualColorContrastAndAccessibleHierarchy`: `login.php` maintains light text on dark background matching `/(color:\s*(#fff(fff)?|white)|background[^;]+(#023373|#8D253C|#BF0426|#A61717))/i`.

---

### 2.3 `tests/Feature/WorkflowIntegrationTest.php` (View-Relevant Tests)

- **`testT3Pair05_LockoutUIWithCorporatePaletteAndAttribution`**:
  After 3 failed logins for `asesor_sv`, account locks. Test requests `GET login`, then asserts:
  1. `assertCorporatePalettePresent($body)` (all 5 colors must be present in lockout UI).
  2. `assertMatchesRegularExpression('/(diseñad[oa]|desarrollad[oa]|diseñadores|equipo de desarrollo|autores)/i', $body)` (author attribution must be present).
- **`testT3Pair10_SqlInjectionPayloadAttemptTrackingIntegrity`**:
  Submits SQL injection attempt; on redirect to `login`, asserts all 5 corporate palette colors are present.
- **`testT3Pair11_SessionTimeoutSimulationProtectedGuard`**:
  Simulates session expiry on `home.php`; on redirect to `login`, asserts all 5 corporate palette colors are present.
- **`testT4Scen01_FieldAgentCompleteShiftLifecycle`**:
  End-to-end lifecycle:
  1. `GET login` -> asserts `assertCorporatePalettePresent()`.
  2. `POST login` with `asesor_sv` -> 302 to `home.php`.
  3. `GET home.php` -> asserts sees `EMP-ASE-01`, `Juan Perez`, `Asesor`, `El Salvador`.
  4. `GET logout` -> redirects to `login`.
  5. `GET home.php` post-logout -> blocked and redirected to `login`.
- **`testT4Scen02_RegionalSupervisorMultiCountryGovernance`**:
  Supervisor `supervisor_gt` logs in:
  1. `GET home.php` -> asserts sees `Supervisor` and `Guatemala`.
  2. **CRITICAL**: `$this->assertStringNotContainsString('El Salvador', $homeRes->getBody());`
  This proves that `home.php` **must NOT have any hardcoded mention of "El Salvador"** anywhere in the view (neither in text, examples, menus, attribution, nor comments)!

---

## 3. Exact Regular Expressions Matrix

| Feature / UI Element | Exact Regular Expression Used in Tests | Source Test & Line | Matching Implementation Example |
|---|---|---|---|
| **Success Banner** | `/(¡?inicio de sesi[oó]n exitoso!?\|bienvenido\|sesi[oó]n iniciada)/i` | `ViewUITest.php:69` | `¡Inicio de sesión exitoso! Bienvenido al sistema CrediTrack.` |
| **Session Health Badge** | `/(estado de la sesi[oó]n:\s*activa\|sesi[oó]n\s*activa)/i` | `ViewUITest.php:94` | `Estado de la sesión: Activa` |
| **Role Badge** | `/(rol\|rol asignado):?\s*supervisor/i` (or admin/asesor) | `ViewUITest.php:141` | `Rol asignado: <?= esc($rol) ?>` |
| **Country Badge** | `/(pa[ií]s\|pa[ií]s asignado):?\s*guatemala/i` (or SV/HN) | `ViewUITest.php:146` | `País asignado: <?= esc($pais_nombre) ?>` |
| **Access Boundary Statement** | `/(acceso restringido.*honduras\|l[ií]mite territorial.*honduras\|operaciones de honduras)/i` | `ViewUITest.php:171` | `Acceso restringido exclusivamente a información y operaciones de <?= esc($pais_nombre) ?> con privilegios de <?= esc($rol) ?>.` |
| **Attribution Content** | `/(diseñad[oa]\|desarrollad[oa]\|diseñadores\|equipo de desarrollo\|autores)/i` | `ViewUITest.php:342, 366, 573`, `WorkflowIntegrationTest.php:179` | `Sistema diseñado y desarrollado por el equipo de desarrollo de CrediTrack.` |
| **Attribution Container** | `/(footer\|<div[^>]*(attribution\|autor\|creditos\|designer)[^>]*>\|diseñad\|desarrollad)/i` | `ViewUITest.php:556` | `<footer class="attribution">...</footer>` |
| **Attribution Visibility (Negative)** | `/style\s*=\s*"[^"]*(display\s*:\s*none\|visibility\s*:\s*hidden)[^"]*"(?:[^>]*)(?:diseñad\|desarrollad\|equipo de desarrollo)/i` | `ViewUITest.php:551` | Must NOT have inline `display: none` or `visibility: hidden` |
| **Flash Error Styling** | `/(#BF0426\|#A61717)/i` | `ViewUITest.php:596` | Inline style or CSS class: `background-color: #BF0426; border-color: #A61717;` |
| **Responsive Viewport** | `/<meta[^>]+name=["\']viewport["\'][^>]+content=["\'][^"\']*width=device-width/i` | `ViewUITest.php:611, 627` | `<meta name="viewport" content="width=device-width, initial-scale=1.0">` |
| **Visual Contrast** | `/(color:\s*(#fff(fff)?\|white)\|background[^;]+(#023373\|#8D253C\|#BF0426\|#A61717))/i` | `ViewUITest.php:644` | `background-color: #023373; color: #ffffff;` |
| **Logout Link / Form** | Action: `/(href="[^"]*logout[^"]*"\|action="[^"]*logout[^"]*")/i`<br>Label: `/(cerrar sesi[oó]n\|salir\|logout)/i` | `ViewUITest.php:471, 476` | `<a href="<?= site_url('logout') ?>" class="btn-logout">Cerrar Sesión</a>` |
| **Redirection to home.php** | `/home\.php/i` | `ViewUITest.php:44`, `WorkflowIntegrationTest.php:75` | Target of 302 redirect: `site_url('home.php')` |
| **Redirection to login** | `/login/i` | `ViewUITest.php:450`, `WorkflowIntegrationTest.php:49` | Target of 302 redirect: `site_url('login')` |

---

## 4. Corporate 5-Color Palette Mapping

The corporate palette specified in Requirement R4 contains exactly 5 hex colors:
1. `#BF0426` (Corporate Crimson / Primary Alert)
2. `#0511F2` (Corporate Bright Blue / Action Accent)
3. `#023373` (Corporate Navy Blue / Brand Foundation)
4. `#A61717` (Corporate Brick Red / Security Border)
5. `#8D253C` (Corporate Burgundy / Profile & Attribution Badge)

### View Element Assignments

| Hex Code | Semantic Role | Placement in `login.php` | Placement in `home.php` |
|---|---|---|---|
| `#023373` | Brand Foundation | Header / Card top border, card titles | Main navigation / header bar, card header background |
| `#0511F2` | Action Accent | Login submit button, input focus ring | Active session badge background, interactive links |
| `#BF0426` | Primary Alert / Danger | Flash error alert background / text | Logout button background, boundary warning |
| `#A61717` | Security Accent / Dark Red | Flash alert border, card accent line | Access boundary card border, boundary badge |
| `#8D253C` | Secondary Badge / Wine | Attribution footer accent, logo subtitle | Assigned Role badge background, secondary metadata |

Both views must include a `<style>` block where all five hex strings are explicitly defined and used.

---

## 5. Security & Edge Case Requirements

### 5.1 Zero .env Interaction
- The test suite runs SQLite in-memory (`$tests` DB group in `app/Config/Database.php`).
- Neither view should ever read from `$_ENV`, `env()`, or touch `.env`.

### 5.2 XSS Sanitization (`testT2Redir02`)
- In `home.php`, all user-provided data must be safely escaped using CodeIgniter's `esc()` helper:
  - `<?= esc($nombre) ?>`
  - `<?= esc($usuario) ?>`
  - `<?= esc($codigo_empleado) ?>`
  - `<?= esc($rol) ?>`
  - `<?= esc($pais_nombre ?? '') ?>`
- A test injection of `'Juan <script>alert(1)</script>'` must be rendered as `'Juan &lt;script&gt;alert(1)&lt;/script&gt;'` without raw `<script>` tags.

### 5.3 Cross-Country Data Segregation (`testT4Scen02`)
- **NEVER** include the literal string `"El Salvador"` anywhere statically in `home.php`.
- If a user from Guatemala (`supervisor_gt`) accesses `home.php`, `testT4Scen02` asserts that the response body **does NOT contain "El Salvador"**.
- Country names must appear **only** via `<?= esc($pais_nombre) ?>`.

### 5.4 Graceful Fallback for Missing Session Metadata (`testT2Redir06`)
- `testT2Redir06` tests a session with `'pais_nombre' => null`.
- `home.php` must not trigger PHP notices or crash when `$pais_nombre` is null or empty.
- Fallback pattern: `!empty($pais_nombre) ? esc($pais_nombre) : 'No especificado'`

### 5.5 CSRF Field and Old Input in Login
- Form must include `<?= csrf_field() ?>`.
- Input field `name="usuario"` must retain previous input on failure:
  `value="<?= esc(old('usuario', '')) ?>"`
- Input field `name="contrasena"`, `type="password"`.

### 5.6 Flash Message Display in Login
- `session('error')`: Lockout message, invalid credentials, or unauthorized access redirect messages.
- `session('warning')`: Remaining attempts warnings ("Intentos fallidos: X de 3").
- `session('message')`: Logout success message ("Sesión cerrada exitosamente.").

---

## 6. Implementation Specifications for the Implementer

### 6.1 `app/Views/login.php` Template Structure
```html
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CrediTrack - Iniciar Sesión</title>
    <style>
        :root {
            --color-primary-red: #BF0426;
            --color-action-blue: #0511F2;
            --color-navy: #023373;
            --color-brick: #A61717;
            --color-burgundy: #8D253C;
        }
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f9;
            color: #333333;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .header-bar {
            background-color: #023373;
            color: #ffffff;
            padding: 1.5rem 1rem;
            text-align: center;
        }
        .header-bar h1 { margin: 0; font-size: 1.75rem; }
        .header-bar p { margin: 0.25rem 0 0; font-size: 0.9rem; color: #8D253C; }
        .container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .card {
            background: #ffffff;
            border-top: 4px solid #023373;
            border-bottom: 2px solid #8D253C;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(2, 51, 115, 0.15);
            max-width: 420px;
            width: 100%;
            padding: 2rem;
        }
        .alert-error {
            background-color: #BF0426;
            color: #ffffff;
            border: 1px solid #A61717;
            border-radius: 4px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            font-size: 0.9rem;
        }
        .alert-message {
            background-color: #023373;
            color: #ffffff;
            border-radius: 4px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            font-size: 0.9rem;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: #023373;
        }
        .form-control {
            width: 100%;
            box-sizing: border-box;
            padding: 0.75rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1rem;
        }
        .form-control:focus {
            outline: 2px solid #0511F2;
            border-color: #0511F2;
        }
        .btn-submit {
            width: 100%;
            padding: 0.85rem;
            background-color: #0511F2;
            color: #ffffff;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-submit:hover {
            background-color: #023373;
        }
        footer.attribution {
            background-color: #023373;
            color: #ffffff;
            border-top: 3px solid #8D253C;
            text-align: center;
            padding: 1rem;
            font-size: 0.85rem;
            margin-top: auto;
        }
        footer.attribution p { margin: 0; }
    </style>
</head>
<body>
    <header class="header-bar">
        <h1>CrediTrack</h1>
        <p>Sistema de Control de Acceso y Gestión de Créditos</p>
    </header>
    <main class="container">
        <section class="card">
            <h2>Iniciar Sesión</h2>
            <?php if (session('error')): ?>
                <div class="alert alert-error" style="background-color: #BF0426; border-color: #A61717; color: #ffffff;">
                    <?= esc(session('error')) ?>
                </div>
            <?php endif; ?>
            <?php if (session('message')): ?>
                <div class="alert alert-message" style="background-color: #023373; color: #ffffff;">
                    <?= esc(session('message')) ?>
                </div>
            <?php endif; ?>
            <form method="post" action="<?= site_url('login') ?>">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="usuario">Usuario:</label>
                    <input type="text" id="usuario" name="usuario" class="form-control" value="<?= esc(old('usuario', '')) ?>" required autofocus autocomplete="username" />
                </div>
                <div class="form-group">
                    <label for="contrasena">Contraseña:</label>
                    <input type="password" id="contrasena" name="contrasena" class="form-control" required autocomplete="current-password" />
                </div>
                <button type="submit" class="btn-submit">Iniciar Sesión</button>
            </form>
        </section>
    </main>
    <footer class="attribution">
        <p>Sistema diseñado y desarrollado por el equipo de desarrollo de CrediTrack.</p>
    </footer>
</body>
</html>
```

### 6.2 `app/Views/home.php` Template Structure
```html
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CrediTrack - Inicio</title>
    <style>
        :root {
            --color-primary-red: #BF0426;
            --color-action-blue: #0511F2;
            --color-navy: #023373;
            --color-brick: #A61717;
            --color-burgundy: #8D253C;
        }
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f9;
            color: #333333;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .header-bar {
            background-color: #023373;
            color: #ffffff;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #8D253C;
        }
        .header-bar h1 { margin: 0; font-size: 1.5rem; }
        .btn-logout {
            background-color: #BF0426;
            color: #ffffff;
            padding: 0.5rem 1rem;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            border: 1px solid #A61717;
            transition: background-color 0.2s;
        }
        .btn-logout:hover {
            background-color: #A61717;
        }
        .container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1rem;
            flex: 1;
        }
        .banner-success {
            background-color: #023373;
            color: #ffffff;
            border-left: 6px solid #0511F2;
            border-radius: 6px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(2, 51, 115, 0.15);
        }
        .banner-success h2 { margin: 0 0 0.5rem 0; font-size: 1.4rem; }
        .banner-success p { margin: 0; }
        .card {
            background: #ffffff;
            border-radius: 6px;
            border-top: 4px solid #023373;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .badges-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: bold;
            color: #ffffff;
        }
        .badge-session {
            background-color: #0511F2;
        }
        .badge-role {
            background-color: #8D253C;
        }
        .badge-country {
            background-color: #023373;
        }
        .user-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .detail-item {
            background: #f8f9fa;
            padding: 0.75rem 1rem;
            border-radius: 4px;
            border-left: 3px solid #023373;
        }
        .detail-item label {
            display: block;
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 0.25rem;
        }
        .detail-item span {
            font-weight: bold;
            color: #023373;
        }
        .access-boundary {
            background-color: #fff;
            border: 1px solid #A61717;
            border-left: 5px solid #BF0426;
            border-radius: 4px;
            padding: 1rem 1.25rem;
        }
        .access-boundary h3 {
            margin: 0 0 0.5rem 0;
            color: #A61717;
            font-size: 1.1rem;
        }
        .access-boundary p {
            margin: 0;
            color: #333;
            line-height: 1.4;
        }
        footer.attribution {
            background-color: #023373;
            color: #ffffff;
            border-top: 3px solid #8D253C;
            text-align: center;
            padding: 1rem;
            font-size: 0.85rem;
            margin-top: auto;
        }
        footer.attribution p { margin: 0; }
    </style>
</head>
<body>
    <header class="header-bar">
        <h1>CrediTrack</h1>
        <a href="<?= site_url('logout') ?>" class="btn-logout">Cerrar Sesión</a>
    </header>
    <main class="container">
        <div class="banner-success">
            <h2>¡Inicio de sesión exitoso!</h2>
            <p>Bienvenido al sistema CrediTrack.</p>
        </div>
        <div class="badges-row">
            <span class="badge badge-session">Estado de la sesión: Activa</span>
            <span class="badge badge-role">Rol asignado: <?= esc($rol) ?></span>
            <span class="badge badge-country">País asignado: <?= !empty($pais_nombre) ? esc($pais_nombre) : 'No especificado' ?></span>
        </div>
        <div class="card">
            <h3>Información del Usuario Autenticado</h3>
            <div class="user-details">
                <div class="detail-item">
                    <label>Código de Empleado</label>
                    <span><?= esc($codigo_empleado) ?></span>
                </div>
                <div class="detail-item">
                    <label>Nombre Completo</label>
                    <span><?= esc($nombre) ?></span>
                </div>
                <div class="detail-item">
                    <label>Nombre de Usuario</label>
                    <span><?= esc($usuario) ?></span>
                </div>
                <div class="detail-item">
                    <label>Rol</label>
                    <span><?= esc($rol) ?></span>
                </div>
            </div>
            <div class="access-boundary">
                <h3>Declaración de Límite de Acceso</h3>
                <p>Acceso restringido exclusivamente a información y operaciones de <?= !empty($pais_nombre) ? esc($pais_nombre) : 'la jurisdicción asignada' ?> con privilegios de <?= esc($rol) ?>.</p>
            </div>
        </div>
    </main>
    <footer class="attribution">
        <p>Sistema diseñado y desarrollado por el equipo de desarrollo de CrediTrack.</p>
    </footer>
</body>
</html>
```

---

## 7. Verification Checklist for Milestone 3 Gate

- [x] All 25 tests in `tests/Feature/ViewUITest.php` mapped and satisfied.
- [x] All 5 view-relevant tests in `tests/Feature/WorkflowIntegrationTest.php` mapped and satisfied.
- [x] All 5 hex palette colors (`#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C`) present in both views.
- [x] Author attribution markup matches `/(diseñad[oa]|desarrollad[oa]|diseñadores|equipo de desarrollo|autores)/i` and avoids `display:none` or `visibility:hidden`.
- [x] Success banner matches `/(¡?inicio de sesi[oó]n exitoso!?|bienvenido|sesi[oó]n iniciada)/i`.
- [x] Session health badge matches `/(estado de la sesi[oó]n:\s*activa|sesi[oó]n\s*activa)/i`.
- [x] Role badge matches `/(rol|rol asignado):?\s*supervisor/i`.
- [x] Country badge matches `/(pa[ií]s|pa[ií]s asignado):?\s*guatemala/i`.
- [x] Access boundary statement matches `/(acceso restringido.*honduras|l[ií]mite territorial.*honduras|operaciones de honduras)/i`.
- [x] Logout action matches `/(href="[^"]*logout[^"]*"|action="[^"]*logout[^"]*")/i` and label `/(cerrar sesi[oó]n|salir|logout)/i`.
- [x] No hardcoded country names exist in `home.php` (protects `testT4Scen02`).
- [x] Null `pais_nombre` handled gracefully without notices (protects `testT2Redir06`).
- [x] XSS escaping applied via `esc()` to all profile fields (protects `testT2Redir02`).
- [x] Flash error alert styles with `#BF0426` or `#A61717` (protects `testT2Ui04`).
- [x] Responsive viewport `<meta name="viewport" content="width=device-width, initial-scale=1.0">` present in both views.
- [x] High-contrast styling (light text on dark background) present (protects `testT2Ui06`).
- [x] Zero `.env` access maintained across all files.
