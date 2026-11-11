# Handoff Report — Milestone 3 (M3_views_ui) Automated Test Suite Expectations

**Author**: `explorer_m3_3`  
**Date**: 2026-11-10  
**Target Milestone**: `M3_views_ui`  
**State Directory**: `c:\xampp\htdocs\creditrack\.agents\teamwork\explorer_m3_3`  

---

## 1. Observation

Direct file observations and quoted lines from the codebase:

1. **`tests/_support/AuthTestCase.php` (Lines 25–45)**:
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
   Requires all 5 hex colors to be present case-insensitively in HTML outputs.

2. **`tests/Feature/ViewUITest.php` (All 25 Tests)**:
   - Line 44: `$this->assertMatchesRegularExpression('/home\.php/i', $location);`
   - Line 69: `$this->assertMatchesRegularExpression('/(¡?inicio de sesi[oó]n exitoso!?|bienvenido|sesi[oó]n iniciada)/i', $body);`
   - Line 94: `$this->assertMatchesRegularExpression('/(estado de la sesi[oó]n:\s*activa|sesi[oó]n\s*activa)/i', $body);`
   - Lines 117–119: `$result->assertSee('EMP-ADM-01'); $result->assertSee('Carlos Admin'); $result->assertSee('admin_sv');`
   - Lines 141, 146: `$this->assertMatchesRegularExpression('/(rol|rol asignado):?\s*supervisor/i', $body);` and `$this->assertMatchesRegularExpression('/(pa[ií]s|pa[ií]s asignado):?\s*guatemala/i', $body);`
   - Line 171: `$this->assertMatchesRegularExpression('/(acceso restringido.*honduras|l[ií]mite territorial.*honduras|operaciones de honduras)/i', $body);`
   - Lines 186, 217, 248, 279, 310: Tests for individual presence of `/#BF0426/i`, `/#0511F2/i`, `/#023373/i`, `/#A61717/i`, and `/#8D253C/i` in both login and home views.
   - Lines 342, 366: `$this->assertMatchesRegularExpression('/(diseñad[oa]|desarrollad[oa]|diseñadores|equipo de desarrollo|autores)/i', $body);` in login and home views.
   - Lines 425–430: `$this->assertStringNotContainsString('<script>alert(1)</script>', $body);` and `$this->assertTrue(str_contains($body, '&lt;script&gt;alert(1)&lt;/script&gt;') || !str_contains($body, '<script>'));`
   - Lines 471, 476: `$this->assertMatchesRegularExpression('/(href="[^"]*logout[^"]*"|action="[^"]*logout[^"]*")/i', $body);` and `$this->assertMatchesRegularExpression('/(cerrar sesi[oó]n|salir|logout)/i', $body);`
   - Line 525: `$this->assertCorporatePalettePresent($loginRes->getBody());` and Line 537: `$this->assertCorporatePalettePresent($homeRes->getBody());`
   - Lines 551, 556:
     ```php
     $this->assertDoesNotMatchRegularExpression(
         '/style\s*=\s*"[^"]*(display\s*:\s*none|visibility\s*:\s*hidden)[^"]*"(?:[^>]*)(?:diseñad|desarrollad|equipo de desarrollo)/i',
         $body
     );
     $this->assertMatchesRegularExpression(
         '/(footer|<div[^>]*(attribution|autor|creditos|designer)[^>]*>|diseñad|desarrollad)/i',
         $body
     );
     ```
   - Line 573: `$this->assertMatchesRegularExpression('/(diseñad[oa]|desarrollad[oa]|diseñadores|equipo de desarrollo|autores)/i', $rawHtml);`
   - Line 596: `$this->assertMatchesRegularExpression('/(#BF0426|#A61717)/i', $body);`
   - Lines 611, 627: `$this->assertMatchesRegularExpression('/<meta[^>]+name=["\']viewport["\'][^>]+content=["\'][^"\']*width=device-width/i', ...);`
   - Line 644: `$this->assertMatchesRegularExpression('/(color:\s*(#fff(fff)?|white)|background[^;]+(#023373|#8D253C|#BF0426|#A61717))/i', $body);`

3. **`tests/Feature/WorkflowIntegrationTest.php` (View-relevant tests)**:
   - Lines 177–180 (`testT3Pair05`): `$this->assertCorporatePalettePresent($body);` and `$this->assertMatchesRegularExpression('/(diseñad[oa]|desarrollad[oa]|diseñadores|equipo de desarrollo|autores)/i', $body);` after 3 consecutive failed login attempts triggering lockout.
   - Lines 318, 338 (`testT3Pair10`, `testT3Pair11`): Asserts `assertCorporatePalettePresent()` on login page following SQL injection payload submission and session timeout simulation.
   - Lines 395–399 (`testT4Scen01`): Asserts `home.php` renders employee code (`EMP-ASE-01`), full name (`Juan Perez`), role (`Asesor`), and country (`El Salvador`).
   - Line 443 (`testT4Scen02`): `$this->assertStringNotContainsString('El Salvador', $homeRes->getBody());` when authenticated as supervisor for Guatemala (`supervisor_gt`).

4. **Controllers & Existing Views State**:
   - `app/Controllers/AuthController.php` (Lines 29–32): Checks if `app/Views/login.php` exists and `filesize > 0`, rendering `view('login')`.
   - `app/Controllers/Home.php` (Lines 27–30): Checks if `app/Views/home.php` exists and `filesize > 0`, rendering `view('home', $data)`.
   - `app/Views/login.php` currently exists as an empty file (0 bytes).
   - `app/Views/home.php` currently does not exist.

---

## 2. Logic Chain

1. **Test Expectation Mapping**:
   From Observation 1 and 2, every view (`login.php` and `home.php`) is subjected to `assertCorporatePalettePresent()`, checking for the case-insensitive presence of all 5 hex strings (`#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C`). Therefore, both view templates must include stylesheet rules or markup directly embedding all 5 hex codes.
2. **Attribution Markup & CSS Anti-Hiding**:
   From Observation 2 (`testT1Ui06`, `testT1Ui07`, `testT2Ui02`, `testT2Ui03`), the attribution markup must reside in a `<footer>` or `<div class="attribution">`, must match `/(diseñad[oa]|desarrollad[oa]|diseñadores|equipo de desarrollo|autores)/i`, and must NOT have inline `display: none` or `visibility: hidden` styles.
3. **Session Health & Identity Confirmation**:
   From Observation 2 (`testT1Redir02` to `testT1Redir06`), `home.php` must render:
   - A banner matching `/(¡?inicio de sesi[oó]n exitoso!?|bienvenido|sesi[oó]n iniciada)/i`
   - A health badge matching `/(estado de la sesi[oó]n:\s*activa|sesi[oó]n\s*activa)/i`
   - Identity strings (`$codigo_empleado`, `$nombre`, `$usuario`)
   - Role badge matching `/(rol|rol asignado):?\s*<role>/i`
   - Country badge matching `/(pa[ií]s|pa[ií]s asignado):?\s*<country>/i`
   - Boundary statement matching `/(acceso restringido.*<country>|l[ií]mite territorial.*<country>|operaciones de <country>)/i`
4. **Data Isolation Strictness**:
   From Observation 3 (`testT4Scen02:443`), when `supervisor_gt` views `home.php`, the test asserts `$this->assertStringNotContainsString('El Salvador', $homeRes->getBody())`. If `home.php` contains hardcoded references to "El Salvador" (e.g. in author notes, sample dropdowns, or static jurisdiction text), `testT4Scen02` will fail. Thus, country names must only be output dynamically through `esc($pais_nombre)`.
5. **Robustness to Missing Metadata**:
   From Observation 2 (`testT2Redir06`), when `pais_nombre` is `null`, `home.php` must not trigger PHP warnings or notices. Using `!empty($pais_nombre) ? esc($pais_nombre) : 'No especificado'` ensures safety.
6. **XSS Protection**:
   From Observation 2 (`testT2Redir02`), all dynamic profile outputs must use `esc()` to properly escape HTML special characters.
7. **Flash Error Styling**:
   From Observation 2 (`testT2Ui04`), the error alert markup in `login.php` must incorporate `#BF0426` or `#A61717`.
8. **Mobile Viewport**:
   From Observation 2 (`testT2Ui05`), `<meta name="viewport" content="width=device-width, initial-scale=1.0">` is required in both views.

---

## 3. Caveats

- **Controllers are locked**: Under Milestone 3 instructions and `PROJECT.md`, `AuthController.php`, `Home.php`, `AuthFilter.php`, and `Routes.php` must not be modified. The views must work with the existing data arrays passed by `Home::index` and flash session keys set by `AuthController`.
- **No external CDN assets**: Views must be fully self-contained using internal CSS `<style>` blocks; external CSS/font CDNs could fail in offline/isolated environments or CI pipelines.
- **SQLite in-memory test runner**: All tests execute in memory via PHPUnit with SQLite. No MySQL database setup is needed to run the test suite.

---

## 4. Conclusion

Both `login.php` and `home.php` can be implemented strictly within `app/Views/` to pass 100% of the 25 tests in `tests/Feature/ViewUITest.php` and all view assertions in `tests/Feature/WorkflowIntegrationTest.php`.

The complete structural blueprints, CSS styling rules, regular expression mappings, and anti-pattern warnings are documented in `c:\xampp\htdocs\creditrack\.agents\teamwork\explorer_m3_3\analysis.md`. The implementer agent can directly apply these templates to achieve full test suite passage.

---

## 5. Verification Method

To independently verify the findings and validate the views once implemented:

1. **Verify ViewUITest (all 25 Tier 1 & Tier 2 tests)**:
   ```powershell
   php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit tests/Feature/ViewUITest.php
   ```
2. **Verify WorkflowIntegrationTest (all Tier 3 & Tier 4 tests)**:
   ```powershell
   php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit tests/Feature/WorkflowIntegrationTest.php
   ```
3. **Verify Corporate Palette Presence**:
   Inspect the rendered HTML of `login` and `home.php` ensuring strings `#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C` exist.
4. **Invalidation Conditions**:
   - Any test failure in `ViewUITest.php` or `WorkflowIntegrationTest.php`.
   - Any hardcoded mention of "El Salvador" on `home.php` that trips `testT4Scen02`.
   - Missing responsive viewport meta tag or unescaped profile strings.
