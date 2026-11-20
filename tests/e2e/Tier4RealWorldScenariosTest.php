<?php

declare(strict_types=1);

namespace Tests\E2E;

require_once __DIR__ . '/E2ETestCase.php';

use CodeIgniter\Test\TestResponse;

/**
 * Tier 4: Real-World Central American Supervisor Workflow Scenarios (T4-SCEN-01..T4-SCEN-08).
 *
 * Opaque-box E2E tests covering R1..R7 and CA-01..CA-12 on SQLite :memory:
 * with zero mocks, zero hardcoded auto-increment IDs or employee codes, and zero environment-file access.
 */
final class Tier4RealWorldScenariosTest extends E2ETestCase
{
    public function testT4Scen01_ElSalvadorFullAgencyLifecycle(): void
    {
        $this->requireHU002Schema();

        $svUser   = $this->findUserByUsername('supervisor_sv');
        $svCodigo = (string) $svUser['codigo_empleado'];
        $hondaId  = (int) $this->findMarcaByName('Honda')['id_marca'];
        $yamahaId = (int) $this->findMarcaByName('Yamaha')['id_marca'];
        $suzukiId = (int) $this->findMarcaByName('Suzuki')['id_marca'];

        // 1. Genuine HTTP login
        $this->loginAsSupervisorSv();

        // 2. Home menu & corporate palette
        $homeRes = $this->getPage('home.php');
        $homeRes->assertStatus(200);
        $homeBody = (string) $homeRes->getBody();
        $this->assertAgencyManagementMenuPresent($homeBody);
        $this->assertCorporatePalettePresent($homeBody);

        // 3. Create form + Leaflet click-to-set UI + cascading geo hooks
        $createFormRes = $this->getPage('agencias/crear');
        $createFormRes->assertStatus(200);
        $formHtml = (string) $createFormRes->getBody();
        $this->assertCorporatePalettePresent($formHtml);
        $this->assertAgencyManagementMenuPresent($formHtml);
        $this->assertMatchesRegularExpression('/<select\b[^>]*name=["\']id_pais["\']/i', $formHtml);
        $this->assertMatchesRegularExpression('/<select\b[^>]*name=["\']id_departamento["\']/i', $formHtml);
        $this->assertMatchesRegularExpression('/<select\b[^>]*name=["\']id_municipio["\']/i', $formHtml);
        $this->assertMatchesRegularExpression('/<select\b[^>]*name=["\']id_distrito["\']/i', $formHtml);
        $this->assertStringContainsStringIgnoringCase('api/geo/', $formHtml);
        $this->assertLeafletMapMarkup($formHtml, true);

        // 4. Cascading Geo API sequence
        $paisesJson = $this->assertJsonOk($this->getJson('api/geo/paises'));
        $paises     = $paisesJson['paises'] ?? [];
        $this->assertCount(1, $paises);
        $this->assertSame('El Salvador', $paises[0]['pais'] ?? null);
        $this->assertTrue((bool) ($paises[0]['requiere_distrito'] ?? false));
        $idPaisSv = (int) $paises[0]['id_pais'];

        $deptosJson = $this->assertJsonOk($this->getJson('api/geo/departamentos'));
        $deptos     = $deptosJson['departamentos'] ?? [];
        $this->assertNotEmpty($deptos);
        $ssDepto = null;
        foreach ($deptos as $depto) {
            if (strcasecmp((string) ($depto['departamento'] ?? ''), 'San Salvador') === 0) {
                $ssDepto = $depto;
                break;
            }
        }
        $this->assertNotNull($ssDepto, 'San Salvador must appear in api/geo/departamentos.');
        $idDepto = (int) $ssDepto['id_departamento'];

        $munisJson = $this->assertJsonOk($this->getJson('api/geo/municipios', [
            'id_departamento' => $idDepto,
        ]));
        $munis = $munisJson['municipios'] ?? [];
        $this->assertNotEmpty($munis);
        $idMuni = (int) $munis[0]['id_municipio'];

        $distsJson = $this->assertJsonOk($this->getJson('api/geo/distritos', [
            'id_municipio' => $idMuni,
        ]));
        $dists = $distsJson['distritos'] ?? [];
        $this->assertNotEmpty($dists);
        $idDist = (int) $dists[0]['id_distrito'];

        // 5. Create agency with map click-to-set coordinates & 2 brands
        $nombre1   = 'Agencia SV Escalon ' . str_replace('.', '', uniqid('', true));
        $resCreate = $this->postForm('agencias/guardar', [
            'nombre_agencia'  => $nombre1,
            'direccion'       => 'Paseo General Escalón #410',
            'id_pais'         => $idPaisSv,
            'id_departamento' => $idDepto,
            'id_municipio'    => $idMuni,
            'id_distrito'     => $idDist,
            'latitud'         => '13.70123450',
            'longitud'        => '-89.22456780',
            'marcas'          => [$hondaId, $yamahaId],
        ]);
        $createStatus = $resCreate->response()->getStatusCode();
        $this->assertTrue(
            in_array($createStatus, [200, 201, 302, 303], true),
            "Expected create success status in T4-SCEN-01, got {$createStatus}."
        );

        $agency = $this->findAgencyByName($nombre1);
        $this->assertNotNull($agency);
        $idAgencia = (int) $agency['idagencia'];
        $this->assertSame(1, (int) $agency['activo']);
        $this->assertSame($idPaisSv, (int) $agency['id_pais']);
        $this->assertEqualsWithDelta(13.7012345, (float) $agency['latitud'], 0.00001);
        $this->assertEqualsWithDelta(-89.2245678, (float) $agency['longitud'], 0.00001);
        $this->assertEqualsCanonicalizing(['Honda', 'Yamaha'], $this->getAgencyBrandNames($idAgencia));
        $this->assertAuditRecorded($idAgencia, 'CREAR', $svCodigo);

        // 6. Inspect detail view
        $showRes = $this->getPage('agencias/ver/' . $idAgencia);
        $showRes->assertStatus(200);
        $showBody = (string) $showRes->getBody();
        $this->assertStringContainsString($nombre1, $showBody);
        $this->assertStringContainsString('Honda', $showBody);
        $this->assertStringContainsString('Yamaha', $showBody);
        $this->assertStringContainsString('CREAR', $showBody);

        // 7. Edit form & update (address, coords, replaced brands)
        $editRes = $this->getPage('agencias/editar/' . $idAgencia);
        $editRes->assertStatus(200);
        $editBody = (string) $editRes->getBody();
        $this->assertStringContainsString($nombre1, $editBody);
        $this->assertLeafletMapMarkup($editBody, true);

        $nombre2 = 'Agencia SV Escalon Renovada ' . str_replace('.', '', uniqid('', true));
        $updRes  = $this->postForm('agencias/actualizar/' . $idAgencia, [
            'nombre_agencia'  => $nombre2,
            'direccion'       => 'Alameda Roosevelt #820',
            'id_pais'         => $idPaisSv,
            'id_departamento' => $idDepto,
            'id_municipio'    => $idMuni,
            'id_distrito'     => $idDist,
            'latitud'         => '13.68991100',
            'longitud'        => '-89.19882200',
            'marcas'          => [$yamahaId, $suzukiId],
        ]);
        $updStatus = $updRes->response()->getStatusCode();
        $this->assertTrue(
            in_array($updStatus, [200, 201, 302, 303], true),
            "Expected update success status in T4-SCEN-01, got {$updStatus}."
        );

        $updated = $this->findAgencyById($idAgencia);
        $this->assertNotNull($updated);
        $this->assertSame($nombre2, (string) $updated['nombre_agencia']);
        $this->assertSame('Alameda Roosevelt #820', (string) $updated['direccion']);
        $this->assertEqualsWithDelta(13.689911, (float) $updated['latitud'], 0.00001);
        $this->assertEqualsWithDelta(-89.198822, (float) $updated['longitud'], 0.00001);
        $this->assertEqualsCanonicalizing(['Suzuki', 'Yamaha'], $this->getAgencyBrandNames($idAgencia));
        $this->assertAuditRecorded($idAgencia, 'ACTUALIZAR', $svCodigo);

        // 8. Operational map & geolocation API filtering
        $mapRes = $this->getPage('agencias/mapa');
        $mapRes->assertStatus(200);
        $mapHtml = (string) $mapRes->getBody();
        $this->assertCorporatePalettePresent($mapHtml);
        $this->assertAgencyManagementMenuPresent($mapHtml);
        $this->assertLeafletMapMarkup($mapHtml, false);
        $this->assertStringContainsString($nombre2, $mapHtml);

        $geoMatch = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion', [
            'id_marca'        => $suzukiId,
            'id_departamento' => $idDepto,
        ]));
        $matchedItem = $this->findGeoItemById($geoMatch, $idAgencia);
        $this->assertNotNull($matchedItem);
        $this->assertSame($nombre2, $matchedItem['nombre'] ?? null);
        $this->assertSame('El Salvador', $matchedItem['pais'] ?? null);
        $this->assertEqualsCanonicalizing(['Suzuki', 'Yamaha'], $matchedItem['marcas'] ?? []);
        $this->assertEqualsWithDelta(13.689911, (float) ($matchedItem['latitud'] ?? 0), 0.00001);
        $this->assertEqualsWithDelta(-89.198822, (float) ($matchedItem['longitud'] ?? 0), 0.00001);

        $geoRemoved = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion', [
            'id_marca' => $hondaId,
        ]));
        $this->assertNull($this->findGeoItemById($geoRemoved, $idAgencia));

        // 9. Soft-disable & verify 3 ordered audits + exclusion from operational map/API
        $motivo = 'Cierre temporal por remodelación de sala de ventas';
        $disRes = $this->postForm('agencias/deshabilitar/' . $idAgencia, [
            'motivo_deshabilitacion' => $motivo,
        ]);
        $disStatus = $disRes->response()->getStatusCode();
        $this->assertTrue(
            in_array($disStatus, [200, 201, 302, 303], true),
            "Expected soft-disable success status in T4-SCEN-01, got {$disStatus}."
        );

        $this->assertAgencySoftDisabled($idAgencia, $svCodigo, $motivo);

        $audits = $this->getAuditLogsForAgency($idAgencia);
        $this->assertCount(3, $audits);
        $this->assertSame(
            ['CREAR', 'ACTUALIZAR', 'DESHABILITAR'],
            array_map(static fn (array $a): string => strtoupper((string) ($a['tipo_operacion'] ?? '')), $audits)
        );
        $this->assertSame($motivo, (string) ($audits[2]['motivo_deshabilitacion'] ?? ''));

        $geoAfter = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $this->assertNull($this->findGeoItemById($geoAfter, $idAgencia));

        $mapAfter = $this->getPage('agencias/mapa');
        $mapAfter->assertStatus(200);
        $this->assertStringNotContainsString($nombre2, (string) $mapAfter->getBody());

        $inactiveList = $this->getPage('agencias', ['estado' => 'inactivas']);
        $inactiveList->assertStatus(200);
        $this->assertStringContainsString($nombre2, (string) $inactiveList->getBody());

        $showAfter = $this->getPage('agencias/ver/' . $idAgencia);
        $showAfter->assertStatus(200);
        $showAfterBody = (string) $showAfter->getBody();
        $this->assertStringContainsString($motivo, $showAfterBody);
        $this->assertStringContainsString('DESHABILITAR', $showAfterBody);
    }

    public function testT4Scen02_GuatemalaMultiBrandDealerWithoutDistrict(): void
    {
        $this->requireHU002Schema();

        $gtUser   = $this->findUserByUsername('supervisor_gt');
        $gtCodigo = (string) $gtUser['codigo_empleado'];
        $hondaId  = (int) $this->findMarcaByName('Honda')['id_marca'];
        $yamahaId = (int) $this->findMarcaByName('Yamaha')['id_marca'];
        $heroId   = (int) $this->findMarcaByName('Hero')['id_marca'];

        $this->loginAsSupervisorGt();

        $paisesJson = $this->assertJsonOk($this->getJson('api/geo/paises'));
        $paises     = $paisesJson['paises'] ?? [];
        $this->assertCount(1, $paises);
        $this->assertSame('Guatemala', $paises[0]['pais'] ?? null);
        $this->assertFalse((bool) ($paises[0]['requiere_distrito'] ?? true));
        $idPaisGt = (int) $paises[0]['id_pais'];

        $createForm = $this->getPage('agencias/crear');
        $createForm->assertStatus(200);
        $formHtml = (string) $createForm->getBody();
        $this->assertLeafletMapMarkup($formHtml, true);
        $this->assertMatchesRegularExpression('/requiere[_-]?distrito/i', $formHtml);

        $deptosJson = $this->assertJsonOk($this->getJson('api/geo/departamentos'));
        $deptos     = $deptosJson['departamentos'] ?? [];
        $this->assertNotEmpty($deptos);
        $gtDepto = $deptos[0];
        foreach ($deptos as $d) {
            if (strcasecmp((string) ($d['departamento'] ?? ''), 'Guatemala') === 0) {
                $gtDepto = $d;
                break;
            }
        }
        $idDepto = (int) $gtDepto['id_departamento'];

        $munisJson = $this->assertJsonOk($this->getJson('api/geo/municipios', [
            'id_departamento' => $idDepto,
        ]));
        $munis = $munisJson['municipios'] ?? [];
        $this->assertNotEmpty($munis);
        $gtMuni = $munis[0];
        foreach ($munis as $m) {
            if (strcasecmp((string) ($m['municipio'] ?? ''), 'Mixco') === 0) {
                $gtMuni = $m;
                break;
            }
        }
        $idMuni = (int) $gtMuni['id_municipio'];

        $distsJson = $this->assertJsonOk($this->getJson('api/geo/distritos', [
            'id_municipio' => $idMuni,
        ]));
        $this->assertSame([], $distsJson['distritos'] ?? null);

        $nombre    = 'Agencia GT Mixco ' . str_replace('.', '', uniqid('', true));
        $resCreate = $this->postForm('agencias/guardar', [
            'nombre_agencia'  => $nombre,
            'direccion'       => 'Calzada San Juan 12-40, Mixco',
            'id_pais'         => $idPaisGt,
            'id_departamento' => $idDepto,
            'id_municipio'    => $idMuni,
            'id_distrito'     => '',
            'latitud'         => '14.63075000',
            'longitud'        => '-90.60612000',
            'marcas'          => [$hondaId, $yamahaId, $heroId],
        ]);
        $status = $resCreate->response()->getStatusCode();
        $this->assertTrue(
            in_array($status, [200, 201, 302, 303], true),
            "Expected GT create success status in T4-SCEN-02, got {$status}."
        );

        $agency = $this->findAgencyByName($nombre);
        $this->assertNotNull($agency);
        $idAgencia = (int) $agency['idagencia'];

        $this->assertNull($agency['id_distrito']);
        $this->assertSame($idPaisGt, (int) $agency['id_pais']);
        $this->assertSame(1, (int) $agency['activo']);
        $this->assertEqualsCanonicalizing(
            ['Hero', 'Honda', 'Yamaha'],
            $this->getAgencyBrandNames($idAgencia)
        );
        $this->assertAuditRecorded($idAgencia, 'CREAR', $gtCodigo);

        $showRes = $this->getPage('agencias/ver/' . $idAgencia);
        $showRes->assertStatus(200);
        $showBody = (string) $showRes->getBody();
        $this->assertStringContainsString($nombre, $showBody);
        $this->assertStringContainsString('Hero', $showBody);

        $mapRes = $this->getPage('agencias/mapa');
        $mapRes->assertStatus(200);
        $mapHtml = (string) $mapRes->getBody();
        $this->assertLeafletMapMarkup($mapHtml, false);
        $this->assertStringContainsString($nombre, $mapHtml);

        $geoJson = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion', [
            'marca' => 'Hero',
        ]));
        $this->assertSame('Guatemala', $geoJson['pais'] ?? null);
        $item = $this->findGeoItemById($geoJson, $idAgencia);
        $this->assertNotNull($item);
        $this->assertArrayHasKey('distrito', $item);
        $this->assertNull($item['distrito']);
        $this->assertEqualsCanonicalizing(['Honda', 'Yamaha', 'Hero'], $item['marcas'] ?? []);
    }

    public function testT4Scen03_CrossBorderIsolationMultiCountry(): void
    {
        $this->requireHU002Schema();

        $gtPaisId = (int) $this->findPaisByName('Guatemala')['id_pais'];
        $svName   = 'Agencia Frontera SV ' . str_replace('.', '', uniqid('', true));
        $gtName   = 'Agencia Frontera GT ' . str_replace('.', '', uniqid('', true));
        $hnName   = 'Agencia Frontera HN ' . str_replace('.', '', uniqid('', true));

        $svAgency = $this->createAgencyViaHttp('supervisor_sv', ['nombre_agencia' => $svName]);
        $gtAgency = $this->createAgencyViaHttp('supervisor_gt', ['nombre_agencia' => $gtName]);
        $hnAgency = $this->createAgencyViaHttp('supervisor_hn', ['nombre_agencia' => $hnName]);

        $svId = (int) $svAgency['idagencia'];
        $gtId = (int) $gtAgency['idagencia'];
        $hnId = (int) $hnAgency['idagencia'];

        // El Salvador supervisor boundary
        $this->loginAsSupervisorSv();
        $svList = $this->getPage('agencias');
        $svList->assertStatus(200);
        $svListBody = (string) $svList->getBody();
        $this->assertStringContainsString($svName, $svListBody);
        $this->assertStringNotContainsString($gtName, $svListBody);
        $this->assertStringNotContainsString($hnName, $svListBody);

        $svMap = $this->getPage('agencias/mapa');
        $svMap->assertStatus(200);
        $svMapBody = (string) $svMap->getBody();
        $this->assertStringContainsString($svName, $svMapBody);
        $this->assertStringNotContainsString($gtName, $svMapBody);
        $this->assertStringNotContainsString($hnName, $svMapBody);

        $svGeo = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $this->assertSame('El Salvador', $svGeo['pais'] ?? null);
        $this->assertNotNull($this->findGeoItemById($svGeo, $svId));
        $this->assertNull($this->findGeoItemById($svGeo, $gtId));
        $this->assertNull($this->findGeoItemById($svGeo, $hnId));

        $this->getJson('api/agencias/geolocalizacion', ['id_pais' => $gtPaisId])->assertStatus(403);
        $this->getJson('api/agencias/geolocalizacion', ['pais' => 'Guatemala'])->assertStatus(403);
        $this->getJson('api/geo/departamentos', ['id_pais' => $gtPaisId])->assertStatus(403);

        $this->getPage('agencias/ver/' . $gtId)->assertStatus(403);
        $this->getPage('agencias/editar/' . $gtId)->assertStatus(403);
        $this->postForm('agencias/actualizar/' . $gtId, [
            'nombre_agencia' => 'Intento Hijack GT',
        ])->assertStatus(403);
        $this->postForm('agencias/deshabilitar/' . $gtId, [
            'motivo_deshabilitacion' => 'Intento cruzado GT',
        ])->assertStatus(403);

        $this->getPage('agencias/ver/' . $hnId)->assertStatus(403);
        $this->postForm('agencias/deshabilitar/' . $hnId, [
            'motivo_deshabilitacion' => 'Intento cruzado HN',
        ])->assertStatus(403);

        $this->assertSame(1, (int) ($this->findAgencyById($gtId)['activo'] ?? 0));
        $this->assertCount(1, $this->getAuditLogsForAgency($gtId));
        $this->assertSame(1, (int) ($this->findAgencyById($hnId)['activo'] ?? 0));

        // Guatemala supervisor boundary
        $this->loginAsSupervisorGt();
        $gtList = $this->getPage('agencias');
        $gtList->assertStatus(200);
        $gtListBody = (string) $gtList->getBody();
        $this->assertStringContainsString($gtName, $gtListBody);
        $this->assertStringNotContainsString($svName, $gtListBody);
        $this->assertStringNotContainsString($hnName, $gtListBody);

        $gtGeo = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $this->assertSame('Guatemala', $gtGeo['pais'] ?? null);
        $this->assertNotNull($this->findGeoItemById($gtGeo, $gtId));
        $this->assertNull($this->findGeoItemById($gtGeo, $svId));
        $this->assertNull($this->findGeoItemById($gtGeo, $hnId));

        $this->getPage('agencias/ver/' . $svId)->assertStatus(403);
        $this->postForm('agencias/deshabilitar/' . $svId, [
            'motivo_deshabilitacion' => 'Intento GT->SV',
        ])->assertStatus(403);

        // Honduras supervisor boundary
        $this->loginAsSupervisorHn();
        $hnList = $this->getPage('agencias');
        $hnList->assertStatus(200);
        $hnListBody = (string) $hnList->getBody();
        $this->assertStringContainsString($hnName, $hnListBody);
        $this->assertStringNotContainsString($svName, $hnListBody);
        $this->assertStringNotContainsString($gtName, $hnListBody);

        $hnGeo = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $this->assertSame('Honduras', $hnGeo['pais'] ?? null);
        $this->assertNotNull($this->findGeoItemById($hnGeo, $hnId));
        $this->assertNull($this->findGeoItemById($hnGeo, $svId));
        $this->assertNull($this->findGeoItemById($hnGeo, $gtId));

        $this->getPage('agencias/ver/' . $svId)->assertStatus(403);
    }

    public function testT4Scen04_SupervisorPrivilegeBoundaryEscalation(): void
    {
        $this->requireHU002Schema();

        $asesor       = $this->findUserByUsername('asesor_sv');
        $asesorCodigo = (string) $asesor['codigo_empleado'];
        $svCodigo     = (string) $this->findUserByUsername('supervisor_sv')['codigo_empleado'];

        $this->loginAsSupervisorSv();

        foreach (['home.php', 'agencias', 'agencias/crear', 'agencias/mapa'] as $uri) {
            $res = $this->getPage($uri);
            $res->assertStatus(200);
            $html = (string) $res->getBody();
            $this->assertCorporatePalettePresent($html);
            $this->assertAgencyManagementMenuPresent($html);
            $this->assertNoUserAdminNavLinks($html);
        }

        $this->getPage('usuarios')->assertStatus(403);
        $this->postForm('usuarios/crear', ['usuario' => 'escalated_user'])->assertStatus(403);
        $this->getPage('usuarios/editar/' . $asesorCodigo)->assertStatus(403);
        $this->postForm('usuarios/deshabilitar/' . $asesorCodigo, ['motivo' => 'Escalada'])->assertStatus(403);
        $this->getPage('roles')->assertStatus(403);
        $this->postForm('roles/asignar', ['rol' => 'Administrador'])->assertStatus(403);
        $this->getPage('permisos')->assertStatus(403);
        $this->postForm('permisos/actualizar', ['permiso' => 'all'])->assertStatus(403);

        foreach (['api/usuarios', 'api/roles', 'api/permisos'] as $apiUri) {
            $apiRes = $this->getJson($apiUri);
            $apiRes->assertStatus(403);
            $json = $this->decodeJsonResponse($apiRes);
            $this->assertSame('error', $json['status'] ?? null);
        }

        $reloadedAsesor = $this->findUserByUsername('asesor_sv');
        $this->assertSame('Asesor', (string) $reloadedAsesor['rol']);
        $this->assertSame(0, (int) $reloadedAsesor['bloqueado']);

        // Supervisor's legitimate agency workflow remains fully functional
        $created = $this->createAgencyViaHttp('supervisor_sv');
        $this->assertAuditRecorded((int) $created['idagencia'], 'CREAR', $svCodigo);
    }

    public function testT4Scen05_GeolocationDataQualityAndFiltering(): void
    {
        $this->requireHU002Schema();

        $svPaisId = (int) $this->findPaisByName('El Salvador')['id_pais'];
        $deptoSS  = $this->findDepartamentoByName('San Salvador', $svPaisId);
        $muniSS   = $this->findMunicipioByName('San Salvador Centro', (int) $deptoSS['id_departamento']);
        $distSS   = $this->findDistritoByName('San Salvador', (int) $muniSS['id_municipio']);

        $deptoLL = $this->findDepartamentoByName('La Libertad', $svPaisId);
        $muniLL  = $this->findMunicipioByName('La Libertad Sur', (int) $deptoLL['id_departamento']);
        $distLL  = $this->findDistritoByName('Santa Tecla', (int) $muniLL['id_municipio']);

        $hondaId  = (int) $this->findMarcaByName('Honda')['id_marca'];
        $yamahaId = (int) $this->findMarcaByName('Yamaha')['id_marca'];

        $this->loginAsSupervisorSv();

        // Agency A: Active, valid coords, San Salvador, Honda
        $nameA = 'Agencia Calidad A ' . uniqid();
        $resA  = $this->postForm('agencias/guardar', [
            'nombre_agencia'  => $nameA,
            'direccion'       => 'Av. España #10',
            'id_pais'         => $svPaisId,
            'id_departamento' => (int) $deptoSS['id_departamento'],
            'id_municipio'    => (int) $muniSS['id_municipio'],
            'id_distrito'     => (int) $distSS['id_distrito'],
            'latitud'         => '13.69294000',
            'longitud'        => '-89.21819000',
            'marcas'          => [$hondaId],
        ]);
        $this->assertTrue(
            in_array($resA->response()->getStatusCode(), [200, 201, 302, 303], true),
            'Expected create success status for Agency A.'
        );
        $agencyA = $this->findAgencyByName($nameA);
        $this->assertNotNull($agencyA);
        $idA = (int) $agencyA['idagencia'];

        // Agency B: Active, valid coords, La Libertad, Yamaha
        $nameB = 'Agencia Calidad B ' . uniqid();
        $resB  = $this->postForm('agencias/guardar', [
            'nombre_agencia'  => $nameB,
            'direccion'       => 'Carretera al Puerto #20',
            'id_pais'         => $svPaisId,
            'id_departamento' => (int) $deptoLL['id_departamento'],
            'id_municipio'    => (int) $muniLL['id_municipio'],
            'id_distrito'     => (int) $distLL['id_distrito'],
            'latitud'         => '13.67694000',
            'longitud'        => '-89.27972000',
            'marcas'          => [$yamahaId],
        ]);
        $this->assertTrue(
            in_array($resB->response()->getStatusCode(), [200, 201, 302, 303], true),
            'Expected create success status for Agency B.'
        );
        $agencyB = $this->findAgencyByName($nameB);
        $this->assertNotNull($agencyB);
        $idB = (int) $agencyB['idagencia'];

        // Agency C: Active, NULL coords (direct DB fixture representing coordless row)
        $agenciasTable = $this->resolveTableName('Agencias');
        $pivotTable    = $this->resolveTableName('Agencia_Marca');
        $this->assertNotNull($agenciasTable);
        $this->assertNotNull($pivotTable);

        $nameC = 'Agencia Calidad C SinCoords ' . uniqid();
        $this->db->table($agenciasTable)->insert([
            'nombre_agencia'  => $nameC,
            'direccion'       => 'Calle Sin Coordenadas #30',
            'Id_pais'         => $svPaisId,
            'Id_departamento' => (int) $deptoSS['id_departamento'],
            'Id_municipio'    => (int) $muniSS['id_municipio'],
            'Id_distrito'     => (int) $distSS['id_distrito'],
            'latitud'         => null,
            'longitud'        => null,
            'Id_marca'        => $hondaId,
            'activo'          => 1,
            'creado_en'       => '2026-11-18 12:00:00',
            'actualizado_en'  => '2026-11-18 12:00:00',
        ]);
        $idC = (int) $this->db->insertID();
        $this->db->table($pivotTable)->insert(['Idagencia' => $idC, 'Id_marca' => $hondaId]);

        // Agency D: Created with valid coords, then soft-disabled
        $nameD = 'Agencia Calidad D Inactiva ' . uniqid();
        $resD  = $this->postForm('agencias/guardar', [
            'nombre_agencia'  => $nameD,
            'direccion'       => 'Final 49 Av. Sur #40',
            'id_pais'         => $svPaisId,
            'id_departamento' => (int) $deptoSS['id_departamento'],
            'id_municipio'    => (int) $muniSS['id_municipio'],
            'id_distrito'     => (int) $distSS['id_distrito'],
            'latitud'         => '13.71000000',
            'longitud'        => '-89.19000000',
            'marcas'          => [$hondaId],
        ]);
        $this->assertTrue(
            in_array($resD->response()->getStatusCode(), [200, 201, 302, 303], true),
            'Expected create success status for Agency D.'
        );
        $agencyD = $this->findAgencyByName($nameD);
        $this->assertNotNull($agencyD);
        $idD = (int) $agencyD['idagencia'];

        $resDisD = $this->postForm('agencias/deshabilitar/' . $idD, [
            'motivo_deshabilitacion' => 'Fuera de operación',
        ]);
        $this->assertTrue(
            in_array($resDisD->response()->getStatusCode(), [200, 201, 302, 303], true),
            'Expected soft-disable success status for Agency D.'
        );

        // Unfiltered geo API: excludes C (NULL coords) and D (activo=0)
        $allGeo = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $this->assertSame('El Salvador', $allGeo['pais'] ?? null);
        $this->assertSame(2, (int) ($allGeo['total'] ?? -1));
        $returnedIds = [];
        foreach ($allGeo['agencias'] ?? [] as $item) {
            foreach (['id', 'nombre', 'pais', 'direccion', 'marcas', 'latitud', 'longitud'] as $key) {
                $this->assertArrayHasKey($key, $item);
            }
            $returnedIds[] = (int) $item['id'];
        }
        $this->assertEqualsCanonicalizing([$idA, $idB], $returnedIds);
        $this->assertNotContains($idC, $returnedIds);
        $this->assertNotContains($idD, $returnedIds);

        // Filter by id_marca=Honda -> Agency A only
        $hondaGeo = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion', [
            'id_marca' => $hondaId,
        ]));
        $this->assertSame(1, (int) ($hondaGeo['total'] ?? -1));
        $this->assertSame($idA, (int) ($hondaGeo['agencias'][0]['id'] ?? 0));

        // Filter by marca=Yamaha -> Agency B only
        $yamahaGeo = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion', [
            'marca' => 'Yamaha',
        ]));
        $this->assertSame(1, (int) ($yamahaGeo['total'] ?? -1));
        $this->assertSame($idB, (int) ($yamahaGeo['agencias'][0]['id'] ?? 0));

        // Filter by id_departamento=La Libertad -> Agency B only
        $deptoGeo = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion', [
            'id_departamento' => (int) $deptoLL['id_departamento'],
        ]));
        $this->assertSame(1, (int) ($deptoGeo['total'] ?? -1));
        $this->assertSame($idB, (int) ($deptoGeo['agencias'][0]['id'] ?? 0));

        // Filter by id_municipio + id_distrito -> Agency A only
        $distGeo = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion', [
            'id_municipio' => (int) $muniSS['id_municipio'],
            'id_distrito'  => (int) $distSS['id_distrito'],
        ]));
        $this->assertSame(1, (int) ($distGeo['total'] ?? -1));
        $this->assertSame($idA, (int) ($distGeo['agencias'][0]['id'] ?? 0));

        // Intersection with zero matches -> empty
        $emptyGeo = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion', [
            'id_marca'        => $hondaId,
            'id_departamento' => (int) $deptoLL['id_departamento'],
        ]));
        $this->assertSame(0, (int) ($emptyGeo['total'] ?? -1));
        $this->assertSame([], $emptyGeo['agencias'] ?? null);
    }

    public function testT4Scen06_ExtensibleCountryOnboardingCostaRica(): void
    {
        $this->requireHU002Schema();

        $crGeo = $this->seedExtensibleCountryWithDivisions('Costa Rica', 0, 'San José', 'Central', null);

        $usuarioTable = $this->resolveTableName('Usuario');
        $this->assertNotNull($usuarioTable);
        $crCodigo = 'EMP-CR-001';
        $this->db->table($usuarioTable)->insert([
            'Codigo_Empleado'   => $crCodigo,
            'Nombre'            => 'Valeria Supervisora CR',
            'Usuario'           => 'supervisor_cr',
            'Contrasena'        => password_hash('CostaRica123!', PASSWORD_BCRYPT),
            'Id_Pais'           => $crGeo['id_pais'],
            'telefono'          => '506-2222-0001',
            'rol'               => 'Supervisor',
            'intentos_fallidos' => 0,
            'bloqueado'         => 0,
            'ultimo_fallo'      => null,
            'ultimo_login'      => null,
            'creado_en'         => '2026-11-18 12:00:00',
            'actualizado_en'    => '2026-11-18 12:00:00',
        ]);

        $hondaId  = (int) $this->findMarcaByName('Honda')['id_marca'];
        $suzukiId = (int) $this->findMarcaByName('Suzuki')['id_marca'];

        // Genuine HTTP login for newly onboarded country supervisor
        $this->loginViaHttp('supervisor_cr', 'CostaRica123!');
        $this->assertSame('Costa Rica', $this->activeSession['pais_nombre'] ?? null);
        $this->assertSame($crGeo['id_pais'], (int) ($this->activeSession['id_pais'] ?? 0));

        $paisesJson = $this->assertJsonOk($this->getJson('api/geo/paises'));
        $this->assertCount(1, $paisesJson['paises'] ?? []);
        $this->assertSame('Costa Rica', $paisesJson['paises'][0]['pais'] ?? null);
        $this->assertFalse((bool) ($paisesJson['paises'][0]['requiere_distrito'] ?? true));

        $deptosJson = $this->assertJsonOk($this->getJson('api/geo/departamentos'));
        $this->assertSame('San José', $deptosJson['departamentos'][0]['departamento'] ?? null);

        $munisJson = $this->assertJsonOk($this->getJson('api/geo/municipios', [
            'id_departamento' => $crGeo['id_departamento'],
        ]));
        $this->assertSame('Central', $munisJson['municipios'][0]['municipio'] ?? null);

        $crName    = 'Agencia Costa Rica Central ' . str_replace('.', '', uniqid('', true));
        $createRes = $this->postForm('agencias/guardar', [
            'nombre_agencia'  => $crName,
            'direccion'       => 'Avenida Central Calle 5, San José',
            'id_pais'         => $crGeo['id_pais'],
            'id_departamento' => $crGeo['id_departamento'],
            'id_municipio'    => $crGeo['id_municipio'],
            'latitud'         => '9.92806900',
            'longitud'        => '-84.09072500',
            'marcas'          => [$hondaId, $suzukiId],
        ]);
        $status = $createRes->response()->getStatusCode();
        $this->assertTrue(
            in_array($status, [200, 201, 302, 303], true),
            "Expected Costa Rica agency creation success status, got {$status}."
        );

        $crAgency = $this->findAgencyByName($crName);
        $this->assertNotNull($crAgency);
        $crAgencyId = (int) $crAgency['idagencia'];
        $this->assertSame($crGeo['id_pais'], (int) $crAgency['id_pais']);
        $this->assertNull($crAgency['id_distrito']);
        $this->assertAuditRecorded($crAgencyId, 'CREAR', $crCodigo);

        $mapRes = $this->getPage('agencias/mapa');
        $mapRes->assertStatus(200);
        $mapHtml = (string) $mapRes->getBody();
        $this->assertLeafletMapMarkup($mapHtml, false);
        $this->assertStringContainsString($crName, $mapHtml);

        $geoJson = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $this->assertSame('Costa Rica', $geoJson['pais'] ?? null);
        $this->assertSame(1, (int) ($geoJson['total'] ?? -1));
        $item = $this->findGeoItemById($geoJson, $crAgencyId);
        $this->assertNotNull($item);
        $this->assertArrayHasKey('distrito', $item);
        $this->assertNull($item['distrito']);
        $this->assertEqualsCanonicalizing(['Honda', 'Suzuki'], $item['marcas'] ?? []);

        // Cross-border isolation: SV supervisor cannot see or access Costa Rica agency
        $this->loginAsSupervisorSv();
        $svGeo = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $this->assertNull($this->findGeoItemById($svGeo, $crAgencyId));
        $this->getPage('agencias/ver/' . $crAgencyId)->assertStatus(403);
    }

    public function testT4Scen07_AgencyAuditAndAdvisorVisitIntegrityChain(): void
    {
        $this->requireHU002Schema();
        $this->assertSqliteForeignKeysEnabled();

        $svCodigo     = (string) $this->findUserByUsername('supervisor_sv')['codigo_empleado'];
        $asesorCodigo = (string) $this->findUserByUsername('asesor_sv')['codigo_empleado'];
        $svChain      = $this->getGeoChainForPais('El Salvador');
        $hondaId      = (int) $this->findMarcaByName('Honda')['id_marca'];
        $yamahaId     = (int) $this->findMarcaByName('Yamaha')['id_marca'];

        $this->loginAsSupervisorSv();

        // 1. Create agency
        $nombre    = 'Agencia Visitas SV ' . str_replace('.', '', uniqid('', true));
        $createRes = $this->postForm('agencias/guardar', [
            'nombre_agencia'  => $nombre,
            'direccion'       => 'Av. Independencia #12',
            'id_pais'         => $svChain['id_pais'],
            'id_departamento' => $svChain['id_departamento'],
            'id_municipio'    => $svChain['id_municipio'],
            'id_distrito'     => $svChain['id_distrito'],
            'latitud'         => '13.69294000',
            'longitud'        => '-89.21819000',
            'marcas'          => [$hondaId],
        ]);
        $createStatus = $createRes->response()->getStatusCode();
        $this->assertTrue(
            in_array($createStatus, [200, 201, 302, 303], true),
            "Expected create success status in T4-SCEN-07, got {$createStatus}."
        );

        $agency = $this->findAgencyByName($nombre);
        $this->assertNotNull($agency);
        $idAgencia = (int) $agency['idagencia'];
        $this->assertAuditRecorded($idAgencia, 'CREAR', $svCodigo);

        // 2. Update agency
        $updRes = $this->postForm('agencias/actualizar/' . $idAgencia, [
            'nombre_agencia'  => $nombre,
            'direccion'       => 'Av. Independencia #99 Renovada',
            'id_pais'         => $svChain['id_pais'],
            'id_departamento' => $svChain['id_departamento'],
            'id_municipio'    => $svChain['id_municipio'],
            'id_distrito'     => $svChain['id_distrito'],
            'latitud'         => '13.69512000',
            'longitud'        => '-89.21543000',
            'marcas'          => [$hondaId, $yamahaId],
        ]);
        $updStatus = $updRes->response()->getStatusCode();
        $this->assertTrue(
            in_array($updStatus, [200, 201, 302, 303], true),
            "Expected update success status in T4-SCEN-07, got {$updStatus}."
        );
        $this->assertAuditRecorded($idAgencia, 'ACTUALIZAR', $svCodigo);

        // 3. Record 2 commercial advisor Visitas linked to agency & advisor
        $visitasTable  = $this->resolveTableName('Visitas');
        $agenciasTable = $this->resolveTableName('Agencias');
        $usuarioTable  = $this->resolveTableName('Usuario');
        $this->assertNotNull($visitasTable);
        $this->assertNotNull($agenciasTable);
        $this->assertNotNull($usuarioTable);

        $this->db->table($visitasTable)->insert([
            'Idagencia'     => $idAgencia,
            'Codigo_Asesor' => $asesorCodigo,
            'fecha_visita'  => '2026-11-18',
            'hora_visita'   => '09:30:00',
            'latitud'       => 13.69512000,
            'longitud'      => -89.21543000,
            'observaciones' => 'Visita matutina',
            'creado_en'     => '2026-11-18 09:30:00',
        ]);
        $this->db->table($visitasTable)->insert([
            'Idagencia'     => $idAgencia,
            'Codigo_Asesor' => $asesorCodigo,
            'fecha_visita'  => '2026-11-18',
            'hora_visita'   => '15:45:00',
            'latitud'       => 13.69530000,
            'longitud'      => -89.21520000,
            'observaciones' => 'Visita vespertina',
            'creado_en'     => '2026-11-18 15:45:00',
        ]);

        // 4. Verify SQLite FK constraints reject orphan agency / advisor references
        $fkBlockedAgency = false;
        try {
            $this->db->table($visitasTable)->insert([
                'Idagencia'     => 999999,
                'Codigo_Asesor' => $asesorCodigo,
                'fecha_visita'  => '2026-11-18',
                'hora_visita'   => '16:00:00',
                'latitud'       => 13.69500000,
                'longitud'      => -89.21500000,
                'creado_en'     => '2026-11-18 16:00:00',
            ]);
        } catch (\Throwable $e) {
            $fkBlockedAgency = true;
        }
        $this->assertTrue($fkBlockedAgency, 'Visitas FK to Agencias(Idagencia) must reject non-existent agency.');

        $fkBlockedAsesor = false;
        try {
            $this->db->table($visitasTable)->insert([
                'Idagencia'     => $idAgencia,
                'Codigo_Asesor' => 'EMP-INEXISTENTE-999',
                'fecha_visita'  => '2026-11-18',
                'hora_visita'   => '16:15:00',
                'latitud'       => 13.69500000,
                'longitud'      => -89.21500000,
                'creado_en'     => '2026-11-18 16:15:00',
            ]);
        } catch (\Throwable $e) {
            $fkBlockedAsesor = true;
        }
        $this->assertTrue($fkBlockedAsesor, 'Visitas FK to Usuario(Codigo_Empleado) must reject unknown advisor.');

        // 5. Soft-disable the agency and verify full historical integrity preservation
        $motivo = 'Reubicación de sucursal';
        $disRes = $this->postForm('agencias/deshabilitar/' . $idAgencia, [
            'motivo_deshabilitacion' => $motivo,
        ]);
        $disStatus = $disRes->response()->getStatusCode();
        $this->assertTrue(
            in_array($disStatus, [200, 201, 302, 303], true),
            "Expected soft-disable success status in T4-SCEN-07, got {$disStatus}."
        );

        $this->assertAgencySoftDisabled($idAgencia, $svCodigo, $motivo);

        $audits = $this->getAuditLogsForAgency($idAgencia);
        $this->assertCount(3, $audits);
        $this->assertSame(
            ['CREAR', 'ACTUALIZAR', 'DESHABILITAR'],
            array_map(static fn (array $a): string => strtoupper((string) ($a['tipo_operacion'] ?? '')), $audits)
        );

        $joinedRows = $this->normalizeRows(
            $this->db->table($visitasTable . ' v')
                ->select('v.Id_visita, v.Idagencia, v.Codigo_Asesor, v.fecha_visita, v.hora_visita, v.latitud, v.longitud, a.nombre_agencia, a.activo, u.Usuario')
                ->join($agenciasTable . ' a', 'a.Idagencia = v.Idagencia')
                ->join($usuarioTable . ' u', 'u.Codigo_Empleado = v.Codigo_Asesor')
                ->where('v.Idagencia', $idAgencia)
                ->orderBy('v.hora_visita', 'ASC')
                ->get()
                ->getResultArray()
        );
        $this->assertCount(2, $joinedRows);
        $this->assertSame(0, (int) $joinedRows[0]['activo']);
        $this->assertSame($asesorCodigo, (string) $joinedRows[0]['codigo_asesor']);
        $this->assertSame('2026-11-18', (string) $joinedRows[0]['fecha_visita']);
        $this->assertSame('09:30:00', (string) $joinedRows[0]['hora_visita']);
        $this->assertSame('15:45:00', (string) $joinedRows[1]['hora_visita']);
        $this->assertEqualsWithDelta(13.69512, (float) $joinedRows[0]['latitud'], 0.0001);
        $this->assertEqualsWithDelta(-89.21543, (float) $joinedRows[0]['longitud'], 0.0001);

        $showRes = $this->getPage('agencias/ver/' . $idAgencia);
        $showRes->assertStatus(200);
        $showBody = (string) $showRes->getBody();
        $this->assertStringContainsString($motivo, $showBody);
        $this->assertStringContainsString('DESHABILITAR', $showBody);

        $geoAfter = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $this->assertNull($this->findGeoItemById($geoAfter, $idAgencia));
    }

    public function testT4Scen08_InvalidCascadeAndPartialSubmissionRecovery(): void
    {
        $this->requireHU002Schema();

        $svCodigo = (string) $this->findUserByUsername('supervisor_sv')['codigo_empleado'];
        $svPaisId = (int) $this->findPaisByName('El Salvador')['id_pais'];
        $deptoSS  = $this->findDepartamentoByName('San Salvador', $svPaisId);
        $muniSS   = $this->findMunicipioByName('San Salvador Centro', (int) $deptoSS['id_departamento']);
        $distSS   = $this->findDistritoByName('San Salvador', (int) $muniSS['id_municipio']);

        $deptoLL = $this->findDepartamentoByName('La Libertad', $svPaisId);
        $muniLL  = $this->findMunicipioByName('La Libertad Sur', (int) $deptoLL['id_departamento']);
        $distLL  = $this->findDistritoByName('Santa Tecla', (int) $muniLL['id_municipio']);

        $hondaId  = (int) $this->findMarcaByName('Honda')['id_marca'];
        $yamahaId = (int) $this->findMarcaByName('Yamaha')['id_marca'];

        $this->loginAsSupervisorSv();

        $agBefore  = $this->countTableRows('Agencias');
        $amBefore  = $this->countTableRows('Agencia_Marca');
        $audBefore = $this->countTableRows('Auditoria_Agencias');

        // Attempt 1: Mismatched geo hierarchy (San Salvador depto + La Libertad muni/dist)
        $res1 = $this->postForm('agencias/guardar', [
            'nombre_agencia'  => 'Agencia Fallida Cascada ' . uniqid(),
            'direccion'       => 'Calle Error #1',
            'id_pais'         => $svPaisId,
            'id_departamento' => (int) $deptoSS['id_departamento'],
            'id_municipio'    => (int) $muniLL['id_municipio'],
            'id_distrito'     => (int) $distLL['id_distrito'],
            'latitud'         => '13.68123000',
            'longitud'        => '-89.23456000',
            'marcas'          => [$hondaId],
        ]);
        $this->assertAgencyRejectionWithNoMutation($res1, $agBefore, $amBefore, $audBefore);

        // Attempt 2: Empty brands
        $res2 = $this->postForm('agencias/guardar', [
            'nombre_agencia'  => 'Agencia Fallida SinMarcas ' . uniqid(),
            'direccion'       => 'Calle Error #2',
            'id_pais'         => $svPaisId,
            'id_departamento' => (int) $deptoSS['id_departamento'],
            'id_municipio'    => (int) $muniSS['id_municipio'],
            'id_distrito'     => (int) $distSS['id_distrito'],
            'latitud'         => '13.68123000',
            'longitud'        => '-89.23456000',
            'marcas'          => [],
        ]);
        $this->assertAgencyRejectionWithNoMutation($res2, $agBefore, $amBefore, $audBefore);

        // Attempt 3: Invalid coordinates
        $res3 = $this->postForm('agencias/guardar', [
            'nombre_agencia'  => 'Agencia Fallida Coords ' . uniqid(),
            'direccion'       => 'Calle Error #3',
            'id_pais'         => $svPaisId,
            'id_departamento' => (int) $deptoSS['id_departamento'],
            'id_municipio'    => (int) $muniSS['id_municipio'],
            'id_distrito'     => (int) $distSS['id_distrito'],
            'latitud'         => '95.00000000',
            'longitud'        => '-200.00000000',
            'marcas'          => [$hondaId],
        ]);
        $this->assertAgencyRejectionWithNoMutation($res3, $agBefore, $amBefore, $audBefore);

        // Attempt 4: Missing mandatory district for El Salvador (requiere_distrito = 1)
        $res4 = $this->postForm('agencias/guardar', [
            'nombre_agencia'  => 'Agencia Fallida SinDistrito ' . uniqid(),
            'direccion'       => 'Calle Error #4',
            'id_pais'         => $svPaisId,
            'id_departamento' => (int) $deptoSS['id_departamento'],
            'id_municipio'    => (int) $muniSS['id_municipio'],
            'id_distrito'     => '',
            'latitud'         => '13.68123000',
            'longitud'        => '-89.23456000',
            'marcas'          => [$hondaId],
        ]);
        $this->assertAgencyRejectionWithNoMutation($res4, $agBefore, $amBefore, $audBefore);

        // Recovery: Valid submission after partial/invalid attempts
        $validName = 'Agencia SV Recuperada ' . str_replace('.', '', uniqid('', true));
        $resOk     = $this->postForm('agencias/guardar', [
            'nombre_agencia'  => $validName,
            'direccion'       => 'Blvd. Los Próceres #250',
            'id_pais'         => $svPaisId,
            'id_departamento' => (int) $deptoSS['id_departamento'],
            'id_municipio'    => (int) $muniSS['id_municipio'],
            'id_distrito'     => (int) $distSS['id_distrito'],
            'latitud'         => '13.68123000',
            'longitud'        => '-89.23456000',
            'marcas'          => [$hondaId, $yamahaId],
        ]);
        $okStatus = $resOk->response()->getStatusCode();
        $this->assertTrue(
            in_array($okStatus, [200, 201, 302, 303], true),
            "Expected recovery create success status in T4-SCEN-08, got {$okStatus}."
        );

        $this->assertSame($agBefore + 1, $this->countTableRows('Agencias'));
        $this->assertSame($amBefore + 2, $this->countTableRows('Agencia_Marca'));
        $this->assertSame($audBefore + 1, $this->countTableRows('Auditoria_Agencias'));

        $recovered = $this->findAgencyByName($validName);
        $this->assertNotNull($recovered);
        $idRecovered = (int) $recovered['idagencia'];
        $this->assertSame(1, (int) $recovered['activo']);
        $this->assertAuditRecorded($idRecovered, 'CREAR', $svCodigo);

        $geoJson = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $this->assertNotNull($this->findGeoItemById($geoJson, $idRecovered));
    }

    // =========================================================================
    // Test-Local Opaque-Box Helpers
    // =========================================================================

    /**
     * @return array<string, mixed>
     */
    private function findUserByUsername(string $username): array
    {
        $usuarioTable = $this->resolveTableName('Usuario');
        $this->assertNotNull($usuarioTable, 'Usuario table must exist.');

        $row = $this->db->table($usuarioTable)
            ->where('Usuario', $username)
            ->get()
            ->getRowArray();

        $this->assertNotNull($row, "User '{$username}' not found in Usuario table.");

        return $this->normalizeRow($row);
    }

    private function countTableRows(string $tableName): int
    {
        $resolved = $this->resolveTableName($tableName);
        $this->assertNotNull($resolved, "Table '{$tableName}' does not exist.");

        return (int) $this->db->table($resolved)->countAllResults();
    }

    private function assertLeafletMapMarkup(string $html, bool $expectClickHandler = false): void
    {
        $this->assertMatchesRegularExpression(
            '/leaflet(?:\.min)?\.(?:css|js)/i',
            $html,
            'Expected locally vendored Leaflet asset reference.'
        );
        $this->assertStringContainsString('L.map', $html, 'Expected Leaflet L.map initialization.');
        $this->assertMatchesRegularExpression(
            '/openstreetmap\.org/i',
            $html,
            'Expected OpenStreetMap tile layer reference.'
        );

        if ($expectClickHandler) {
            $this->assertMatchesRegularExpression(
                '/\.on\(\s*[\'"]click[\'"]/i',
                $html,
                'Expected Leaflet map click handler for coordinate capture.'
            );
            $this->assertMatchesRegularExpression('/name=["\']latitud["\']/i', $html);
            $this->assertMatchesRegularExpression('/name=["\']longitud["\']/i', $html);
        }
    }

    private function assertNoUserAdminNavLinks(string $html): void
    {
        $this->assertSame(
            0,
            preg_match('/<a\b[^>]*href=["\'][^"\']*\/(?:usuarios|roles|permisos)(?:[\/?#"\']|$)/i', $html),
            'Supervisor UI must not expose user, role, or permission administration links.'
        );
    }

    /**
     * @param array<string, mixed> $decodedJson
     * @return array<string, mixed>|null
     */
    private function findGeoItemById(array $decodedJson, int $idAgencia): ?array
    {
        $items = $decodedJson['agencias'] ?? [];
        if (! is_array($items)) {
            return null;
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $itemId = (int) ($item['id'] ?? $item['idagencia'] ?? 0);
            if ($itemId === $idAgencia) {
                return $item;
            }
        }

        return null;
    }

    private function assertAgencyRejectionWithNoMutation(
        TestResponse $res,
        int $agBefore,
        int $amBefore,
        int $audBefore
    ): void {
        $status = $res->response()->getStatusCode();
        $this->assertNotSame(404, $status, 'Expected implemented agencias/guardar validation endpoint, got 404.');
        $this->assertNotSame(201, $status, 'Invalid submission must not return 201 Created.');
        $this->assertLessThan(500, $status, "Invalid submission must not trigger HTTP 5xx, got {$status}.");
        $this->assertTrue(
            in_array($status, [200, 302, 303, 400, 422], true),
            "Expected validation rejection status, got {$status}."
        );

        if (in_array($status, [302, 303], true)) {
            $this->assertDoesNotMatchRegularExpression('#/agencias/ver/\d+#i', (string) $res->getRedirectUrl());
        }

        $this->assertSame($agBefore, $this->countTableRows('Agencias'));
        $this->assertSame($amBefore, $this->countTableRows('Agencia_Marca'));
        $this->assertSame($audBefore, $this->countTableRows('Auditoria_Agencias'));
    }
}
