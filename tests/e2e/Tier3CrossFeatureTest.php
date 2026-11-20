<?php

declare(strict_types=1);

namespace Tests\E2E;

require_once __DIR__ . '/E2ETestCase.php';

use CodeIgniter\Test\TestResponse;

/**
 * Tier 3: Orthogonal Pairwise Cross-Feature Combinatorial Tests (T3-PAIR-01..T3-PAIR-16).
 *
 * Strictly opaque-box tests covering interactions across Role, Country,
 * requiere_distrito mode, Coordinates, Brand cardinality, Status, and Operations/Filters.
 * Uses SQLite :memory: with dynamic ID/employee lookups, zero mocks, and zero environment-file access.
 */
final class Tier3CrossFeatureTest extends E2ETestCase
{
    public function testT3Pair01_SupervisorSvValidCoordsSingleBrandActiveAcrossListMapAndGeoApi(): void
    {
        $this->requireHU002Schema();
        $this->actingAsUser('supervisor_sv');

        $svChain = $this->getGeoChainForPais('El Salvador');
        $this->assertSame(1, $svChain['requiere_distrito']);
        $this->assertNotNull($svChain['id_distrito']);

        $honda     = $this->findMarcaByName('Honda');
        $hondaId   = (int) $honda['id_marca'];
        $nombre    = 'Agencia Pair01 ' . str_replace('.', '', uniqid('', true));
        $direccion = 'Blvd. Los Héroes #101, San Salvador';

        $createRes = $this->postForm('agencias/guardar', [
            'nombre_agencia'  => $nombre,
            'direccion'       => $direccion,
            'id_pais'         => $svChain['id_pais'],
            'id_departamento' => $svChain['id_departamento'],
            'id_municipio'    => $svChain['id_municipio'],
            'id_distrito'     => $svChain['id_distrito'],
            'latitud'         => '13.69294000',
            'longitud'        => '-89.21819000',
            'marcas'          => [$hondaId],
        ]);

        $status = $createRes->response()->getStatusCode();
        $this->assertTrue(
            in_array($status, [200, 201, 302, 303], true),
            "Expected create success status for T3-PAIR-01, got {$status}."
        );

        $agency = $this->findAgencyByName($nombre);
        $this->assertNotNull($agency, "Agency '{$nombre}' must be persisted.");
        $idAgencia = (int) $agency['idagencia'];

        $this->assertSame(1, (int) $agency['activo']);
        $this->assertSame($svChain['id_pais'], (int) $agency['id_pais']);
        $this->assertSame($svChain['id_departamento'], (int) $agency['id_departamento']);
        $this->assertSame($svChain['id_municipio'], (int) $agency['id_municipio']);
        $this->assertSame($svChain['id_distrito'], (int) $agency['id_distrito']);
        $this->assertEqualsWithDelta(13.69294, (float) $agency['latitud'], 0.0001);
        $this->assertEqualsWithDelta(-89.21819, (float) $agency['longitud'], 0.0001);

        $this->assertSame([$hondaId], $this->getAgencyBrandIds($idAgencia));
        $this->assertAuditRecorded($idAgencia, 'CREAR', $this->activeEmployeeCode());

        $listRes = $this->getPage('agencias');
        $listRes->assertStatus(200);
        $listBody = (string) $listRes->getBody();
        $this->assertStringContainsString($nombre, $listBody);
        $this->assertStringContainsString($direccion, $listBody);
        $this->assertStringContainsString('Honda', $listBody);

        $showRes = $this->getPage('agencias/ver/' . $idAgencia);
        $showRes->assertStatus(200);
        $showBody = (string) $showRes->getBody();
        $this->assertStringContainsString($nombre, $showBody);
        $this->assertStringContainsString((string) $svChain['distrito'], $showBody);
        $this->assertStringContainsString('Honda', $showBody);

        $mapRes = $this->getPage('agencias/mapa');
        $mapRes->assertStatus(200);
        $mapBody = (string) $mapRes->getBody();
        $this->assertStringContainsStringIgnoringCase('leaflet', $mapBody);
        $this->assertStringContainsStringIgnoringCase('openstreetmap', $mapBody);
        $this->assertStringContainsString($nombre, $mapBody);

        $geoRes  = $this->getJson('api/agencias/geolocalizacion');
        $geoJson = $this->assertJsonOk($geoRes);
        $this->assertSame('El Salvador', $geoJson['pais'] ?? null);
        $this->assertGreaterThanOrEqual(1, (int) ($geoJson['total'] ?? 0));

        $item = $this->findGeoItemById($geoJson, $idAgencia);
        $this->assertNotNull($item, "Agency {$idAgencia} must appear in geolocation API.");
        $this->assertSame($nombre, $item['nombre'] ?? null);
        $this->assertSame('El Salvador', $item['pais'] ?? null);
        $this->assertSame($direccion, $item['direccion'] ?? null);
        $this->assertEqualsCanonicalizing(['Honda'], $item['marcas'] ?? []);
        $this->assertSame($svChain['distrito'], $item['distrito'] ?? null);
        $this->assertEqualsWithDelta(13.69294, (float) ($item['latitud'] ?? 0), 0.0001);
        $this->assertEqualsWithDelta(-89.21819, (float) ($item['longitud'] ?? 0), 0.0001);
    }

    public function testT3Pair02_SupervisorSvValidCoordsThreeBrandsActiveGeoApiAndBrandFilters(): void
    {
        $this->requireHU002Schema();
        $this->actingAsUser('supervisor_sv');

        $svChain  = $this->getGeoChainForPais('El Salvador');
        $hondaId  = (int) $this->findMarcaByName('Honda')['id_marca'];
        $yamahaId = (int) $this->findMarcaByName('Yamaha')['id_marca'];
        $suzukiId = (int) $this->findMarcaByName('Suzuki')['id_marca'];
        $heroId   = (int) $this->findMarcaByName('Hero')['id_marca'];

        $nombre = 'Agencia Pair02 ' . str_replace('.', '', uniqid('', true));

        $createRes = $this->postForm('agencias/guardar', [
            'nombre_agencia'  => $nombre,
            'direccion'       => 'Alameda Manuel Enrique Araujo #220',
            'id_pais'         => $svChain['id_pais'],
            'id_departamento' => $svChain['id_departamento'],
            'id_municipio'    => $svChain['id_municipio'],
            'id_distrito'     => $svChain['id_distrito'],
            'latitud'         => '13.70120000',
            'longitud'        => '-89.22440000',
            'marcas'          => [$hondaId, $yamahaId, $suzukiId],
        ]);

        $status = $createRes->response()->getStatusCode();
        $this->assertTrue(
            in_array($status, [200, 201, 302, 303], true),
            "Expected create success status for T3-PAIR-02, got {$status}."
        );

        $agency = $this->findAgencyByName($nombre);
        $this->assertNotNull($agency, "Agency '{$nombre}' must be persisted.");
        $idAgencia = (int) $agency['idagencia'];

        $this->assertEqualsCanonicalizing(
            ['Honda', 'Suzuki', 'Yamaha'],
            $this->getAgencyBrandNames($idAgencia)
        );

        $allJson = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $allItem = $this->findGeoItemById($allJson, $idAgencia);
        $this->assertNotNull($allItem);
        $this->assertCount(3, $allItem['marcas'] ?? []);
        $this->assertEqualsCanonicalizing(['Honda', 'Yamaha', 'Suzuki'], $allItem['marcas'] ?? []);

        $byIdJson = $this->assertJsonOk(
            $this->getJson('api/agencias/geolocalizacion', ['id_marca' => $yamahaId])
        );
        $byIdItem = $this->findGeoItemById($byIdJson, $idAgencia);
        $this->assertNotNull($byIdItem);
        $this->assertEqualsCanonicalizing(['Honda', 'Yamaha', 'Suzuki'], $byIdItem['marcas'] ?? []);

        $byNameJson = $this->assertJsonOk(
            $this->getJson('api/agencias/geolocalizacion', ['marca' => 'Suzuki'])
        );
        $byNameItem = $this->findGeoItemById($byNameJson, $idAgencia);
        $this->assertNotNull($byNameItem);
        $this->assertEqualsCanonicalizing(['Honda', 'Yamaha', 'Suzuki'], $byNameItem['marcas'] ?? []);

        $unmatchedJson = $this->assertJsonOk(
            $this->getJson('api/agencias/geolocalizacion', ['id_marca' => $heroId])
        );
        $this->assertNull($this->findGeoItemById($unmatchedJson, $idAgencia));
    }

    public function testT3Pair03_SupervisorGtValidCoordsNullDistrictTwoBrandsActive(): void
    {
        $this->requireHU002Schema();
        $this->actingAsUser('supervisor_gt');

        $gtChain = $this->getGeoChainForPais('Guatemala');
        $this->assertSame(0, $gtChain['requiere_distrito']);

        $hondaId  = (int) $this->findMarcaByName('Honda')['id_marca'];
        $yamahaId = (int) $this->findMarcaByName('Yamaha')['id_marca'];
        $nombre   = 'Agencia Pair03 GT ' . str_replace('.', '', uniqid('', true));

        $createRes = $this->postForm('agencias/guardar', [
            'nombre_agencia'  => $nombre,
            'direccion'       => '6a Avenida 10-20 Zona 1, Guatemala',
            'id_pais'         => $gtChain['id_pais'],
            'id_departamento' => $gtChain['id_departamento'],
            'id_municipio'    => $gtChain['id_municipio'],
            'latitud'         => '14.63491000',
            'longitud'        => '-90.50688000',
            'marcas'          => [$hondaId, $yamahaId],
        ]);

        $status = $createRes->response()->getStatusCode();
        $this->assertTrue(
            in_array($status, [200, 201, 302, 303], true),
            "Expected create success status for T3-PAIR-03, got {$status}."
        );

        $agency = $this->findAgencyByName($nombre);
        $this->assertNotNull($agency);
        $idAgencia = (int) $agency['idagencia'];

        $this->assertSame($gtChain['id_pais'], (int) $agency['id_pais']);
        $this->assertNull($agency['id_distrito']);
        $this->assertSame(1, (int) $agency['activo']);
        $this->assertEqualsCanonicalizing(['Honda', 'Yamaha'], $this->getAgencyBrandNames($idAgencia));
        $this->assertAuditRecorded($idAgencia, 'CREAR', $this->activeEmployeeCode());

        $geoJson = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $this->assertSame('Guatemala', $geoJson['pais'] ?? null);
        $item = $this->findGeoItemById($geoJson, $idAgencia);
        $this->assertNotNull($item);
        $this->assertArrayHasKey('distrito', $item);
        $this->assertNull($item['distrito']);
        $this->assertEqualsCanonicalizing(['Honda', 'Yamaha'], $item['marcas'] ?? []);

        $showRes = $this->getPage('agencias/ver/' . $idAgencia);
        $showRes->assertStatus(200);
        $showBody = (string) $showRes->getBody();
        $this->assertStringContainsString($nombre, $showBody);
        $this->assertStringContainsString('Guatemala', $showBody);
        $this->assertStringContainsString('Honda', $showBody);
        $this->assertStringContainsString('Yamaha', $showBody);
    }

    public function testT3Pair04_SupervisorGtNullCoordsNullDistrictOneBrandActive(): void
    {
        $this->requireHU002Schema();

        $gtChain = $this->getGeoChainForPais('Guatemala');
        $hondaId = (int) $this->findMarcaByName('Honda')['id_marca'];
        $nombre  = 'Agencia Pair04 SinCoords ' . str_replace('.', '', uniqid('', true));

        $agency = $this->insertAgencyFixture($gtChain, [$hondaId], [
            'nombre_agencia' => $nombre,
            'Id_distrito'    => null,
            'latitud'        => null,
            'longitud'       => null,
            'activo'         => 1,
        ]);
        $agencyId = (int) $agency['idagencia'];

        $this->actingAsUser('supervisor_gt');

        $listRes = $this->getPage('agencias');
        $listRes->assertStatus(200);
        $this->assertStringContainsString($nombre, (string) $listRes->getBody());

        $showRes = $this->getPage('agencias/ver/' . $agencyId);
        $showRes->assertStatus(200);
        $showBody = (string) $showRes->getBody();
        $this->assertStringContainsString($nombre, $showBody);
        $this->assertStringContainsString('Honda', $showBody);

        $mapRes = $this->getPage('agencias/mapa');
        $mapRes->assertStatus(200);
        $mapBody = (string) $mapRes->getBody();
        $this->assertStringContainsStringIgnoringCase('leaflet', $mapBody);
        $this->assertStringContainsStringIgnoringCase('openstreetmap', $mapBody);
        $this->assertStringNotContainsString($nombre, $mapBody);

        $geoJson = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $this->assertSame('Guatemala', $geoJson['pais'] ?? null);
        $this->assertSame(0, (int) ($geoJson['total'] ?? -1));
        $this->assertNull($this->findGeoItemById($geoJson, $agencyId));
    }

    public function testT3Pair05_SupervisorSvValidCoordsOneBrandDisabledVisibleInInactiveListExcludedFromMapAndApi(): void
    {
        $this->requireHU002Schema();
        $this->actingAsUser('supervisor_sv');

        $svChain = $this->getGeoChainForPais('El Salvador');
        $hondaId = (int) $this->findMarcaByName('Honda')['id_marca'];
        $nombre  = 'Agencia Pair05 Inactiva ' . str_replace('.', '', uniqid('', true));
        $motivo  = 'Cierre temporal por inventario';

        $agency = $this->insertAgencyFixture($svChain, [$hondaId], [
            'nombre_agencia'        => $nombre,
            'latitud'               => 13.69880000,
            'longitud'              => -89.20550000,
            'activo'                => 0,
            'motivo_deshabilitacion' => $motivo,
            'deshabilitado_en'      => '2026-11-18 11:00:00',
            'deshabilitado_por'     => $this->activeEmployeeCode(),
        ]);
        $agencyId = (int) $agency['idagencia'];

        $inactRes = $this->getPage('agencias', ['estado' => 'inactivas']);
        $inactRes->assertStatus(200);
        $inactBody = (string) $inactRes->getBody();
        $this->assertStringContainsString($nombre, $inactBody);
        $this->assertStringContainsString($motivo, $inactBody);

        $actRes = $this->getPage('agencias', ['estado' => 'activas']);
        $actRes->assertStatus(200);
        $this->assertStringNotContainsString($nombre, (string) $actRes->getBody());

        $showRes = $this->getPage('agencias/ver/' . $agencyId);
        $showRes->assertStatus(200);
        $showBody = (string) $showRes->getBody();
        $this->assertStringContainsString($nombre, $showBody);
        $this->assertStringContainsString($motivo, $showBody);
        $this->assertStringContainsString('Honda', $showBody);

        $mapRes = $this->getPage('agencias/mapa');
        $mapRes->assertStatus(200);
        $this->assertStringNotContainsString($nombre, (string) $mapRes->getBody());

        $geoJson = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $this->assertSame(0, (int) ($geoJson['total'] ?? -1));
        $this->assertNull($this->findGeoItemById($geoJson, $agencyId));
    }

    public function testT3Pair06_SupervisorGtValidCoordsOneBrandSoftDisableLogsAuditAndExcludesFromMapAndApi(): void
    {
        $this->requireHU002Schema();

        $gtChain  = $this->getGeoChainForPais('Guatemala');
        $yamahaId = (int) $this->findMarcaByName('Yamaha')['id_marca'];
        $nombre   = 'Agencia Pair06 GT Baja ' . str_replace('.', '', uniqid('', true));

        $agency = $this->insertAgencyFixture($gtChain, [$yamahaId], [
            'nombre_agencia' => $nombre,
            'Id_distrito'    => null,
            'latitud'        => 14.63491000,
            'longitud'       => -90.50688000,
            'activo'         => 1,
        ]);
        $agencyId = (int) $agency['idagencia'];

        $this->actingAsUser('supervisor_gt');
        $motivo = 'Reubicación de distribuidor Guatemala';

        $disableRes = $this->postForm('agencias/deshabilitar/' . $agencyId, [
            'motivo_deshabilitacion' => $motivo,
        ]);
        $status = $disableRes->response()->getStatusCode();
        $this->assertTrue(
            in_array($status, [200, 302, 303], true),
            "Expected soft-disable success status for T3-PAIR-06, got {$status}."
        );

        $this->assertAgencySoftDisabled($agencyId, $this->activeEmployeeCode(), $motivo);
        $this->assertSame([$yamahaId], $this->getAgencyBrandIds($agencyId));

        $audit = $this->assertAuditRecorded($agencyId, 'DESHABILITAR', $this->activeEmployeeCode(), $motivo);
        if (! empty($audit['datos_anteriores'])) {
            $before = $this->decodeAuditSnapshot((string) $audit['datos_anteriores']);
            $this->assertSame(1, (int) ($before['activo'] ?? 1));
        }
        if (! empty($audit['datos_nuevos'])) {
            $after = $this->decodeAuditSnapshot((string) $audit['datos_nuevos']);
            $this->assertSame(0, (int) ($after['activo'] ?? 0));
        }

        $mapRes = $this->getPage('agencias/mapa');
        $mapRes->assertStatus(200);
        $this->assertStringNotContainsString($nombre, (string) $mapRes->getBody());

        $geoJson = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $this->assertSame('Guatemala', $geoJson['pais'] ?? null);
        $this->assertSame(0, (int) ($geoJson['total'] ?? -1));
        $this->assertNull($this->findGeoItemById($geoJson, $agencyId));

        $showRes = $this->getPage('agencias/ver/' . $agencyId);
        $showRes->assertStatus(200);
        $showBody = (string) $showRes->getBody();
        $this->assertStringContainsString($nombre, $showBody);
        $this->assertStringContainsString($motivo, $showBody);
        $this->assertStringContainsString('DESHABILITAR', $showBody);
    }

    public function testT3Pair07_SupervisorHnExtensibleCountryValidCoordsTwoBrandsActiveIsolatedFromSvAndGt(): void
    {
        $this->requireHU002Schema();

        $svChain = $this->getGeoChainForPais('El Salvador');
        $gtChain = $this->getGeoChainForPais('Guatemala');
        $hnChain = $this->getGeoChainForPais('Honduras');

        $this->assertSame(1, $hnChain['requiere_distrito']);
        $this->assertNotNull($hnChain['id_distrito']);

        $hondaId = (int) $this->findMarcaByName('Honda')['id_marca'];
        $ktmId   = (int) $this->findMarcaByName('KTM')['id_marca'];

        $svName = 'Agencia Pair07 SV ' . str_replace('.', '', uniqid('', true));
        $gtName = 'Agencia Pair07 GT ' . str_replace('.', '', uniqid('', true));
        $hnName = 'Agencia Pair07 HN ' . str_replace('.', '', uniqid('', true));

        $this->insertAgencyFixture($svChain, [$hondaId], ['nombre_agencia' => $svName]);
        $this->insertAgencyFixture($gtChain, [$hondaId], [
            'nombre_agencia' => $gtName,
            'Id_distrito'    => null,
            'latitud'        => 14.63491000,
            'longitud'       => -90.50688000,
        ]);

        $this->actingAsUser('supervisor_hn');
        $hnActor = $this->activeEmployeeCode();

        $createRes = $this->postForm('agencias/guardar', [
            'nombre_agencia'  => $hnName,
            'direccion'       => 'Blvd. Morazán #305, Tegucigalpa',
            'id_pais'         => $hnChain['id_pais'],
            'id_departamento' => $hnChain['id_departamento'],
            'id_municipio'    => $hnChain['id_municipio'],
            'id_distrito'     => $hnChain['id_distrito'],
            'latitud'         => '14.07227000',
            'longitud'        => '-87.19213000',
            'marcas'          => [$hondaId, $ktmId],
        ]);

        $status = $createRes->response()->getStatusCode();
        $this->assertTrue(
            in_array($status, [200, 201, 302, 303], true),
            "Expected HN create success status for T3-PAIR-07, got {$status}."
        );

        $hnAgency = $this->findAgencyByName($hnName);
        $this->assertNotNull($hnAgency);
        $hnId = (int) $hnAgency['idagencia'];

        $this->assertSame($hnChain['id_pais'], (int) $hnAgency['id_pais']);
        $this->assertSame($hnChain['id_distrito'], (int) $hnAgency['id_distrito']);
        $this->assertEqualsCanonicalizing(['Honda', 'KTM'], $this->getAgencyBrandNames($hnId));
        $this->assertAuditRecorded($hnId, 'CREAR', $hnActor);

        $hnListRes = $this->getPage('agencias');
        $hnListRes->assertStatus(200);
        $hnListBody = (string) $hnListRes->getBody();
        $this->assertStringContainsString($hnName, $hnListBody);
        $this->assertStringNotContainsString($svName, $hnListBody);
        $this->assertStringNotContainsString($gtName, $hnListBody);

        $hnMapRes = $this->getPage('agencias/mapa');
        $hnMapRes->assertStatus(200);
        $hnMapBody = (string) $hnMapRes->getBody();
        $this->assertStringContainsString($hnName, $hnMapBody);
        $this->assertStringNotContainsString($svName, $hnMapBody);
        $this->assertStringNotContainsString($gtName, $hnMapBody);

        $hnGeoJson = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $this->assertSame('Honduras', $hnGeoJson['pais'] ?? null);
        $hnGeoItem = $this->findGeoItemById($hnGeoJson, $hnId);
        $this->assertNotNull($hnGeoItem);
        $this->assertSame('Honduras', $hnGeoItem['pais'] ?? null);
        $this->assertEqualsCanonicalizing(['Honda', 'KTM'], $hnGeoItem['marcas'] ?? []);

        // Cross-country isolation from SV supervisor
        $this->actingAsUser('supervisor_sv');
        $svList = $this->getPage('agencias');
        $svList->assertStatus(200);
        $this->assertStringNotContainsString($hnName, (string) $svList->getBody());

        $svGeo = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $this->assertNull($this->findGeoItemById($svGeo, $hnId));

        $this->assertForbiddenHtmlOrRedirectBlocked($this->getPage('agencias/ver/' . $hnId));
        $this->assertForbiddenHtmlOrRedirectBlocked($this->postForm('agencias/deshabilitar/' . $hnId, [
            'motivo_deshabilitacion' => 'Intento cruzado SV->HN',
        ]));

        // Cross-country isolation from GT supervisor
        $this->actingAsUser('supervisor_gt');
        $gtList = $this->getPage('agencias');
        $gtList->assertStatus(200);
        $this->assertStringNotContainsString($hnName, (string) $gtList->getBody());

        $gtGeo = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $this->assertNull($this->findGeoItemById($gtGeo, $hnId));

        $this->assertForbiddenHtmlOrRedirectBlocked($this->getPage('agencias/editar/' . $hnId));

        $reloadedHn = $this->findAgencyById($hnId);
        $this->assertNotNull($reloadedHn);
        $this->assertSame(1, (int) $reloadedHn['activo']);
    }

    public function testT3Pair08_SupervisorSvBrandAndDepartmentFilterIntersectionScopedToSv(): void
    {
        $this->requireHU002Schema();

        $svPais   = $this->findPaisByName('El Salvador');
        $svPaisId = (int) $svPais['id_pais'];

        $deptoSS = $this->findDepartamentoByName('San Salvador', $svPaisId);
        $muniSS  = $this->findAnyMunicipioForDepartamento((int) $deptoSS['id_departamento']);
        $distSS  = $this->findAnyDistritoForMunicipio((int) $muniSS['id_municipio']);
        $this->assertNotNull($distSS);

        $deptoLL = $this->findDepartamentoByName('La Libertad', $svPaisId);
        $muniLL  = $this->findAnyMunicipioForDepartamento((int) $deptoLL['id_departamento']);
        $distLL  = $this->findAnyDistritoForMunicipio((int) $muniLL['id_municipio']);
        $this->assertNotNull($distLL);

        $gtChain  = $this->getGeoChainForPais('Guatemala');
        $hondaId  = (int) $this->findMarcaByName('Honda')['id_marca'];
        $yamahaId = (int) $this->findMarcaByName('Yamaha')['id_marca'];

        $ssChain = [
            'id_pais'         => $svPaisId,
            'id_departamento' => (int) $deptoSS['id_departamento'],
            'id_municipio'    => (int) $muniSS['id_municipio'],
            'id_distrito'     => (int) $distSS['id_distrito'],
        ];
        $llChain = [
            'id_pais'         => $svPaisId,
            'id_departamento' => (int) $deptoLL['id_departamento'],
            'id_municipio'    => (int) $muniLL['id_municipio'],
            'id_distrito'     => (int) $distLL['id_distrito'],
        ];

        $agMatch = $this->insertAgencyFixture($ssChain, [$hondaId], [
            'nombre_agencia' => 'Agencia Pair08 Match ' . uniqid(),
        ]);
        $agWrongBrand = $this->insertAgencyFixture($ssChain, [$yamahaId], [
            'nombre_agencia' => 'Agencia Pair08 WrongBrand ' . uniqid(),
        ]);
        $agWrongDepto = $this->insertAgencyFixture($llChain, [$hondaId], [
            'nombre_agencia' => 'Agencia Pair08 WrongDepto ' . uniqid(),
        ]);
        $agForeignGt = $this->insertAgencyFixture($gtChain, [$hondaId], [
            'nombre_agencia' => 'Agencia Pair08 ForeignGT ' . uniqid(),
            'Id_distrito'    => null,
            'latitud'        => 14.63491000,
            'longitud'       => -90.50688000,
        ]);

        $this->actingAsUser('supervisor_sv');

        $query = [
            'id_marca'        => $hondaId,
            'id_departamento' => (int) $deptoSS['id_departamento'],
        ];

        $geoJson = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion', $query));
        $this->assertSame('El Salvador', $geoJson['pais'] ?? null);
        $this->assertSame(1, (int) ($geoJson['total'] ?? -1));
        $this->assertCount(1, $geoJson['agencias'] ?? []);
        $this->assertSame(
            (int) $agMatch['idagencia'],
            (int) ($geoJson['agencias'][0]['id'] ?? $geoJson['agencias'][0]['idagencia'] ?? 0)
        );

        $listRes = $this->getPage('agencias', $query);
        $listRes->assertStatus(200);
        $listBody = (string) $listRes->getBody();
        $this->assertStringContainsString((string) $agMatch['nombre_agencia'], $listBody);
        $this->assertStringNotContainsString((string) $agWrongBrand['nombre_agencia'], $listBody);
        $this->assertStringNotContainsString((string) $agWrongDepto['nombre_agencia'], $listBody);
        $this->assertStringNotContainsString((string) $agForeignGt['nombre_agencia'], $listBody);
    }

    public function testT3Pair09_SupervisorSvMunicipalityAndDistrictFilterExcludesGtAgencies(): void
    {
        $this->requireHU002Schema();

        $svPaisId   = (int) $this->findPaisByName('El Salvador')['id_pais'];
        $deptoSS    = $this->findDepartamentoByName('San Salvador', $svPaisId);
        $muniCentro = $this->findMunicipioByName('San Salvador Centro', (int) $deptoSS['id_departamento']);
        $distSS     = $this->findDistritoByName('San Salvador', (int) $muniCentro['id_municipio']);
        $distMej    = $this->findDistritoByName('Mejicanos', (int) $muniCentro['id_municipio']);
        $muniEste   = $this->findMunicipioByName('San Salvador Este', (int) $deptoSS['id_departamento']);
        $distEste   = $this->findAnyDistritoForMunicipio((int) $muniEste['id_municipio']);
        $this->assertNotNull($distEste);

        $gtChain = $this->getGeoChainForPais('Guatemala');
        $hondaId = (int) $this->findMarcaByName('Honda')['id_marca'];

        $agTarget = $this->insertAgencyFixture([
            'id_pais'         => $svPaisId,
            'id_departamento' => (int) $deptoSS['id_departamento'],
            'id_municipio'    => (int) $muniCentro['id_municipio'],
            'id_distrito'     => (int) $distSS['id_distrito'],
        ], [$hondaId], ['nombre_agencia' => 'Agencia Pair09 Target ' . uniqid()]);

        $agOtherDist = $this->insertAgencyFixture([
            'id_pais'         => $svPaisId,
            'id_departamento' => (int) $deptoSS['id_departamento'],
            'id_municipio'    => (int) $muniCentro['id_municipio'],
            'id_distrito'     => (int) $distMej['id_distrito'],
        ], [$hondaId], ['nombre_agencia' => 'Agencia Pair09 Mejicanos ' . uniqid()]);

        $agOtherMuni = $this->insertAgencyFixture([
            'id_pais'         => $svPaisId,
            'id_departamento' => (int) $deptoSS['id_departamento'],
            'id_municipio'    => (int) $muniEste['id_municipio'],
            'id_distrito'     => (int) $distEste['id_distrito'],
        ], [$hondaId], ['nombre_agencia' => 'Agencia Pair09 Soyapango ' . uniqid()]);

        $agGt = $this->insertAgencyFixture($gtChain, [$hondaId], [
            'nombre_agencia' => 'Agencia Pair09 GT ' . uniqid(),
            'Id_distrito'    => null,
            'latitud'        => 14.63491000,
            'longitud'       => -90.50688000,
        ]);

        $this->actingAsUser('supervisor_sv');

        $munisJson = $this->assertJsonOk($this->getJson('api/geo/municipios', [
            'id_departamento' => (int) $deptoSS['id_departamento'],
        ]));
        $muniNames = array_map(
            static fn (array $m): string => (string) ($m['municipio'] ?? ''),
            $munisJson['municipios'] ?? []
        );
        $this->assertContains('San Salvador Centro', $muniNames);
        $this->assertContains('San Salvador Este', $muniNames);

        $distsJson = $this->assertJsonOk($this->getJson('api/geo/distritos', [
            'id_municipio' => (int) $muniCentro['id_municipio'],
        ]));
        $distNames = array_map(
            static fn (array $d): string => (string) ($d['distrito'] ?? ''),
            $distsJson['distritos'] ?? []
        );
        $this->assertContains('San Salvador', $distNames);
        $this->assertContains('Mejicanos', $distNames);

        $byMuniJson = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion', [
            'id_municipio' => (int) $muniCentro['id_municipio'],
        ]));
        $this->assertSame(2, (int) ($byMuniJson['total'] ?? -1));
        $muniIds = array_map(
            static fn (array $a): int => (int) ($a['id'] ?? $a['idagencia'] ?? 0),
            $byMuniJson['agencias'] ?? []
        );
        $this->assertEqualsCanonicalizing(
            [(int) $agTarget['idagencia'], (int) $agOtherDist['idagencia']],
            $muniIds
        );
        $this->assertNotContains((int) $agOtherMuni['idagencia'], $muniIds);
        $this->assertNotContains((int) $agGt['idagencia'], $muniIds);

        $byMuniDistJson = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion', [
            'id_municipio' => (int) $muniCentro['id_municipio'],
            'id_distrito'  => (int) $distSS['id_distrito'],
        ]));
        $this->assertSame(1, (int) ($byMuniDistJson['total'] ?? -1));
        $this->assertCount(1, $byMuniDistJson['agencias'] ?? []);
        $this->assertSame(
            (int) $agTarget['idagencia'],
            (int) ($byMuniDistJson['agencias'][0]['id'] ?? $byMuniDistJson['agencias'][0]['idagencia'] ?? 0)
        );
        $this->assertSame('San Salvador', $byMuniDistJson['agencias'][0]['distrito'] ?? null);
    }

    public function testT3Pair10_SupervisorGtEditAddingCoordinatesPromotesCoordlessAgencyToMapAndApi(): void
    {
        $this->requireHU002Schema();

        $gtChain = $this->getGeoChainForPais('Guatemala');
        $hondaId = (int) $this->findMarcaByName('Honda')['id_marca'];
        $nombre  = 'Agencia Pair10 Promocion ' . str_replace('.', '', uniqid('', true));

        $agency = $this->insertAgencyFixture($gtChain, [$hondaId], [
            'nombre_agencia' => $nombre,
            'Id_distrito'    => null,
            'latitud'        => null,
            'longitud'       => null,
            'activo'         => 1,
        ]);
        $agencyId = (int) $agency['idagencia'];

        $this->actingAsUser('supervisor_gt');

        $preGeo = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $this->assertSame(0, (int) ($preGeo['total'] ?? -1));
        $this->assertNull($this->findGeoItemById($preGeo, $agencyId));

        $preMap = $this->getPage('agencias/mapa');
        $preMap->assertStatus(200);
        $this->assertStringNotContainsString($nombre, (string) $preMap->getBody());

        $editForm = $this->getPage('agencias/editar/' . $agencyId);
        $editForm->assertStatus(200);
        $editBody = (string) $editForm->getBody();
        $this->assertStringContainsStringIgnoringCase('leaflet', $editBody);
        $this->assertMatchesRegularExpression('/name=["\']latitud["\']/i', $editBody);
        $this->assertMatchesRegularExpression('/name=["\']longitud["\']/i', $editBody);

        $updateRes = $this->postForm('agencias/actualizar/' . $agencyId, [
            'nombre_agencia'  => $nombre,
            'direccion'       => (string) $agency['direccion'],
            'id_pais'         => $gtChain['id_pais'],
            'id_departamento' => $gtChain['id_departamento'],
            'id_municipio'    => $gtChain['id_municipio'],
            'id_distrito'     => '',
            'latitud'         => '14.64072000',
            'longitud'        => '-90.51327000',
            'marcas'          => [$hondaId],
        ]);
        $status = $updateRes->response()->getStatusCode();
        $this->assertTrue(
            in_array($status, [200, 302, 303], true),
            "Expected update success status for T3-PAIR-10, got {$status}."
        );

        $reloaded = $this->findAgencyById($agencyId);
        $this->assertNotNull($reloaded);
        $this->assertEqualsWithDelta(14.64072, (float) $reloaded['latitud'], 0.0001);
        $this->assertEqualsWithDelta(-90.51327, (float) $reloaded['longitud'], 0.0001);
        $this->assertNull($reloaded['id_distrito']);

        $audit  = $this->assertAuditRecorded($agencyId, 'ACTUALIZAR', $this->activeEmployeeCode());
        $before = $this->decodeAuditSnapshot((string) $audit['datos_anteriores']);
        $after  = $this->decodeAuditSnapshot((string) $audit['datos_nuevos']);
        $this->assertNull($before['latitud'] ?? null);
        $this->assertNull($before['longitud'] ?? null);
        $this->assertEqualsWithDelta(14.64072, (float) ($after['latitud'] ?? 0), 0.0001);
        $this->assertEqualsWithDelta(-90.51327, (float) ($after['longitud'] ?? 0), 0.0001);

        $postGeo = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $this->assertSame(1, (int) ($postGeo['total'] ?? -1));
        $item = $this->findGeoItemById($postGeo, $agencyId);
        $this->assertNotNull($item);
        $this->assertArrayHasKey('distrito', $item);
        $this->assertNull($item['distrito']);
        $this->assertEqualsWithDelta(14.64072, (float) ($item['latitud'] ?? 0), 0.0001);
        $this->assertEqualsWithDelta(-90.51327, (float) ($item['longitud'] ?? 0), 0.0001);

        $postMap = $this->getPage('agencias/mapa');
        $postMap->assertStatus(200);
        $this->assertStringContainsString($nombre, (string) $postMap->getBody());
    }

    public function testT3Pair11_SupervisorSvEditReplacingBrandsAndDistrictUpdatesPivotAndAudit(): void
    {
        $this->requireHU002Schema();

        $svPaisId   = (int) $this->findPaisByName('El Salvador')['id_pais'];
        $deptoSS    = $this->findDepartamentoByName('San Salvador', $svPaisId);
        $muniCentro = $this->findMunicipioByName('San Salvador Centro', (int) $deptoSS['id_departamento']);
        $distOld    = $this->findDistritoByName('San Salvador', (int) $muniCentro['id_municipio']);
        $muniEste   = $this->findMunicipioByName('San Salvador Este', (int) $deptoSS['id_departamento']);
        $distNew    = $this->findDistritoByName('Soyapango', (int) $muniEste['id_municipio']);

        $hondaId  = (int) $this->findMarcaByName('Honda')['id_marca'];
        $yamahaId = (int) $this->findMarcaByName('Yamaha')['id_marca'];
        $suzukiId = (int) $this->findMarcaByName('Suzuki')['id_marca'];

        $initialName = 'Agencia Pair11 Inicial ' . str_replace('.', '', uniqid('', true));
        $agency      = $this->insertAgencyFixture([
            'id_pais'         => $svPaisId,
            'id_departamento' => (int) $deptoSS['id_departamento'],
            'id_municipio'    => (int) $muniCentro['id_municipio'],
            'id_distrito'     => (int) $distOld['id_distrito'],
        ], [$hondaId], ['nombre_agencia' => $initialName]);
        $agencyId = (int) $agency['idagencia'];

        $this->actingAsUser('supervisor_sv');

        $updatedName = 'Agencia Pair11 Actualizada ' . str_replace('.', '', uniqid('', true));
        $updatedAddr = 'Blvd. del Ejército Km 5, Soyapango';

        $updateRes = $this->postForm('agencias/actualizar/' . $agencyId, [
            'nombre_agencia'  => $updatedName,
            'direccion'       => $updatedAddr,
            'id_pais'         => $svPaisId,
            'id_departamento' => (int) $deptoSS['id_departamento'],
            'id_municipio'    => (int) $muniEste['id_municipio'],
            'id_distrito'     => (int) $distNew['id_distrito'],
            'latitud'         => '13.71024000',
            'longitud'        => '-89.13991000',
            'marcas'          => [$yamahaId, $suzukiId],
        ]);
        $status = $updateRes->response()->getStatusCode();
        $this->assertTrue(
            in_array($status, [200, 302, 303], true),
            "Expected update success status for T3-PAIR-11, got {$status}."
        );

        $reloaded = $this->findAgencyById($agencyId);
        $this->assertNotNull($reloaded);
        $this->assertSame($updatedName, (string) $reloaded['nombre_agencia']);
        $this->assertSame($updatedAddr, (string) $reloaded['direccion']);
        $this->assertSame((int) $muniEste['id_municipio'], (int) $reloaded['id_municipio']);
        $this->assertSame((int) $distNew['id_distrito'], (int) $reloaded['id_distrito']);

        $expectedBrandIds = [$yamahaId, $suzukiId];
        sort($expectedBrandIds);
        $this->assertSame($expectedBrandIds, $this->getAgencyBrandIds($agencyId));

        $audit      = $this->assertAuditRecorded($agencyId, 'ACTUALIZAR', $this->activeEmployeeCode());
        $beforeJson = (string) $audit['datos_anteriores'];
        $afterJson  = (string) $audit['datos_nuevos'];
        $beforeArr  = $this->decodeAuditSnapshot($beforeJson);
        $afterArr   = $this->decodeAuditSnapshot($afterJson);
        $this->assertNotSame($beforeArr, $afterArr);
        $this->assertStringContainsString($initialName, $beforeJson);
        $this->assertStringContainsString($updatedName, $afterJson);

        $geoJson = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $item    = $this->findGeoItemById($geoJson, $agencyId);
        $this->assertNotNull($item);
        $this->assertSame('Soyapango', $item['distrito'] ?? null);
        $this->assertEqualsCanonicalizing(['Suzuki', 'Yamaha'], $item['marcas'] ?? []);

        $showRes = $this->getPage('agencias/ver/' . $agencyId);
        $showRes->assertStatus(200);
        $showBody = (string) $showRes->getBody();
        $this->assertStringContainsString('Soyapango', $showBody);
        $this->assertStringContainsString('Yamaha', $showBody);
        $this->assertStringContainsString('Suzuki', $showBody);
        $this->assertStringContainsString('ACTUALIZAR', $showBody);
    }

    public function testT3Pair12_AdministradorSvDenied403AcrossAllAgencyCrudMapAndGeoApis(): void
    {
        $this->requireHU002Schema();

        $svChain = $this->getGeoChainForPais('El Salvador');
        $hondaId = (int) $this->findMarcaByName('Honda')['id_marca'];
        $target  = 'Agencia Pair12 AdminTarget ' . uniqid();
        $agency  = $this->insertAgencyFixture($svChain, [$hondaId], ['nombre_agencia' => $target]);
        $id      = (int) $agency['idagencia'];

        $beforeCount = $this->countAgencies();
        $this->actingAsUser('admin_sv');

        foreach ([
            'agencias',
            'agencias/crear',
            'agencias/ver/' . $id,
            'agencias/editar/' . $id,
            'agencias/mapa',
        ] as $uri) {
            $this->assertForbiddenHtmlOrRedirectBlocked($this->getPage($uri));
        }

        $this->assertForbiddenHtmlOrRedirectBlocked($this->postForm('agencias/guardar', [
            'nombre_agencia'  => 'Intento Admin Create',
            'direccion'       => 'Dir Admin',
            'id_pais'         => $svChain['id_pais'],
            'id_departamento' => $svChain['id_departamento'],
            'id_municipio'    => $svChain['id_municipio'],
            'id_distrito'     => $svChain['id_distrito'],
            'latitud'         => '13.69294000',
            'longitud'        => '-89.21819000',
            'marcas'          => [$hondaId],
        ]));
        $this->assertForbiddenHtmlOrRedirectBlocked($this->postForm('agencias/actualizar/' . $id, [
            'nombre_agencia'  => 'Intento Admin Update',
            'direccion'       => 'Dir Admin Update',
            'id_pais'         => $svChain['id_pais'],
            'id_departamento' => $svChain['id_departamento'],
            'id_municipio'    => $svChain['id_municipio'],
            'id_distrito'     => $svChain['id_distrito'],
            'latitud'         => '13.69294000',
            'longitud'        => '-89.21819000',
            'marcas'          => [$hondaId],
        ]));
        $this->assertForbiddenHtmlOrRedirectBlocked($this->postForm('agencias/deshabilitar/' . $id, [
            'motivo_deshabilitacion' => 'Intento admin',
        ]));

        $this->assertForbiddenApiJson($this->getJson('api/geo/paises'));
        $this->assertForbiddenApiJson($this->getJson('api/geo/departamentos'));
        $this->assertForbiddenApiJson($this->getJson('api/geo/municipios', [
            'id_departamento' => $svChain['id_departamento'],
        ]));
        $this->assertForbiddenApiJson($this->getJson('api/geo/distritos', [
            'id_municipio' => $svChain['id_municipio'],
        ]));
        $this->assertForbiddenApiJson($this->getJson('api/agencias/geolocalizacion'));

        $this->assertSame($beforeCount, $this->countAgencies());
        $reloaded = $this->findAgencyById($id);
        $this->assertNotNull($reloaded);
        $this->assertSame(1, (int) $reloaded['activo']);
        $this->assertSame($target, (string) $reloaded['nombre_agencia']);
        $this->assertSame([], $this->getAuditLogsForAgency($id));
    }

    public function testT3Pair13_AsesorSvDenied403AcrossAllAgencyCrudMapAndGeoApis(): void
    {
        $this->requireHU002Schema();

        $svChain = $this->getGeoChainForPais('El Salvador');
        $hondaId = (int) $this->findMarcaByName('Honda')['id_marca'];
        $target  = 'Agencia Pair13 AsesorTarget ' . uniqid();
        $agency  = $this->insertAgencyFixture($svChain, [$hondaId], ['nombre_agencia' => $target]);
        $id      = (int) $agency['idagencia'];

        $beforeCount = $this->countAgencies();
        $this->actingAsUser('asesor_sv');

        foreach ([
            'agencias',
            'agencias/crear',
            'agencias/ver/' . $id,
            'agencias/editar/' . $id,
            'agencias/mapa',
        ] as $uri) {
            $this->assertForbiddenHtmlOrRedirectBlocked($this->getPage($uri));
        }

        $this->assertForbiddenHtmlOrRedirectBlocked($this->postForm('agencias/guardar', [
            'nombre_agencia'  => 'Intento Asesor Create',
            'direccion'       => 'Dir Asesor',
            'id_pais'         => $svChain['id_pais'],
            'id_departamento' => $svChain['id_departamento'],
            'id_municipio'    => $svChain['id_municipio'],
            'id_distrito'     => $svChain['id_distrito'],
            'latitud'         => '13.69294000',
            'longitud'        => '-89.21819000',
            'marcas'          => [$hondaId],
        ]));
        $this->assertForbiddenHtmlOrRedirectBlocked($this->postForm('agencias/actualizar/' . $id, [
            'nombre_agencia'  => 'Intento Asesor Update',
            'direccion'       => 'Dir Asesor Update',
            'id_pais'         => $svChain['id_pais'],
            'id_departamento' => $svChain['id_departamento'],
            'id_municipio'    => $svChain['id_municipio'],
            'id_distrito'     => $svChain['id_distrito'],
            'latitud'         => '13.69294000',
            'longitud'        => '-89.21819000',
            'marcas'          => [$hondaId],
        ]));
        $this->assertForbiddenHtmlOrRedirectBlocked($this->postForm('agencias/deshabilitar/' . $id, [
            'motivo_deshabilitacion' => 'Intento asesor',
        ]));

        $this->assertForbiddenApiJson($this->getJson('api/geo/paises'));
        $this->assertForbiddenApiJson($this->getJson('api/geo/departamentos'));
        $this->assertForbiddenApiJson($this->getJson('api/geo/municipios', [
            'id_departamento' => $svChain['id_departamento'],
        ]));
        $this->assertForbiddenApiJson($this->getJson('api/geo/distritos', [
            'id_municipio' => $svChain['id_municipio'],
        ]));
        $this->assertForbiddenApiJson($this->getJson('api/agencias/geolocalizacion'));

        $this->assertSame($beforeCount, $this->countAgencies());
        $reloaded = $this->findAgencyById($id);
        $this->assertNotNull($reloaded);
        $this->assertSame(1, (int) $reloaded['activo']);
        $this->assertSame($target, (string) $reloaded['nombre_agencia']);
        $this->assertSame([], $this->getAuditLogsForAgency($id));
    }

    public function testT3Pair14_SupervisorSvCrossCountryGtAgencyAndUserRoleAdminAllDenied403WithZeroAudits(): void
    {
        $this->requireHU002Schema();

        $gtChain  = $this->getGeoChainForPais('Guatemala');
        $hondaId  = (int) $this->findMarcaByName('Honda')['id_marca'];
        $gtNombre = 'Agencia Pair14 GT Target ' . uniqid();
        $gtAgency = $this->insertAgencyFixture($gtChain, [$hondaId], [
            'nombre_agencia' => $gtNombre,
            'Id_distrito'    => null,
            'latitud'        => 14.63491000,
            'longitud'       => -90.50688000,
        ]);
        $gtId = (int) $gtAgency['idagencia'];

        $asesor       = $this->findUserByUsername('asesor_sv');
        $asesorCodigo = (string) $asesor['codigo_empleado'];
        $beforeCount  = $this->countAgencies();

        $this->actingAsUser('supervisor_sv');

        // Cross-country GT agency operations (8 requests)
        $this->assertForbiddenHtmlOrRedirectBlocked($this->getPage('agencias/ver/' . $gtId));
        $this->assertForbiddenHtmlOrRedirectBlocked($this->getPage('agencias/editar/' . $gtId));
        $this->assertForbiddenHtmlOrRedirectBlocked($this->postForm('agencias/actualizar/' . $gtId, [
            'nombre_agencia'  => 'Intento Cross Update',
            'direccion'       => 'Calle GT',
            'id_pais'         => $gtChain['id_pais'],
            'id_departamento' => $gtChain['id_departamento'],
            'id_municipio'    => $gtChain['id_municipio'],
            'latitud'         => '14.63491000',
            'longitud'        => '-90.50688000',
            'marcas'          => [$hondaId],
        ]));
        $this->assertForbiddenHtmlOrRedirectBlocked($this->postForm('agencias/deshabilitar/' . $gtId, [
            'motivo_deshabilitacion' => 'Cross country',
        ]));
        $this->assertForbiddenHtmlOrRedirectBlocked($this->postForm('agencias/guardar', [
            'nombre_agencia'  => 'Intento Cross Create GT',
            'direccion'       => 'Calle GT',
            'id_pais'         => $gtChain['id_pais'],
            'id_departamento' => $gtChain['id_departamento'],
            'id_municipio'    => $gtChain['id_municipio'],
            'latitud'         => '14.63491000',
            'longitud'        => '-90.50688000',
            'marcas'          => [$hondaId],
        ]));

        $this->assertForbiddenApiJson($this->getJson('api/agencias/geolocalizacion', [
            'id_pais' => $gtChain['id_pais'],
        ]));
        $this->assertForbiddenApiJson($this->getJson('api/agencias/geolocalizacion', [
            'pais' => 'Guatemala',
        ]));
        $this->assertForbiddenApiJson($this->getJson('api/geo/departamentos', [
            'id_pais' => $gtChain['id_pais'],
        ]));

        // User, role, and permission administration attempts (8 requests)
        $this->assertForbiddenHtmlOrRedirectBlocked($this->getPage('usuarios'));
        $this->assertForbiddenHtmlOrRedirectBlocked($this->postForm('usuarios/crear', [
            'usuario' => 'nuevo_usr',
        ]));
        $this->assertForbiddenHtmlOrRedirectBlocked($this->getPage('usuarios/editar/' . $asesorCodigo));
        $this->assertForbiddenHtmlOrRedirectBlocked($this->postForm('usuarios/deshabilitar/' . $asesorCodigo, [
            'motivo' => 'Intento supervisor',
        ]));
        $this->assertForbiddenHtmlOrRedirectBlocked($this->getPage('roles'));
        $this->assertForbiddenHtmlOrRedirectBlocked($this->postForm('roles/asignar', [
            'rol' => 'Administrador',
        ]));
        $this->assertForbiddenHtmlOrRedirectBlocked($this->getPage('permisos'));
        $this->assertForbiddenHtmlOrRedirectBlocked($this->postForm('permisos/actualizar', [
            'permiso' => 'all',
        ]));

        $this->assertSame($beforeCount, $this->countAgencies());
        $reloadedGt = $this->findAgencyById($gtId);
        $this->assertNotNull($reloadedGt);
        $this->assertSame(1, (int) $reloadedGt['activo']);
        $this->assertSame($gtNombre, (string) $reloadedGt['nombre_agencia']);
        $this->assertSame([], $this->getAuditLogsForAgency($gtId));

        $reloadedAsesor = $this->findUserByUsername('asesor_sv');
        $this->assertSame('Asesor', (string) $reloadedAsesor['rol']);
        $this->assertSame(0, (int) $reloadedAsesor['bloqueado']);
    }

    public function testT3Pair15_SupervisorSvInvalidGeoCascadeWithValidBrandsAndCoordsIsAtomicallyRejected(): void
    {
        $this->requireHU002Schema();
        $this->actingAsUser('supervisor_sv');

        $svPaisId = (int) $this->findPaisByName('El Salvador')['id_pais'];
        $deptoSS  = $this->findDepartamentoByName('San Salvador', $svPaisId);
        $deptoLL  = $this->findDepartamentoByName('La Libertad', $svPaisId);
        $muniLL   = $this->findMunicipioByName('La Libertad Sur', (int) $deptoLL['id_departamento']);
        $distLL   = $this->findDistritoByName('Santa Tecla', (int) $muniLL['id_municipio']);
        $gtChain  = $this->getGeoChainForPais('Guatemala');

        $hondaId  = (int) $this->findMarcaByName('Honda')['id_marca'];
        $yamahaId = (int) $this->findMarcaByName('Yamaha')['id_marca'];

        $beforeAgencies = $this->countAgencies();
        $beforePivot    = $this->countTableRows('Agencia_Marca');
        $beforeAudits   = $this->countTableRows('Auditoria_Agencias');

        $badName1 = 'Agencia Pair15 CascadeMismatch ' . uniqid();
        $res1     = $this->postForm('agencias/guardar', [
            'nombre_agencia'  => $badName1,
            'direccion'       => 'Av. Invalida #1',
            'id_pais'         => $svPaisId,
            'id_departamento' => (int) $deptoSS['id_departamento'],
            'id_municipio'    => (int) $muniLL['id_municipio'],
            'id_distrito'     => (int) $distLL['id_distrito'],
            'latitud'         => '13.69294000',
            'longitud'        => '-89.21819000',
            'marcas'          => [$hondaId, $yamahaId],
        ]);
        $status1 = $res1->response()->getStatusCode();
        $this->assertTrue(
            in_array($status1, [200, 302, 303, 400, 422], true),
            "Expected validation rejection status for cascade mismatch, got {$status1}."
        );
        if (in_array($status1, [302, 303], true)) {
            $this->assertDoesNotMatchRegularExpression('#/agencias/ver/\d+#i', (string) $res1->getRedirectUrl());
        }

        $badName2 = 'Agencia Pair15 CrossCountryMuni ' . uniqid();
        $res2     = $this->postForm('agencias/guardar', [
            'nombre_agencia'  => $badName2,
            'direccion'       => 'Av. Invalida #2',
            'id_pais'         => $svPaisId,
            'id_departamento' => (int) $deptoSS['id_departamento'],
            'id_municipio'    => $gtChain['id_municipio'],
            'id_distrito'     => (int) $distLL['id_distrito'],
            'latitud'         => '13.69294000',
            'longitud'        => '-89.21819000',
            'marcas'          => [$hondaId],
        ]);
        $status2 = $res2->response()->getStatusCode();
        $this->assertTrue(
            in_array($status2, [200, 302, 303, 400, 422], true),
            "Expected validation rejection status for foreign municipality, got {$status2}."
        );
        if (in_array($status2, [302, 303], true)) {
            $this->assertDoesNotMatchRegularExpression('#/agencias/ver/\d+#i', (string) $res2->getRedirectUrl());
        }

        $this->assertNull($this->findAgencyByName($badName1));
        $this->assertNull($this->findAgencyByName($badName2));
        $this->assertSame($beforeAgencies, $this->countAgencies());
        $this->assertSame($beforePivot, $this->countTableRows('Agencia_Marca'));
        $this->assertSame($beforeAudits, $this->countTableRows('Auditoria_Agencias'));
    }

    public function testT3Pair16_SupervisorGtEmptyStringDistrictAndMultipleBrandsPersistsNullDistrictAuditAndApi(): void
    {
        $this->requireHU002Schema();
        $this->actingAsUser('supervisor_gt');

        $gtChain  = $this->getGeoChainForPais('Guatemala');
        $hondaId  = (int) $this->findMarcaByName('Honda')['id_marca'];
        $suzukiId = (int) $this->findMarcaByName('Suzuki')['id_marca'];
        $heroId   = (int) $this->findMarcaByName('Hero')['id_marca'];
        $nombre   = 'Agencia Pair16 GT EmptyDist ' . str_replace('.', '', uniqid('', true));

        $createRes = $this->postForm('agencias/guardar', [
            'nombre_agencia'  => $nombre,
            'direccion'       => 'Calzada Roosevelt 15-40, Guatemala',
            'id_pais'         => $gtChain['id_pais'],
            'id_departamento' => $gtChain['id_departamento'],
            'id_municipio'    => $gtChain['id_municipio'],
            'id_distrito'     => '',
            'latitud'         => '14.61250000',
            'longitud'        => '-90.53510000',
            'marcas'          => [$hondaId, $suzukiId, $heroId],
        ]);
        $status = $createRes->response()->getStatusCode();
        $this->assertTrue(
            in_array($status, [200, 201, 302, 303], true),
            "Expected create success status for T3-PAIR-16, got {$status}."
        );

        $created = $this->findAgencyByName($nombre);
        $this->assertNotNull($created);
        $idAgencia = (int) $created['idagencia'];

        $this->assertNull($created['id_distrito']);
        $this->assertSame(1, (int) $created['activo']);

        $expectedIds = [$hondaId, $suzukiId, $heroId];
        sort($expectedIds);
        $this->assertSame($expectedIds, $this->getAgencyBrandIds($idAgencia));

        $audit      = $this->assertAuditRecorded($idAgencia, 'CREAR', $this->activeEmployeeCode());
        $afterState = $this->decodeAuditSnapshot((string) $audit['datos_nuevos']);
        if (array_key_exists('id_distrito', $afterState)) {
            $this->assertNull($afterState['id_distrito']);
        }

        $geoJson = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $this->assertSame('Guatemala', $geoJson['pais'] ?? null);
        $item = $this->findGeoItemById($geoJson, $idAgencia);
        $this->assertNotNull($item);
        $this->assertArrayHasKey('distrito', $item);
        $this->assertNull($item['distrito']);
        $this->assertEqualsCanonicalizing(['Hero', 'Honda', 'Suzuki'], $item['marcas'] ?? []);
        $this->assertEqualsWithDelta(14.6125, (float) ($item['latitud'] ?? 0), 0.0001);
        $this->assertEqualsWithDelta(-90.5351, (float) ($item['longitud'] ?? 0), 0.0001);
    }

    // =========================================================================
    // Test-Local Opaque-Box Fixtures & Helpers
    // =========================================================================

    /**
     * @param array<string, mixed> $geoChain
     * @param list<int>            $brandIds
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function insertAgencyFixture(array $geoChain, array $brandIds, array $overrides = []): array
    {
        $this->requireHU002Schema();

        $agenciasTable = $this->resolveTableName('Agencias');
        $pivotTable    = $this->resolveTableName('Agencia_Marca');
        $this->assertNotNull($agenciasTable, 'Agencias table must exist.');
        $this->assertNotNull($pivotTable, 'Agencia_Marca table must exist.');

        $data = array_merge([
            'nombre_agencia'         => 'Agencia Fixture ' . str_replace('.', '', uniqid('', true)),
            'direccion'              => 'Calle Principal #' . random_int(10, 999),
            'Id_pais'                => (int) $geoChain['id_pais'],
            'Id_departamento'        => (int) $geoChain['id_departamento'],
            'Id_municipio'           => (int) $geoChain['id_municipio'],
            'Id_distrito'            => $geoChain['id_distrito'] !== null ? (int) $geoChain['id_distrito'] : null,
            'latitud'                => 13.69294000,
            'longitud'               => -89.21819000,
            'Id_marca'               => $brandIds !== [] ? (int) $brandIds[0] : null,
            'activo'                 => 1,
            'motivo_deshabilitacion' => null,
            'deshabilitado_en'       => null,
            'deshabilitado_por'      => null,
            'creado_en'              => '2026-11-18 12:00:00',
            'actualizado_en'         => '2026-11-18 12:00:00',
        ], $overrides);

        $this->db->table($agenciasTable)->insert($data);
        $idAgencia = (int) $this->db->insertID();
        $this->assertGreaterThan(0, $idAgencia, 'Failed to insert agency fixture.');

        foreach ($brandIds as $idMarca) {
            $this->db->table($pivotTable)->insert([
                'Idagencia' => $idAgencia,
                'Id_marca'  => (int) $idMarca,
            ]);
        }

        $created = $this->findAgencyById($idAgencia);
        $this->assertNotNull($created);

        return $created;
    }

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

    private function countTableRows(string $preferredTable): int
    {
        $resolved = $this->resolveTableName($preferredTable);
        $this->assertNotNull($resolved, "Table '{$preferredTable}' does not exist.");

        return (int) $this->db->table($resolved)->countAllResults();
    }

    private function activeEmployeeCode(): string
    {
        $code = (string) ($this->activeSession['codigo_empleado'] ?? '');
        $this->assertNotSame('', $code, 'Active authenticated session must contain codigo_empleado.');

        return $code;
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

    private function assertForbiddenHtmlOrRedirectBlocked(TestResponse $response): void
    {
        $response->assertStatus(403);
    }

    private function assertForbiddenApiJson(TestResponse $response): void
    {
        $response->assertStatus(403);
        $json = $this->decodeJsonResponse($response);
        $this->assertSame('error', $json['status'] ?? null);
        $this->assertSame('forbidden', $json['error'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeAuditSnapshot(?string $json): array
    {
        $this->assertNotNull($json, 'Audit JSON snapshot must not be null.');
        $trimmed = trim($json);
        $this->assertNotSame('', $trimmed, 'Audit JSON snapshot must not be empty.');

        $decoded = json_decode($trimmed, true);
        $this->assertIsArray($decoded, 'Audit snapshot must decode as JSON array: ' . $trimmed);

        return array_change_key_case($decoded, CASE_LOWER);
    }
}
