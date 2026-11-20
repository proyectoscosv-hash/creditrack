<?php

declare(strict_types=1);

namespace Tests\E2E;

require_once __DIR__ . '/E2ETestCase.php';

/**
 * Tier 1: Category-Partition Happy Paths for Creditrack HU-002.
 *
 * Covers all 16 features (F01..F16) with 5 isolated primary-behavior tests
 * per feature (80 tests total) using strictly opaque-box HTTP/HTML/JSON and
 * observable SQLite :memory: schema/data assertions.
 *
 * @internal
 */
final class Tier1FeatureCoverageTest extends E2ETestCase
{
    // =========================================================================
    // FEATURE F01: Supervisor-Only Agency Access (T1-F01-01..T1-F01-05)
    // =========================================================================

    /**
     * T1-F01-01: Authenticated Supervisor SV accesses GET agencias (200).
     */
    public function testT1F01_01_AuthenticatedSupervisorSvAccessesAgencyList(): void
    {
        $this->loginAsSupervisorSv();

        $res = $this->getPage('agencias');
        $res->assertStatus(200);

        $body = $res->getBody();
        $this->assertStringNotContainsString('Iniciar Sesión', $body);
        $this->assertMatchesRegularExpression('/agencias/i', $body);
    }

    /**
     * T1-F01-02: Authenticated Supervisor accesses GET agencias/crear (200).
     */
    public function testT1F01_02_AuthenticatedSupervisorAccessesCreateForm(): void
    {
        $this->actingAsUser('supervisor_sv');

        $res = $this->getPage('agencias/crear');
        $res->assertStatus(200);

        $body = $res->getBody();
        foreach (['nombre_agencia', 'direccion', 'id_pais', 'id_departamento', 'id_municipio', 'id_distrito', 'latitud', 'longitud', 'marcas'] as $field) {
            $this->assertMatchesRegularExpression(
                '/name=["\']' . preg_quote($field, '/') . '(?:\[\])?["\']/i',
                $body,
                "Expected form input/select for '{$field}' on agencias/crear."
            );
        }
    }

    /**
     * T1-F01-03: Authenticated Supervisor creates agency via POST agencias/guardar.
     */
    public function testT1F01_03_AuthenticatedSupervisorCreatesAgencyViaPostGuardar(): void
    {
        $this->actingAsUser('supervisor_sv');
        $payload = $this->buildValidAgencyPayload('El Salvador');

        $res    = $this->postForm('agencias/guardar', $payload);
        $status = $res->response()->getStatusCode();

        $this->assertTrue(in_array($status, [200, 201, 302, 303], true), "Expected success status, got {$status}.");

        $created = $this->findAgencyByName((string) $payload['nombre_agencia']);
        $this->assertNotNull($created, 'Agency must be persisted in Agencias table.');
        $this->assertSame((int) $payload['id_pais'], (int) $created['id_pais']);
        $this->assertSame($payload['marcas'], $this->getAgencyBrandIds((int) $created['idagencia']));
    }

    /**
     * T1-F01-04: Authenticated Supervisor accesses GET agencias/mapa (200).
     */
    public function testT1F01_04_AuthenticatedSupervisorAccessesOperationalMap(): void
    {
        $this->actingAsUser('supervisor_sv');

        $res = $this->getPage('agencias/mapa');
        $res->assertStatus(200);
        $this->assertMatchesRegularExpression('/(leaflet|mapa)/i', $res->getBody());
    }

    /**
     * T1-F01-05: Authenticated Supervisor accesses GET api/agencias/geolocalizacion (200 JSON).
     */
    public function testT1F01_05_AuthenticatedSupervisorAccessesGeolocationApi(): void
    {
        $this->actingAsUser('supervisor_sv');

        $res  = $this->getJson('api/agencias/geolocalizacion');
        $json = $this->assertJsonOk($res);

        $this->assertSame('El Salvador', $json['pais'] ?? null);
        $this->assertArrayHasKey('total', $json);
        $this->assertIsArray($json['agencias'] ?? null);
    }

    // =========================================================================
    // FEATURE F02: Country-Scoped Agency Isolation (T1-F02-01..T1-F02-05)
    // =========================================================================

    /**
     * T1-F02-01: SV supervisor GET agencias displays only El Salvador agencies.
     */
    public function testT1F02_01_SvSupervisorListDisplaysOnlyElSalvadorAgencies(): void
    {
        $sv = $this->createAgencyViaHttp('supervisor_sv');
        $gt = $this->createAgencyViaHttp('supervisor_gt');

        $this->actingAsUser('supervisor_sv');
        $res = $this->getPage('agencias');
        $res->assertStatus(200);
        $res->assertSee((string) $sv['nombre_agencia']);
        $res->assertDontSee((string) $gt['nombre_agencia']);
    }

    /**
     * T1-F02-02: GT supervisor GET agencias displays only Guatemala agencies.
     */
    public function testT1F02_02_GtSupervisorListDisplaysOnlyGuatemalaAgencies(): void
    {
        $sv = $this->createAgencyViaHttp('supervisor_sv');
        $gt = $this->createAgencyViaHttp('supervisor_gt');

        $this->actingAsUser('supervisor_gt');
        $res = $this->getPage('agencias');
        $res->assertStatus(200);
        $res->assertSee((string) $gt['nombre_agencia']);
        $res->assertDontSee((string) $sv['nombre_agencia']);
    }

    /**
     * T1-F02-03: SV supervisor views own-country agency detail via GET agencias/ver/{id}.
     */
    public function testT1F02_03_SvSupervisorViewsOwnCountryAgencyDetail(): void
    {
        $sv = $this->createAgencyViaHttp('supervisor_sv');

        $this->actingAsUser('supervisor_sv');
        $res = $this->getPage('agencias/ver/' . $sv['idagencia']);
        $res->assertStatus(200);
        $res->assertSee((string) $sv['nombre_agencia']);
        $res->assertSee((string) $sv['direccion']);
        $res->assertSee('El Salvador');
    }

    /**
     * T1-F02-04: SV supervisor edits and updates own-country agency.
     */
    public function testT1F02_04_SvSupervisorEditsAndUpdatesOwnCountryAgency(): void
    {
        $sv    = $this->createAgencyViaHttp('supervisor_sv');
        $chain = $this->getGeoChainForPais('El Salvador');

        $this->actingAsUser('supervisor_sv');
        $editRes = $this->getPage('agencias/editar/' . $sv['idagencia']);
        $editRes->assertStatus(200);
        $editRes->assertSee((string) $sv['nombre_agencia']);

        $newName = 'Agencia SV Actualizada ' . uniqid();
        $newDir  = 'Paseo General Escalon #500';
        $updRes  = $this->postForm('agencias/actualizar/' . $sv['idagencia'], [
            'nombre_agencia'  => $newName,
            'direccion'       => $newDir,
            'id_pais'         => $chain['id_pais'],
            'id_departamento' => $chain['id_departamento'],
            'id_municipio'    => $chain['id_municipio'],
            'id_distrito'     => $chain['id_distrito'],
            'latitud'         => '13.70100000',
            'longitud'        => '-89.21000000',
            'marcas'          => $this->getDefaultBrandIds(1),
        ]);

        $status = $updRes->response()->getStatusCode();
        $this->assertTrue(in_array($status, [200, 302, 303], true), "Expected update success status, got {$status}.");

        $reloaded = $this->findAgencyById((int) $sv['idagencia']);
        $this->assertNotNull($reloaded);
        $this->assertSame($newName, $reloaded['nombre_agencia']);
        $this->assertSame($newDir, $reloaded['direccion']);
    }

    /**
     * T1-F02-05: GET api/agencias/geolocalizacion returns only agencies in supervisor's country.
     */
    public function testT1F02_05_GeolocationApiReturnsOnlySupervisorCountryAgencies(): void
    {
        $sv = $this->createAgencyViaHttp('supervisor_sv');
        $gt = $this->createAgencyViaHttp('supervisor_gt');

        $this->actingAsUser('supervisor_sv');
        $res  = $this->getJson('api/agencias/geolocalizacion');
        $json = $this->assertJsonOk($res);

        $this->assertSame('El Salvador', $json['pais'] ?? null);
        $returnedIds = array_map(static fn (array $item): int => (int) ($item['id'] ?? 0), $json['agencias'] ?? []);

        $this->assertContains((int) $sv['idagencia'], $returnedIds);
        $this->assertNotContains((int) $gt['idagencia'], $returnedIds);

        foreach ($json['agencias'] ?? [] as $item) {
            $this->assertSame('El Salvador', $item['pais'] ?? null);
        }
    }

    // =========================================================================
    // FEATURE F03: Supervisor Prohibition on User/Role Admin (T1-F03-01..T1-F03-05)
    // =========================================================================

    /**
     * T1-F03-01: Supervisor GET usuarios returns 403.
     */
    public function testT1F03_01_SupervisorGetUsuariosReturns403(): void
    {
        $this->actingAsUser('supervisor_sv');

        $res = $this->getPage('usuarios');
        $res->assertStatus(403);
    }

    /**
     * T1-F03-02: Supervisor POST usuarios/crear returns 403.
     */
    public function testT1F03_02_SupervisorPostUsuariosCrearReturns403(): void
    {
        $this->actingAsUser('supervisor_sv');

        $res = $this->postForm('usuarios/crear', [
            'usuario' => 'rogue_supervisor_user',
            'nombre'  => 'Rogue User',
            'rol'     => 'Administrador',
        ]);
        $res->assertStatus(403);

        $usuarioTable = $this->resolveTableName('Usuario');
        $this->assertNotNull($usuarioTable);
        $rogue = $this->db->table($usuarioTable)->where('Usuario', 'rogue_supervisor_user')->get()->getRowArray();
        $this->assertNull($rogue, 'Blocked request must not create any user record.');
    }

    /**
     * T1-F03-03: Supervisor GET roles returns 403.
     */
    public function testT1F03_03_SupervisorGetRolesReturns403(): void
    {
        $this->actingAsUser('supervisor_sv');

        $res = $this->getPage('roles');
        $res->assertStatus(403);
    }

    /**
     * T1-F03-04: Supervisor GET permisos returns 403.
     */
    public function testT1F03_04_SupervisorGetPermisosReturns403(): void
    {
        $this->actingAsUser('supervisor_sv');

        $res = $this->getPage('permisos');
        $res->assertStatus(403);
    }

    /**
     * T1-F03-05: Supervisor home.php and agency views do not expose user/role/permission admin links.
     */
    public function testT1F03_05_SupervisorViewsDoNotExposeUserRolePermissionAdminLinks(): void
    {
        $this->actingAsUser('supervisor_sv');

        foreach (['home.php', 'agencias', 'agencias/crear', 'agencias/mapa'] as $route) {
            $res = $this->getPage($route);
            $res->assertStatus(200);

            $this->assertDoesNotMatchRegularExpression(
                '/(?:href|action)\s*=\s*["\'](?:[^"\']*\/)?(?:usuarios|roles|permisos)(?:[\/?#"\'])/i',
                $res->getBody(),
                "Route '{$route}' must not expose href/action links to user, role, or permission administration."
            );
        }
    }

    // =========================================================================
    // FEATURE F04: Agency Registration (Create) (T1-F04-01..T1-F04-05)
    // =========================================================================

    /**
     * T1-F04-01: Create SV agency with single brand and valid coordinates.
     */
    public function testT1F04_01_CreateSvAgencyWithSingleBrandAndValidCoordinates(): void
    {
        $this->actingAsUser('supervisor_sv');
        $brandIds = $this->getDefaultBrandIds(1);
        $payload  = $this->buildValidAgencyPayload('El Salvador', [
            'marcas'   => $brandIds,
            'latitud'  => '13.69876500',
            'longitud' => '-89.19123400',
        ]);

        $res    = $this->postForm('agencias/guardar', $payload);
        $status = $res->response()->getStatusCode();
        $this->assertTrue(in_array($status, [200, 201, 302, 303], true), "Expected success status, got {$status}.");

        $row = $this->findAgencyByName((string) $payload['nombre_agencia']);
        $this->assertNotNull($row);
        $this->assertSame((int) $payload['id_pais'], (int) $row['id_pais']);
        $this->assertSame((int) $payload['id_departamento'], (int) $row['id_departamento']);
        $this->assertSame((int) $payload['id_municipio'], (int) $row['id_municipio']);
        $this->assertSame((int) $payload['id_distrito'], (int) $row['id_distrito']);
        $this->assertEqualsWithDelta(13.698765, (float) $row['latitud'], 0.00001);
        $this->assertEqualsWithDelta(-89.191234, (float) $row['longitud'], 0.00001);
        $this->assertSame($brandIds, $this->getAgencyBrandIds((int) $row['idagencia']));
    }

    /**
     * T1-F04-02: Create SV agency with multiple brands (populates Agencia_Marca pivot).
     */
    public function testT1F04_02_CreateSvAgencyWithMultipleBrandsPopulatesPivot(): void
    {
        $this->actingAsUser('supervisor_sv');
        $brandIds = $this->getDefaultBrandIds(3);
        $payload  = $this->buildValidAgencyPayload('El Salvador', ['marcas' => $brandIds]);

        $res    = $this->postForm('agencias/guardar', $payload);
        $status = $res->response()->getStatusCode();
        $this->assertTrue(in_array($status, [200, 201, 302, 303], true), "Expected success status, got {$status}.");

        $row = $this->findAgencyByName((string) $payload['nombre_agencia']);
        $this->assertNotNull($row);

        $actual = $this->getAgencyBrandIds((int) $row['idagencia']);
        sort($brandIds);
        sort($actual);
        $this->assertSame($brandIds, $actual);
    }

    /**
     * T1-F04-03: Create GT agency without id_distrito.
     */
    public function testT1F04_03_CreateGtAgencyWithoutIdDistrito(): void
    {
        $this->actingAsUser('supervisor_gt');
        $payload = $this->buildValidAgencyPayload('Guatemala');
        unset($payload['id_distrito']);

        $res    = $this->postForm('agencias/guardar', $payload);
        $status = $res->response()->getStatusCode();
        $this->assertTrue(in_array($status, [200, 201, 302, 303], true), "Expected success status, got {$status}.");

        $row = $this->findAgencyByName((string) $payload['nombre_agencia']);
        $this->assertNotNull($row);
        $this->assertNull($row['id_distrito']);
        $this->assertSame((int) $payload['id_pais'], (int) $row['id_pais']);
    }

    /**
     * T1-F04-04: POST agencias route alias creates agency identically to POST agencias/guardar.
     */
    public function testT1F04_04_PostAgenciasAliasCreatesAgencyIdentically(): void
    {
        $this->actingAsUser('supervisor_sv');
        $brandIds = $this->getDefaultBrandIds(2);
        $payload  = $this->buildValidAgencyPayload('El Salvador', ['marcas' => $brandIds]);

        $res    = $this->postForm('agencias', $payload);
        $status = $res->response()->getStatusCode();
        $this->assertTrue(in_array($status, [200, 201, 302, 303], true), "Expected success status on POST agencias, got {$status}.");

        $row = $this->findAgencyByName((string) $payload['nombre_agencia']);
        $this->assertNotNull($row);
        $this->assertCount(2, $this->getAgencyBrandIds((int) $row['idagencia']));
    }

    /**
     * T1-F04-05: Created agency defaults to activo = 1 with creado_en and actualizado_en populated.
     */
    public function testT1F04_05_CreatedAgencyDefaultsToActivo1WithTimestamps(): void
    {
        $created = $this->createAgencyViaHttp('supervisor_sv');

        $this->assertSame(1, (int) ($created['activo'] ?? -1));
        $this->assertNotEmpty($created['creado_en'] ?? null, 'creado_en must be populated on creation.');
        $this->assertNotEmpty($created['actualizado_en'] ?? null, 'actualizado_en must be populated on creation.');
        $this->assertNull($created['deshabilitado_en'] ?? null);
        $this->assertNull($created['deshabilitado_por'] ?? null);
    }

    // =========================================================================
    // FEATURE F05: Agency Query / Consultation List & Detail (T1-F05-01..T1-F05-05)
    // =========================================================================

    /**
     * T1-F05-01: GET agencias lists active agencies in supervisor's country.
     */
    public function testT1F05_01_GetAgenciasListsActiveAgenciesInSupervisorCountry(): void
    {
        $a1 = $this->createAgencyViaHttp('supervisor_sv');
        $a2 = $this->createAgencyViaHttp('supervisor_sv');

        $this->actingAsUser('supervisor_sv');
        $res = $this->getPage('agencias');
        $res->assertStatus(200);
        $res->assertSee((string) $a1['nombre_agencia']);
        $res->assertSee((string) $a2['nombre_agencia']);
    }

    /**
     * T1-F05-02: ?estado=inactivas and ?estado=todas filter disabled/all agencies.
     */
    public function testT1F05_02_EstadoQueryFiltersActiveDisabledAndAllAgencies(): void
    {
        $active   = $this->createAgencyViaHttp('supervisor_sv');
        $disabled = $this->createAgencyViaHttp('supervisor_sv');

        $this->actingAsUser('supervisor_sv');
        $disRes = $this->postForm('agencias/deshabilitar/' . $disabled['idagencia'], [
            'motivo_deshabilitacion' => 'Cierre temporal por inventario',
        ]);
        $disStatus = $disRes->response()->getStatusCode();
        $this->assertTrue(in_array($disStatus, [200, 302, 303], true), "Expected disable success status, got {$disStatus}.");

        $rInact = $this->getPage('agencias', ['estado' => 'inactivas']);
        $rInact->assertStatus(200);
        $rInact->assertSee((string) $disabled['nombre_agencia']);
        $rInact->assertDontSee((string) $active['nombre_agencia']);

        $rAct = $this->getPage('agencias', ['estado' => 'activas']);
        $rAct->assertStatus(200);
        $rAct->assertSee((string) $active['nombre_agencia']);
        $rAct->assertDontSee((string) $disabled['nombre_agencia']);

        $rAll = $this->getPage('agencias', ['estado' => 'todas']);
        $rAll->assertStatus(200);
        $rAll->assertSee((string) $active['nombre_agencia']);
        $rAll->assertSee((string) $disabled['nombre_agencia']);
    }

    /**
     * T1-F05-03: ?id_marca= and ?id_departamento= narrow list results accurately.
     */
    public function testT1F05_03_BrandAndDepartmentQueryFiltersNarrowListResults(): void
    {
        $svPais     = $this->findPaisByName('El Salvador');
        $idPaisSv   = (int) $svPais['id_pais'];
        $sanSalv    = $this->findDepartamentoByName('San Salvador', $idPaisSv);
        $laLibertad = $this->findDepartamentoByName('La Libertad', $idPaisSv);

        $ssMuni = $this->findAnyMunicipioForDepartamento((int) $sanSalv['id_departamento']);
        $ssDist = $this->findAnyDistritoForMunicipio((int) $ssMuni['id_municipio']);
        $llMuni = $this->findAnyMunicipioForDepartamento((int) $laLibertad['id_departamento']);
        $llDist = $this->findAnyDistritoForMunicipio((int) $llMuni['id_municipio']);

        $this->assertNotNull($ssDist);
        $this->assertNotNull($llDist);

        $honda  = $this->findMarcaByName('Honda');
        $yamaha = $this->findMarcaByName('Yamaha');

        $a1 = $this->createAgencyViaHttp('supervisor_sv', [
            'id_departamento' => (int) $sanSalv['id_departamento'],
            'id_municipio'    => (int) $ssMuni['id_municipio'],
            'id_distrito'     => (int) $ssDist['id_distrito'],
            'marcas'          => [(int) $honda['id_marca']],
        ]);

        $a2 = $this->createAgencyViaHttp('supervisor_sv', [
            'id_departamento' => (int) $laLibertad['id_departamento'],
            'id_municipio'    => (int) $llMuni['id_municipio'],
            'id_distrito'     => (int) $llDist['id_distrito'],
            'marcas'          => [(int) $yamaha['id_marca']],
        ]);

        $this->actingAsUser('supervisor_sv');

        $byBrand = $this->getPage('agencias', ['id_marca' => (int) $honda['id_marca']]);
        $byBrand->assertStatus(200);
        $byBrand->assertSee((string) $a1['nombre_agencia']);
        $byBrand->assertDontSee((string) $a2['nombre_agencia']);

        $byDepto = $this->getPage('agencias', ['id_departamento' => (int) $laLibertad['id_departamento']]);
        $byDepto->assertStatus(200);
        $byDepto->assertSee((string) $a2['nombre_agencia']);
        $byDepto->assertDontSee((string) $a1['nombre_agencia']);
    }

    /**
     * T1-F05-04: ?q= searches agency name and physical address.
     */
    public function testT1F05_04_SearchQuerySearchesAgencyNameAndAddress(): void
    {
        $uniq = str_replace('.', '', uniqid('', true));

        $a1 = $this->createAgencyViaHttp('supervisor_sv', [
            'nombre_agencia' => 'Sucursal Titan Norte ' . $uniq,
            'direccion'      => 'Bulevar Los Heroes #45',
        ]);
        $a2 = $this->createAgencyViaHttp('supervisor_sv', [
            'nombre_agencia' => 'Sucursal Sur ' . $uniq,
            'direccion'      => 'Calle Cedros #999 ' . $uniq,
        ]);

        $this->actingAsUser('supervisor_sv');

        $byName = $this->getPage('agencias', ['q' => 'Titan Norte ' . $uniq]);
        $byName->assertStatus(200);
        $byName->assertSee((string) $a1['nombre_agencia']);
        $byName->assertDontSee((string) $a2['nombre_agencia']);

        $byAddr = $this->getPage('agencias', ['q' => 'Calle Cedros #999 ' . $uniq]);
        $byAddr->assertStatus(200);
        $byAddr->assertSee((string) $a2['nombre_agencia']);
        $byAddr->assertDontSee((string) $a1['nombre_agencia']);
    }

    /**
     * T1-F05-05: GET agencias/ver/{id} displays full details, brands, coordinates, status, and disable reason.
     */
    public function testT1F05_05_GetAgenciasVerDisplaysFullDetailsBrandsCoordsStatusAndDisableReason(): void
    {
        $honda  = $this->findMarcaByName('Honda');
        $yamaha = $this->findMarcaByName('Yamaha');

        $ag = $this->createAgencyViaHttp('supervisor_sv', [
            'marcas'   => [(int) $honda['id_marca'], (int) $yamaha['id_marca']],
            'latitud'  => '13.70123400',
            'longitud' => '-89.20567800',
        ]);

        $this->actingAsUser('supervisor_sv');
        $motivo = 'Reubicacion estrategica sucursal';
        $disRes = $this->postForm('agencias/deshabilitar/' . $ag['idagencia'], [
            'motivo_deshabilitacion' => $motivo,
        ]);
        $disStatus = $disRes->response()->getStatusCode();
        $this->assertTrue(in_array($disStatus, [200, 302, 303], true), "Expected disable success status, got {$disStatus}.");

        $res = $this->getPage('agencias/ver/' . $ag['idagencia']);
        $res->assertStatus(200);
        $res->assertSee((string) $ag['nombre_agencia']);
        $res->assertSee((string) $ag['direccion']);
        $res->assertSee('El Salvador');
        $res->assertSee('Honda');
        $res->assertSee('Yamaha');
        $res->assertSee('13.7012');
        $res->assertSee('-89.2056');
        $res->assertSee($motivo);
        $this->assertMatchesRegularExpression('/(inactiv[ao]|deshabilitad[ao])/i', $res->getBody());
    }

    // =========================================================================
    // FEATURE F06: Agency Update (Modify) (T1-F06-01..T1-F06-05)
    // =========================================================================

    /**
     * T1-F06-01: GET agencias/editar/{id} pre-populates agency attributes and selected brands.
     */
    public function testT1F06_01_GetAgenciasEditarPrePopulatesAttributesAndSelectedBrands(): void
    {
        $honda  = $this->findMarcaByName('Honda');
        $yamaha = $this->findMarcaByName('Yamaha');

        $ag = $this->createAgencyViaHttp('supervisor_sv', [
            'marcas'   => [(int) $honda['id_marca'], (int) $yamaha['id_marca']],
            'latitud'  => '13.68881200',
            'longitud' => '-89.23334500',
        ]);

        $this->actingAsUser('supervisor_sv');
        $res = $this->getPage('agencias/editar/' . $ag['idagencia']);
        $res->assertStatus(200);

        $body = $res->getBody();
        $res->assertSee((string) $ag['nombre_agencia']);
        $res->assertSee((string) $ag['direccion']);
        $res->assertSee('13.6888');
        $res->assertSee('-89.2333');

        foreach ([(int) $honda['id_marca'], (int) $yamaha['id_marca']] as $brandId) {
            $this->assertMatchesRegularExpression(
                '/(?:value=["\']' . $brandId . '["\'][^>]*(?:checked|selected)|(?:checked|selected)[^>]*value=["\']' . $brandId . '["\'])/i',
                $body,
                "Expected brand ID {$brandId} to be pre-selected in edit form."
            );
        }
    }

    /**
     * T1-F06-02: POST agencias/actualizar/{id} updates nombre_agencia and direccion.
     */
    public function testT1F06_02_PostAgenciasActualizarUpdatesNombreAndDireccion(): void
    {
        $ag    = $this->createAgencyViaHttp('supervisor_sv');
        $chain = $this->getGeoChainForPais('El Salvador');

        $this->actingAsUser('supervisor_sv');
        $newName = 'Agencia Renovada ' . uniqid();
        $newDir  = 'Avenida Independencia #808, San Salvador';

        $res = $this->postForm('agencias/actualizar/' . $ag['idagencia'], [
            'nombre_agencia'  => $newName,
            'direccion'       => $newDir,
            'id_pais'         => $chain['id_pais'],
            'id_departamento' => $chain['id_departamento'],
            'id_municipio'    => $chain['id_municipio'],
            'id_distrito'     => $chain['id_distrito'],
            'latitud'         => '13.69294000',
            'longitud'        => '-89.21819000',
            'marcas'          => $this->getDefaultBrandIds(1),
        ]);

        $status = $res->response()->getStatusCode();
        $this->assertTrue(in_array($status, [200, 302, 303], true), "Expected update success status, got {$status}.");

        $updated = $this->findAgencyById((int) $ag['idagencia']);
        $this->assertNotNull($updated);
        $this->assertSame($newName, $updated['nombre_agencia']);
        $this->assertSame($newDir, $updated['direccion']);
    }

    /**
     * T1-F06-03: Update changes geographic hierarchy (id_departamento, id_municipio, id_distrito).
     */
    public function testT1F06_03_UpdateChangesGeographicHierarchy(): void
    {
        $svPais     = $this->findPaisByName('El Salvador');
        $idPaisSv   = (int) $svPais['id_pais'];
        $sanSalv    = $this->findDepartamentoByName('San Salvador', $idPaisSv);
        $laLibertad = $this->findDepartamentoByName('La Libertad', $idPaisSv);

        $ssMuni = $this->findAnyMunicipioForDepartamento((int) $sanSalv['id_departamento']);
        $ssDist = $this->findAnyDistritoForMunicipio((int) $ssMuni['id_municipio']);
        $llMuni = $this->findAnyMunicipioForDepartamento((int) $laLibertad['id_departamento']);
        $llDist = $this->findAnyDistritoForMunicipio((int) $llMuni['id_municipio']);

        $this->assertNotNull($ssDist);
        $this->assertNotNull($llDist);

        $ag = $this->createAgencyViaHttp('supervisor_sv', [
            'id_departamento' => (int) $sanSalv['id_departamento'],
            'id_municipio'    => (int) $ssMuni['id_municipio'],
            'id_distrito'     => (int) $ssDist['id_distrito'],
        ]);

        $this->actingAsUser('supervisor_sv');
        $res = $this->postForm('agencias/actualizar/' . $ag['idagencia'], [
            'nombre_agencia'  => $ag['nombre_agencia'],
            'direccion'       => $ag['direccion'],
            'id_pais'         => $idPaisSv,
            'id_departamento' => (int) $laLibertad['id_departamento'],
            'id_municipio'    => (int) $llMuni['id_municipio'],
            'id_distrito'     => (int) $llDist['id_distrito'],
            'latitud'         => '13.67694000',
            'longitud'        => '-89.27972000',
            'marcas'          => $this->getDefaultBrandIds(1),
        ]);

        $status = $res->response()->getStatusCode();
        $this->assertTrue(in_array($status, [200, 302, 303], true), "Expected update success status, got {$status}.");

        $updated = $this->findAgencyById((int) $ag['idagencia']);
        $this->assertNotNull($updated);
        $this->assertSame((int) $laLibertad['id_departamento'], (int) $updated['id_departamento']);
        $this->assertSame((int) $llMuni['id_municipio'], (int) $updated['id_municipio']);
        $this->assertSame((int) $llDist['id_distrito'], (int) $updated['id_distrito']);
    }

    /**
     * T1-F06-04: Update changes latitud and longitud.
     */
    public function testT1F06_04_UpdateChangesLatitudAndLongitud(): void
    {
        $ag    = $this->createAgencyViaHttp('supervisor_sv', [
            'latitud'  => '13.69294000',
            'longitud' => '-89.21819000',
        ]);
        $chain = $this->getGeoChainForPais('El Salvador');

        $this->actingAsUser('supervisor_sv');
        $res = $this->postForm('agencias/actualizar/' . $ag['idagencia'], [
            'nombre_agencia'  => $ag['nombre_agencia'],
            'direccion'       => $ag['direccion'],
            'id_pais'         => $chain['id_pais'],
            'id_departamento' => $chain['id_departamento'],
            'id_municipio'    => $chain['id_municipio'],
            'id_distrito'     => $chain['id_distrito'],
            'latitud'         => '13.71555500',
            'longitud'        => '-89.24444400',
            'marcas'          => $this->getDefaultBrandIds(1),
        ]);

        $status = $res->response()->getStatusCode();
        $this->assertTrue(in_array($status, [200, 302, 303], true), "Expected update success status, got {$status}.");

        $updated = $this->findAgencyById((int) $ag['idagencia']);
        $this->assertNotNull($updated);
        $this->assertEqualsWithDelta(13.715555, (float) $updated['latitud'], 0.00001);
        $this->assertEqualsWithDelta(-89.244444, (float) $updated['longitud'], 0.00001);
    }

    /**
     * T1-F06-05: Update replaces associated marcas[] in Agencia_Marca (adds new, removes unselected).
     */
    public function testT1F06_05_UpdateReplacesAssociatedMarcasInAgenciaMarca(): void
    {
        $allMarcas = $this->getAllMarcas();
        $this->assertGreaterThanOrEqual(3, count($allMarcas));

        $initialBrands = [(int) $allMarcas[0]['id_marca'], (int) $allMarcas[1]['id_marca']];
        $newBrands     = [(int) $allMarcas[1]['id_marca'], (int) $allMarcas[2]['id_marca']];

        $ag    = $this->createAgencyViaHttp('supervisor_sv', ['marcas' => $initialBrands]);
        $chain = $this->getGeoChainForPais('El Salvador');

        $this->actingAsUser('supervisor_sv');
        $res = $this->postForm('agencias/actualizar/' . $ag['idagencia'], [
            'nombre_agencia'  => $ag['nombre_agencia'],
            'direccion'       => $ag['direccion'],
            'id_pais'         => $chain['id_pais'],
            'id_departamento' => $chain['id_departamento'],
            'id_municipio'    => $chain['id_municipio'],
            'id_distrito'     => $chain['id_distrito'],
            'latitud'         => '13.69294000',
            'longitud'        => '-89.21819000',
            'marcas'          => $newBrands,
        ]);

        $status = $res->response()->getStatusCode();
        $this->assertTrue(in_array($status, [200, 302, 303], true), "Expected update success status, got {$status}.");

        $actual = $this->getAgencyBrandIds((int) $ag['idagencia']);
        sort($newBrands);
        sort($actual);
        $this->assertSame($newBrands, $actual);
    }

    // =========================================================================
    // FEATURE F07: Logical (Soft) Disable of Agency (T1-F07-01..T1-F07-05)
    // =========================================================================

    /**
     * T1-F07-01: POST agencias/deshabilitar/{id} with valid reason sets activo = 0.
     */
    public function testT1F07_01_PostAgenciasDeshabilitarSetsActivoZero(): void
    {
        $ag = $this->createAgencyViaHttp('supervisor_sv');

        $this->actingAsUser('supervisor_sv');
        $res = $this->postForm('agencias/deshabilitar/' . $ag['idagencia'], [
            'motivo_deshabilitacion' => 'Cierre temporal por remodelacion',
        ]);

        $status = $res->response()->getStatusCode();
        $this->assertTrue(in_array($status, [200, 302, 303], true), "Expected disable success status, got {$status}.");

        $reloaded = $this->findAgencyById((int) $ag['idagencia']);
        $this->assertNotNull($reloaded);
        $this->assertSame(0, (int) $reloaded['activo']);
    }

    /**
     * T1-F07-02: Soft-disable preserves Agencias row and historical Agencia_Marca associations (no DELETE).
     */
    public function testT1F07_02_SoftDisablePreservesAgencyRowAndMarcaPivot(): void
    {
        $brandIds = $this->getDefaultBrandIds(2);
        $ag       = $this->createAgencyViaHttp('supervisor_sv', ['marcas' => $brandIds]);

        $this->actingAsUser('supervisor_sv');
        $res = $this->postForm('agencias/deshabilitar/' . $ag['idagencia'], [
            'motivo_deshabilitacion' => 'Consolidacion operativa regional',
        ]);

        $status = $res->response()->getStatusCode();
        $this->assertTrue(in_array($status, [200, 302, 303], true), "Expected disable success status, got {$status}.");

        $reloaded = $this->findAgencyById((int) $ag['idagencia']);
        $this->assertNotNull($reloaded, 'Soft-disabled agency row must remain in Agencias table.');

        $actualBrands = $this->getAgencyBrandIds((int) $ag['idagencia']);
        sort($brandIds);
        sort($actualBrands);
        $this->assertSame($brandIds, $actualBrands, 'Historical Agencia_Marca links must be preserved.');
    }

    /**
     * T1-F07-03: Soft-disable stamps deshabilitado_en, deshabilitado_por, and motivo_deshabilitacion.
     */
    public function testT1F07_03_SoftDisableStampsTimestampActorAndReason(): void
    {
        $ag = $this->createAgencyViaHttp('supervisor_sv');

        $this->actingAsUser('supervisor_sv');
        $actor  = (string) ($this->activeSession['codigo_empleado'] ?? '');
        $motivo = 'Fin de contrato de arrendamiento';

        $res = $this->postForm('agencias/deshabilitar/' . $ag['idagencia'], [
            'motivo_deshabilitacion' => $motivo,
        ]);

        $status = $res->response()->getStatusCode();
        $this->assertTrue(in_array($status, [200, 302, 303], true), "Expected disable success status, got {$status}.");

        $this->assertAgencySoftDisabled((int) $ag['idagencia'], $actor, $motivo);
    }

    /**
     * T1-F07-04: Soft-disabled agency is excluded from GET agencias/mapa and default api/agencias/geolocalizacion.
     */
    public function testT1F07_04_SoftDisabledAgencyExcludedFromMapAndDefaultGeoApi(): void
    {
        $active   = $this->createAgencyViaHttp('supervisor_sv');
        $disabled = $this->createAgencyViaHttp('supervisor_sv');

        $this->actingAsUser('supervisor_sv');
        $disRes = $this->postForm('agencias/deshabilitar/' . $disabled['idagencia'], [
            'motivo_deshabilitacion' => 'Sucursal inactiva por mantenimiento',
        ]);
        $disStatus = $disRes->response()->getStatusCode();
        $this->assertTrue(in_array($disStatus, [200, 302, 303], true), "Expected disable success status, got {$disStatus}.");

        $mapRes = $this->getPage('agencias/mapa');
        $mapRes->assertStatus(200);
        $mapRes->assertDontSee((string) $disabled['nombre_agencia']);

        $apiRes = $this->getJson('api/agencias/geolocalizacion');
        $json   = $this->assertJsonOk($apiRes);
        $ids    = array_map(static fn (array $item): int => (int) ($item['id'] ?? 0), $json['agencias'] ?? []);

        $this->assertContains((int) $active['idagencia'], $ids);
        $this->assertNotContains((int) $disabled['idagencia'], $ids);
    }

    /**
     * T1-F07-05: Soft-disabled agency remains inspectable via agencias/ver/{id} and agencias?estado=inactivas.
     */
    public function testT1F07_05_SoftDisabledAgencyRemainsInspectableInVerAndInactivas(): void
    {
        $ag     = $this->createAgencyViaHttp('supervisor_sv');
        $motivo = 'Traslado a nueva zona comercial';

        $this->actingAsUser('supervisor_sv');
        $disRes = $this->postForm('agencias/deshabilitar/' . $ag['idagencia'], [
            'motivo_deshabilitacion' => $motivo,
        ]);
        $disStatus = $disRes->response()->getStatusCode();
        $this->assertTrue(in_array($disStatus, [200, 302, 303], true), "Expected disable success status, got {$disStatus}.");

        $verRes = $this->getPage('agencias/ver/' . $ag['idagencia']);
        $verRes->assertStatus(200);
        $verRes->assertSee((string) $ag['nombre_agencia']);
        $verRes->assertSee($motivo);

        $listRes = $this->getPage('agencias', ['estado' => 'inactivas']);
        $listRes->assertStatus(200);
        $listRes->assertSee((string) $ag['nombre_agencia']);
    }

    // =========================================================================
    // FEATURE F08: Comprehensive Agency Audit Logging (T1-F08-01..T1-F08-05)
    // =========================================================================

    /**
     * T1-F08-01: Agency creation writes CREAR audit row with Codigo_Empleado, fecha_hora, and datos_nuevos.
     */
    public function testT1F08_01_CreateWritesCrearAuditRow(): void
    {
        $ag    = $this->createAgencyViaHttp('supervisor_sv');
        $actor = (string) ($this->activeSession['codigo_empleado'] ?? '');

        $audit = $this->assertAuditRecorded((int) $ag['idagencia'], 'CREAR', $actor);
        $this->assertStringContainsString((string) $ag['nombre_agencia'], (string) ($audit['datos_nuevos'] ?? ''));
    }

    /**
     * T1-F08-02: Agency update writes ACTUALIZAR audit row with both datos_anteriores and datos_nuevos.
     */
    public function testT1F08_02_UpdateWritesActualizarAuditRowWithBeforeAndAfterSnapshots(): void
    {
        $ag      = $this->createAgencyViaHttp('supervisor_sv');
        $oldName = (string) $ag['nombre_agencia'];
        $newName = 'Agencia Editada Audit ' . uniqid();
        $chain   = $this->getGeoChainForPais('El Salvador');

        $this->actingAsUser('supervisor_sv');
        $actor = (string) ($this->activeSession['codigo_empleado'] ?? '');

        $res = $this->postForm('agencias/actualizar/' . $ag['idagencia'], [
            'nombre_agencia'  => $newName,
            'direccion'       => 'Nueva Direccion Audit #10',
            'id_pais'         => $chain['id_pais'],
            'id_departamento' => $chain['id_departamento'],
            'id_municipio'    => $chain['id_municipio'],
            'id_distrito'     => $chain['id_distrito'],
            'latitud'         => '13.69294000',
            'longitud'        => '-89.21819000',
            'marcas'          => $this->getDefaultBrandIds(1),
        ]);
        $status = $res->response()->getStatusCode();
        $this->assertTrue(in_array($status, [200, 302, 303], true), "Expected update success status, got {$status}.");

        $audit = $this->assertAuditRecorded((int) $ag['idagencia'], 'ACTUALIZAR', $actor);
        $this->assertStringContainsString($oldName, (string) ($audit['datos_anteriores'] ?? ''));
        $this->assertStringContainsString($newName, (string) ($audit['datos_nuevos'] ?? ''));
    }

    /**
     * T1-F08-03: Agency soft-disable writes DESHABILITAR audit row with motivo_deshabilitacion.
     */
    public function testT1F08_03_SoftDisableWritesDeshabilitarAuditRowWithMotivo(): void
    {
        $ag     = $this->createAgencyViaHttp('supervisor_sv');
        $motivo = 'Cierre definitivo por fusion de sucursales';

        $this->actingAsUser('supervisor_sv');
        $actor = (string) ($this->activeSession['codigo_empleado'] ?? '');

        $res = $this->postForm('agencias/deshabilitar/' . $ag['idagencia'], [
            'motivo_deshabilitacion' => $motivo,
        ]);
        $status = $res->response()->getStatusCode();
        $this->assertTrue(in_array($status, [200, 302, 303], true), "Expected disable success status, got {$status}.");

        $this->assertAuditRecorded((int) $ag['idagencia'], 'DESHABILITAR', $actor, $motivo);
    }

    /**
     * T1-F08-04: GET agencias/ver/{id} renders recorded audit trail entries.
     */
    public function testT1F08_04_DetailViewRendersRecordedAuditTrailEntries(): void
    {
        $ag    = $this->createAgencyViaHttp('supervisor_sv');
        $chain = $this->getGeoChainForPais('El Salvador');

        $this->actingAsUser('supervisor_sv');
        $actor = (string) ($this->activeSession['codigo_empleado'] ?? '');

        $updRes = $this->postForm('agencias/actualizar/' . $ag['idagencia'], [
            'nombre_agencia'  => $ag['nombre_agencia'] . ' v2',
            'direccion'       => 'Calle Auditoria #77',
            'id_pais'         => $chain['id_pais'],
            'id_departamento' => $chain['id_departamento'],
            'id_municipio'    => $chain['id_municipio'],
            'id_distrito'     => $chain['id_distrito'],
            'latitud'         => '13.69294000',
            'longitud'        => '-89.21819000',
            'marcas'          => $this->getDefaultBrandIds(1),
        ]);
        $status = $updRes->response()->getStatusCode();
        $this->assertTrue(in_array($status, [200, 302, 303], true), "Expected update success status, got {$status}.");

        $verRes = $this->getPage('agencias/ver/' . $ag['idagencia']);
        $verRes->assertStatus(200);

        $body = $verRes->getBody();
        $this->assertMatchesRegularExpression('/CREAR/i', $body);
        $this->assertMatchesRegularExpression('/ACTUALIZAR/i', $body);
        $verRes->assertSee($actor);
    }

    /**
     * T1-F08-05: Sequential CREAR -> ACTUALIZAR -> DESHABILITAR generates 3 ordered audit entries.
     */
    public function testT1F08_05_SequentialLifecycleGeneratesThreeOrderedAuditEntries(): void
    {
        $ag    = $this->createAgencyViaHttp('supervisor_sv');
        $chain = $this->getGeoChainForPais('El Salvador');

        $this->actingAsUser('supervisor_sv');
        $actor = (string) ($this->activeSession['codigo_empleado'] ?? '');

        $updRes = $this->postForm('agencias/actualizar/' . $ag['idagencia'], [
            'nombre_agencia'  => $ag['nombre_agencia'] . ' Paso 2',
            'direccion'       => 'Direccion Paso 2',
            'id_pais'         => $chain['id_pais'],
            'id_departamento' => $chain['id_departamento'],
            'id_municipio'    => $chain['id_municipio'],
            'id_distrito'     => $chain['id_distrito'],
            'latitud'         => '13.69500000',
            'longitud'        => '-89.21500000',
            'marcas'          => $this->getDefaultBrandIds(1),
        ]);
        $this->assertTrue(in_array($updRes->response()->getStatusCode(), [200, 302, 303], true));

        $disRes = $this->postForm('agencias/deshabilitar/' . $ag['idagencia'], [
            'motivo_deshabilitacion' => 'Ciclo completo finalizado',
        ]);
        $this->assertTrue(in_array($disRes->response()->getStatusCode(), [200, 302, 303], true));

        $logs = $this->getAuditLogsForAgency((int) $ag['idagencia']);
        $this->assertCount(3, $logs);
        $this->assertSame('CREAR', strtoupper((string) $logs[0]['tipo_operacion']));
        $this->assertSame('ACTUALIZAR', strtoupper((string) $logs[1]['tipo_operacion']));
        $this->assertSame('DESHABILITAR', strtoupper((string) $logs[2]['tipo_operacion']));

        foreach ($logs as $log) {
            $this->assertSame($actor, (string) $log['codigo_empleado']);
        }
        $this->assertLessThan((int) $logs[1]['id_auditoria'], (int) $logs[0]['id_auditoria']);
        $this->assertLessThan((int) $logs[2]['id_auditoria'], (int) $logs[1]['id_auditoria']);
    }

    // =========================================================================
    // FEATURE F09: Dynamic Cascading Geographic Selectors & API (T1-F09-01..05)
    // =========================================================================

    /**
     * T1-F09-01: GET api/geo/paises returns supervisor's country with requiere_distrito.
     */
    public function testT1F09_01_GetApiGeoPaisesReturnsSupervisorCountryWithRequiereDistrito(): void
    {
        $this->actingAsUser('supervisor_sv');
        $resSv  = $this->getJson('api/geo/paises');
        $jsonSv = $this->assertJsonOk($resSv);

        $paisesSv = $jsonSv['paises'] ?? [];
        $this->assertNotEmpty($paisesSv);
        $this->assertCount(1, $paisesSv);
        $this->assertSame('El Salvador', $paisesSv[0]['pais'] ?? null);
        $this->assertArrayHasKey('requiere_distrito', $paisesSv[0]);
        $this->assertTrue((bool) $paisesSv[0]['requiere_distrito']);

        $this->actingAsUser('supervisor_gt');
        $resGt  = $this->getJson('api/geo/paises');
        $jsonGt = $this->assertJsonOk($resGt);

        $paisesGt = $jsonGt['paises'] ?? [];
        $this->assertCount(1, $paisesGt);
        $this->assertSame('Guatemala', $paisesGt[0]['pais'] ?? null);
        $this->assertFalse((bool) $paisesGt[0]['requiere_distrito']);
    }

    /**
     * T1-F09-02: GET api/geo/departamentos returns only departments of supervisor's country.
     */
    public function testT1F09_02_GetApiGeoDepartamentosReturnsOnlySupervisorCountryDepartments(): void
    {
        $svPais   = $this->findPaisByName('El Salvador');
        $idPaisSv = (int) $svPais['id_pais'];

        $this->actingAsUser('supervisor_sv');
        $res  = $this->getJson('api/geo/departamentos');
        $json = $this->assertJsonOk($res);

        $deptos = $json['departamentos'] ?? [];
        $this->assertNotEmpty($deptos);

        $names = [];
        foreach ($deptos as $depto) {
            $this->assertSame($idPaisSv, (int) ($depto['id_pais'] ?? 0));
            $names[] = (string) ($depto['departamento'] ?? '');
        }

        $this->assertContains('San Salvador', $names);
        $this->assertNotContains('Sacatepéquez', $names);
    }

    /**
     * T1-F09-03: GET api/geo/municipios?id_departamento=X returns child municipalities.
     */
    public function testT1F09_03_GetApiGeoMunicipiosReturnsChildMunicipalities(): void
    {
        $svPais   = $this->findPaisByName('El Salvador');
        $sanSalv  = $this->findDepartamentoByName('San Salvador', (int) $svPais['id_pais']);
        $idDepto  = (int) $sanSalv['id_departamento'];

        $this->actingAsUser('supervisor_sv');
        $res  = $this->getJson('api/geo/municipios', ['id_departamento' => $idDepto]);
        $json = $this->assertJsonOk($res);

        $munis = $json['municipios'] ?? [];
        $this->assertNotEmpty($munis);

        $names = [];
        foreach ($munis as $muni) {
            $this->assertSame($idDepto, (int) ($muni['id_departamento'] ?? 0));
            $names[] = (string) ($muni['municipio'] ?? '');
        }
        $this->assertContains('San Salvador Centro', $names);
    }

    /**
     * T1-F09-04: GET api/geo/distritos?id_municipio=X returns child districts.
     */
    public function testT1F09_04_GetApiGeoDistritosReturnsChildDistricts(): void
    {
        $svPais  = $this->findPaisByName('El Salvador');
        $sanSalv = $this->findDepartamentoByName('San Salvador', (int) $svPais['id_pais']);
        $muni    = $this->findMunicipioByName('San Salvador Centro', (int) $sanSalv['id_departamento']);
        $idMuni  = (int) $muni['id_municipio'];

        $this->actingAsUser('supervisor_sv');
        $res  = $this->getJson('api/geo/distritos', ['id_municipio' => $idMuni]);
        $json = $this->assertJsonOk($res);

        $distritos = $json['distritos'] ?? [];
        $this->assertNotEmpty($distritos);

        $names = [];
        foreach ($distritos as $dist) {
            $this->assertSame($idMuni, (int) ($dist['id_municipio'] ?? 0));
            $names[] = (string) ($dist['distrito'] ?? '');
        }
        $this->assertContains('San Salvador', $names);
    }

    /**
     * T1-F09-05: agencias/crear and agencias/editar/{id} render cascading selectors and geo JS hooks.
     */
    public function testT1F09_05_CreateAndEditViewsRenderCascadingSelectorsAndGeoHooks(): void
    {
        $ag = $this->createAgencyViaHttp('supervisor_sv');

        $this->actingAsUser('supervisor_sv');
        foreach (['agencias/crear', 'agencias/editar/' . $ag['idagencia']] as $route) {
            $res = $this->getPage($route);
            $res->assertStatus(200);
            $body = $res->getBody();

            foreach (['id_pais', 'id_departamento', 'id_municipio', 'id_distrito'] as $selectName) {
                $this->assertMatchesRegularExpression(
                    '/<select[^>]+name=["\']' . preg_quote($selectName, '/') . '["\']/i',
                    $body,
                    "Expected cascading <select> for '{$selectName}' in {$route}."
                );
            }

            $this->assertMatchesRegularExpression('/api\/geo\/departamentos/i', $body);
            $this->assertMatchesRegularExpression('/api\/geo\/municipios/i', $body);
            $this->assertMatchesRegularExpression('/api\/geo\/distritos/i', $body);
        }
    }

    // =========================================================================
    // FEATURE F10: Guatemala Optional District & Country Extensibility (T1-F10-01..05)
    // =========================================================================

    /**
     * T1-F10-01: Seeded Pais has requiere_distrito = 0 for Guatemala and 1 for El Salvador.
     */
    public function testT1F10_01_SeededPaisHasCorrectRequiereDistritoFlags(): void
    {
        $sv = $this->findPaisByName('El Salvador');
        $gt = $this->findPaisByName('Guatemala');
        $hn = $this->findPaisByName('Honduras');

        $this->assertSame(1, (int) $sv['requiere_distrito']);
        $this->assertSame(0, (int) $gt['requiere_distrito']);
        $this->assertSame(1, (int) $hn['requiere_distrito']);
    }

    /**
     * T1-F10-02: GT supervisor registers agency with omitted/null id_distrito.
     */
    public function testT1F10_02_GtSupervisorRegistersAgencyWithOmittedOrNullDistrito(): void
    {
        $this->actingAsUser('supervisor_gt');
        $payload = $this->buildValidAgencyPayload('Guatemala', ['id_distrito' => '']);

        $res    = $this->postForm('agencias/guardar', $payload);
        $status = $res->response()->getStatusCode();
        $this->assertTrue(in_array($status, [200, 201, 302, 303], true), "Expected success status, got {$status}.");

        $row = $this->findAgencyByName((string) $payload['nombre_agencia']);
        $this->assertNotNull($row);
        $this->assertNull($row['id_distrito']);
    }

    /**
     * T1-F10-03: GT supervisor updates agency leaving id_distrito null/empty.
     */
    public function testT1F10_03_GtSupervisorUpdatesAgencyLeavingDistritoNull(): void
    {
        $gt    = $this->createAgencyViaHttp('supervisor_gt');
        $chain = $this->getGeoChainForPais('Guatemala');

        $this->actingAsUser('supervisor_gt');
        $newName = 'Agencia GT Actualizada ' . uniqid();
        $res     = $this->postForm('agencias/actualizar/' . $gt['idagencia'], [
            'nombre_agencia'  => $newName,
            'direccion'       => '6a Avenida Zona 10, Ciudad de Guatemala',
            'id_pais'         => $chain['id_pais'],
            'id_departamento' => $chain['id_departamento'],
            'id_municipio'    => $chain['id_municipio'],
            'id_distrito'     => '',
            'latitud'         => '14.61111000',
            'longitud'        => '-90.51111000',
            'marcas'          => $this->getDefaultBrandIds(1),
        ]);

        $status = $res->response()->getStatusCode();
        $this->assertTrue(in_array($status, [200, 302, 303], true), "Expected update success status, got {$status}.");

        $updated = $this->findAgencyById((int) $gt['idagencia']);
        $this->assertNotNull($updated);
        $this->assertSame($newName, $updated['nombre_agencia']);
        $this->assertNull($updated['id_distrito']);
    }

    /**
     * T1-F10-04: Newly inserted extensible country (requiere_distrito = 0) works end-to-end without code edits.
     */
    public function testT1F10_04_ExtensibleCountryWorksEndToEndWithoutCodeEdits(): void
    {
        $cr = $this->seedExtensibleCountryWithDivisions('Costa Rica', 0, 'San José', 'Central');

        $usuarioTable = $this->resolveTableName('Usuario');
        $this->assertNotNull($usuarioTable);
        $crUsername = 'supervisor_cr_e2e';
        $this->db->table($usuarioTable)->insert([
            'Codigo_Empleado'   => 'EMP-CR-E2E',
            'Nombre'            => 'Laura Supervisora CR',
            'Usuario'           => $crUsername,
            'Contrasena'        => password_hash('SuperCr123!', PASSWORD_BCRYPT),
            'Id_Pais'           => $cr['id_pais'],
            'telefono'          => '50680000001',
            'rol'               => 'Supervisor',
            'intentos_fallidos' => 0,
            'bloqueado'         => 0,
            'ultimo_fallo'      => null,
            'ultimo_login'      => null,
            'creado_en'         => '2026-11-18 10:00:00',
            'actualizado_en'    => '2026-11-18 10:00:00',
        ]);

        $this->actingAsUser($crUsername);

        $paisesRes = $this->assertJsonOk($this->getJson('api/geo/paises'));
        $this->assertSame('Costa Rica', $paisesRes['paises'][0]['pais'] ?? null);
        $this->assertFalse((bool) ($paisesRes['paises'][0]['requiere_distrito'] ?? true));

        $deptosRes = $this->assertJsonOk($this->getJson('api/geo/departamentos'));
        $this->assertSame('San José', $deptosRes['departamentos'][0]['departamento'] ?? null);

        $munisRes = $this->assertJsonOk($this->getJson('api/geo/municipios', ['id_departamento' => $cr['id_departamento']]));
        $this->assertSame('Central', $munisRes['municipios'][0]['municipio'] ?? null);

        $agencyName = 'Agencia CR E2E ' . uniqid();
        $createRes  = $this->postForm('agencias/guardar', [
            'nombre_agencia'  => $agencyName,
            'direccion'       => 'Avenida Central #202, San José',
            'id_pais'         => $cr['id_pais'],
            'id_departamento' => $cr['id_departamento'],
            'id_municipio'    => $cr['id_municipio'],
            'latitud'         => '9.92806900',
            'longitud'        => '-84.09072500',
            'marcas'          => $this->getDefaultBrandIds(1),
        ]);
        $status = $createRes->response()->getStatusCode();
        $this->assertTrue(in_array($status, [200, 201, 302, 303], true), "Expected CR agency creation success, got {$status}.");

        $geoRes = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $this->assertSame('Costa Rica', $geoRes['pais'] ?? null);
        $names = array_map(static fn (array $a): string => (string) ($a['nombre'] ?? ''), $geoRes['agencias'] ?? []);
        $this->assertContains($agencyName, $names);
    }

    /**
     * T1-F10-05: Create/edit view exposes country district requirement metadata (requiere_distrito) dynamically.
     */
    public function testT1F10_05_CreateAndEditViewsExposeCountryDistrictRequirementMetadata(): void
    {
        $gt = $this->createAgencyViaHttp('supervisor_gt');

        $this->actingAsUser('supervisor_gt');
        foreach (['agencias/crear', 'agencias/editar/' . $gt['idagencia']] as $route) {
            $res = $this->getPage($route);
            $res->assertStatus(200);
            $this->assertMatchesRegularExpression(
                '/requiere[_-]distrito/i',
                $res->getBody(),
                "View '{$route}' must expose requiere_distrito metadata for dynamic district handling."
            );
        }
    }

    // =========================================================================
    // FEATURE F11: Interactive Operational Map (Leaflet + OSM) (T1-F11-01..05)
    // =========================================================================

    /**
     * T1-F11-01: GET agencias/mapa renders 200 referencing locally vendored Leaflet CSS/JS and OpenStreetMap tile URL.
     */
    public function testT1F11_01_GetAgenciasMapaRendersWithLocalLeafletAndOsmTileUrl(): void
    {
        $this->actingAsUser('supervisor_sv');
        $res = $this->getPage('agencias/mapa');
        $res->assertStatus(200);

        $body = $res->getBody();
        $this->assertMatchesRegularExpression('/assets\/leaflet\/leaflet\.css/i', $body);
        $this->assertMatchesRegularExpression('/assets\/leaflet\/leaflet\.js/i', $body);
        $this->assertMatchesRegularExpression('/tile\.openstreetmap\.org/i', $body);
    }

    /**
     * T1-F11-02: Active agencies with valid coordinates are exposed to the operational map.
     */
    public function testT1F11_02_ActiveAgenciesWithValidCoordsExposedToOperationalMap(): void
    {
        $ag = $this->createAgencyViaHttp('supervisor_sv', [
            'latitud'  => '13.69450000',
            'longitud' => '-89.21230000',
        ]);

        $this->actingAsUser('supervisor_sv');
        $mapRes = $this->getPage('agencias/mapa');
        $mapRes->assertStatus(200);

        $body           = $mapRes->getBody();
        $embedsOrFetch  = str_contains($body, (string) $ag['nombre_agencia'])
            || str_contains($body, 'api/agencias/geolocalizacion');
        $this->assertTrue($embedsOrFetch, 'Operational map must either embed active agencies or fetch api/agencias/geolocalizacion.');

        $apiJson = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $ids     = array_map(static fn (array $a): int => (int) ($a['id'] ?? 0), $apiJson['agencias'] ?? []);
        $this->assertContains((int) $ag['idagencia'], $ids);
    }

    /**
     * T1-F11-03: Map marker popup template/payload includes nombre, direccion, pais, and marcas.
     */
    public function testT1F11_03_MapMarkerPopupIncludesNombreDireccionPaisAndMarcas(): void
    {
        $brandIds = $this->getDefaultBrandIds(2);
        $ag       = $this->createAgencyViaHttp('supervisor_sv', ['marcas' => $brandIds]);

        $this->actingAsUser('supervisor_sv');
        $mapRes = $this->getPage('agencias/mapa');
        $mapRes->assertStatus(200);
        $body = $mapRes->getBody();

        $this->assertMatchesRegularExpression('/bindPopup/i', $body);
        $this->assertMatchesRegularExpression('/nombre/i', $body);
        $this->assertMatchesRegularExpression('/direcci[oó]n/i', $body);
        $this->assertMatchesRegularExpression('/pa[ií]s/i', $body);
        $this->assertMatchesRegularExpression('/marcas/i', $body);

        $apiJson = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $matched = null;
        foreach ($apiJson['agencias'] ?? [] as $item) {
            if ((int) ($item['id'] ?? 0) === (int) $ag['idagencia']) {
                $matched = $item;
                break;
            }
        }
        $this->assertNotNull($matched);
        $this->assertSame($ag['nombre_agencia'], $matched['nombre'] ?? null);
        $this->assertSame($ag['direccion'], $matched['direccion'] ?? null);
        $this->assertSame('El Salvador', $matched['pais'] ?? null);
        $this->assertCount(2, $matched['marcas'] ?? []);
    }

    /**
     * T1-F11-04: Map view fetches/displays strictly the supervisor's country agencies.
     */
    public function testT1F11_04_MapViewDisplaysStrictlySupervisorCountryAgencies(): void
    {
        $sv = $this->createAgencyViaHttp('supervisor_sv');
        $gt = $this->createAgencyViaHttp('supervisor_gt');

        $this->actingAsUser('supervisor_sv');
        $mapRes = $this->getPage('agencias/mapa');
        $mapRes->assertStatus(200);
        $mapRes->assertSee('El Salvador');
        $mapRes->assertDontSee((string) $gt['nombre_agencia']);

        $apiJson = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $ids     = array_map(static fn (array $a): int => (int) ($a['id'] ?? 0), $apiJson['agencias'] ?? []);
        $this->assertContains((int) $sv['idagencia'], $ids);
        $this->assertNotContains((int) $gt['idagencia'], $ids);
    }

    /**
     * T1-F11-05: Local Leaflet assets under public/assets/leaflet/ exist on disk.
     */
    public function testT1F11_05_LocalLeafletAssetsExistOnDisk(): void
    {
        $cssPath = ROOTPATH . 'public/assets/leaflet/leaflet.css';
        $jsPath  = ROOTPATH . 'public/assets/leaflet/leaflet.js';

        $this->assertFileExists($cssPath, 'Local vendored leaflet.css must exist under public/assets/leaflet/.');
        $this->assertFileExists($jsPath, 'Local vendored leaflet.js must exist under public/assets/leaflet/.');
        $this->assertGreaterThan(0, (int) filesize($cssPath));
        $this->assertGreaterThan(0, (int) filesize($jsPath));
    }

    // =========================================================================
    // FEATURE F12: Map Click-to-Set Coordinates (Create & Edit) (T1-F12-01..05)
    // =========================================================================

    /**
     * T1-F12-01: GET agencias/crear includes embedded Leaflet map container plus latitud and longitud inputs.
     */
    public function testT1F12_01_CreateFormIncludesEmbeddedLeafletMapAndCoordInputs(): void
    {
        $this->actingAsUser('supervisor_sv');
        $res = $this->getPage('agencias/crear');
        $res->assertStatus(200);

        $body = $res->getBody();
        $this->assertMatchesRegularExpression('/assets\/leaflet\/leaflet\.css/i', $body);
        $this->assertMatchesRegularExpression('/assets\/leaflet\/leaflet\.js/i', $body);
        $this->assertMatchesRegularExpression('/<div[^>]+id=["\'][^"\']*map[^"\']*["\']/i', $body);
        $this->assertMatchesRegularExpression('/<input[^>]+name=["\']latitud["\']/i', $body);
        $this->assertMatchesRegularExpression('/<input[^>]+name=["\']longitud["\']/i', $body);
    }

    /**
     * T1-F12-02: Create form includes click-to-set JS logic updating latitud/longitud and marker position.
     */
    public function testT1F12_02_CreateFormIncludesClickToSetJsUpdatingInputsAndMarker(): void
    {
        $this->actingAsUser('supervisor_sv');
        $res = $this->getPage('agencias/crear');
        $res->assertStatus(200);

        $body = $res->getBody();
        $this->assertMatchesRegularExpression('/\.on\s*\(\s*["\']click["\']/i', $body);
        $this->assertMatchesRegularExpression('/L\.marker/i', $body);
        $this->assertMatchesRegularExpression('/latitud/i', $body);
        $this->assertMatchesRegularExpression('/longitud/i', $body);
    }

    /**
     * T1-F12-03: GET agencias/editar/{id} initializes embedded Leaflet map with existing agency coordinates.
     */
    public function testT1F12_03_EditFormInitializesEmbeddedMapWithExistingCoordinates(): void
    {
        $ag = $this->createAgencyViaHttp('supervisor_sv', [
            'latitud'  => '13.71111000',
            'longitud' => '-89.22222000',
        ]);

        $this->actingAsUser('supervisor_sv');
        $res = $this->getPage('agencias/editar/' . $ag['idagencia']);
        $res->assertStatus(200);

        $body = $res->getBody();
        $this->assertMatchesRegularExpression('/assets\/leaflet\/leaflet\.js/i', $body);
        $this->assertMatchesRegularExpression('/<div[^>]+id=["\'][^"\']*map[^"\']*["\']/i', $body);
        $this->assertMatchesRegularExpression('/\.on\s*\(\s*["\']click["\']/i', $body);
        $res->assertSee('13.71111');
        $res->assertSee('-89.22222');
    }

    /**
     * T1-F12-04: Submitting map-captured coordinates on create persists exact decimal precision.
     */
    public function testT1F12_04_SubmittingMapCapturedCoordinatesOnCreatePersistsExactPrecision(): void
    {
        $this->actingAsUser('supervisor_sv');
        $payload = $this->buildValidAgencyPayload('El Salvador', [
            'latitud'  => '13.69294123',
            'longitud' => '-89.21819456',
        ]);

        $res    = $this->postForm('agencias/guardar', $payload);
        $status = $res->response()->getStatusCode();
        $this->assertTrue(in_array($status, [200, 201, 302, 303], true), "Expected create success status, got {$status}.");

        $row = $this->findAgencyByName((string) $payload['nombre_agencia']);
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(13.69294123, (float) $row['latitud'], 0.000001);
        $this->assertEqualsWithDelta(-89.21819456, (float) $row['longitud'], 0.000001);
    }

    /**
     * T1-F12-05: Submitting updated map-captured coordinates on edit persists updated decimal precision.
     */
    public function testT1F12_05_SubmittingUpdatedMapCapturedCoordinatesOnEditPersistsExactPrecision(): void
    {
        $ag    = $this->createAgencyViaHttp('supervisor_sv');
        $chain = $this->getGeoChainForPais('El Salvador');

        $this->actingAsUser('supervisor_sv');
        $res = $this->postForm('agencias/actualizar/' . $ag['idagencia'], [
            'nombre_agencia'  => $ag['nombre_agencia'],
            'direccion'       => $ag['direccion'],
            'id_pais'         => $chain['id_pais'],
            'id_departamento' => $chain['id_departamento'],
            'id_municipio'    => $chain['id_municipio'],
            'id_distrito'     => $chain['id_distrito'],
            'latitud'         => '13.74567890',
            'longitud'        => '-89.15432100',
            'marcas'          => $this->getDefaultBrandIds(1),
        ]);

        $status = $res->response()->getStatusCode();
        $this->assertTrue(in_array($status, [200, 302, 303], true), "Expected update success status, got {$status}.");

        $updated = $this->findAgencyById((int) $ag['idagencia']);
        $this->assertNotNull($updated);
        $this->assertEqualsWithDelta(13.7456789, (float) $updated['latitud'], 0.000001);
        $this->assertEqualsWithDelta(-89.154321, (float) $updated['longitud'], 0.000001);
    }

    // =========================================================================
    // FEATURE F13: Agency Geolocation REST/JSON API (T1-F13-01..T1-F13-05)
    // =========================================================================

    /**
     * T1-F13-01: GET api/agencias/geolocalizacion returns 200 with status, pais, total, and agencias[] fields.
     */
    public function testT1F13_01_GeolocationApiReturnsExpectedJsonContract(): void
    {
        $honda  = $this->findMarcaByName('Honda');
        $yamaha = $this->findMarcaByName('Yamaha');

        $ag = $this->createAgencyViaHttp('supervisor_sv', [
            'marcas'   => [(int) $honda['id_marca'], (int) $yamaha['id_marca']],
            'latitud'  => '13.69294000',
            'longitud' => '-89.21819000',
        ]);

        $this->actingAsUser('supervisor_sv');
        $json = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));

        $this->assertSame('El Salvador', $json['pais'] ?? null);
        $this->assertGreaterThanOrEqual(1, (int) ($json['total'] ?? 0));
        $this->assertIsArray($json['agencias'] ?? null);

        $found = null;
        foreach ($json['agencias'] as $item) {
            if ((int) ($item['id'] ?? 0) === (int) $ag['idagencia']) {
                $found = $item;
                break;
            }
        }

        $this->assertNotNull($found, 'Created agency must appear in geolocation API response.');
        $this->assertSame((int) $ag['idagencia'], $found['id']);
        $this->assertSame($ag['nombre_agencia'], $found['nombre']);
        $this->assertSame('El Salvador', $found['pais']);
        $this->assertSame($ag['direccion'], $found['direccion']);
        $this->assertIsArray($found['marcas']);
        $this->assertContains('Honda', $found['marcas']);
        $this->assertContains('Yamaha', $found['marcas']);
        $this->assertEqualsWithDelta(13.69294, (float) $found['latitud'], 0.00001);
        $this->assertEqualsWithDelta(-89.21819, (float) $found['longitud'], 0.00001);
    }

    /**
     * T1-F13-02: Filtering by id_marca / marca returns matching agencies (including multi-brand).
     */
    public function testT1F13_02_GeolocationApiFiltersByBrandIncludingMultiBrand(): void
    {
        $honda  = $this->findMarcaByName('Honda');
        $yamaha = $this->findMarcaByName('Yamaha');
        $suzuki = $this->findMarcaByName('Suzuki');

        $a1 = $this->createAgencyViaHttp('supervisor_sv', [
            'marcas' => [(int) $honda['id_marca'], (int) $yamaha['id_marca']],
        ]);
        $a2 = $this->createAgencyViaHttp('supervisor_sv', [
            'marcas' => [(int) $suzuki['id_marca']],
        ]);

        $this->actingAsUser('supervisor_sv');

        $byId  = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion', ['id_marca' => (int) $yamaha['id_marca']]));
        $idsBy = array_map(static fn (array $x): int => (int) $x['id'], $byId['agencias'] ?? []);
        $this->assertContains((int) $a1['idagencia'], $idsBy);
        $this->assertNotContains((int) $a2['idagencia'], $idsBy);

        $byName  = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion', ['marca' => 'Yamaha']));
        $idsName = array_map(static fn (array $x): int => (int) $x['id'], $byName['agencias'] ?? []);
        $this->assertContains((int) $a1['idagencia'], $idsName);
        $this->assertNotContains((int) $a2['idagencia'], $idsName);
    }

    /**
     * T1-F13-03: Filtering by id_departamento returns matching agencies.
     */
    public function testT1F13_03_GeolocationApiFiltersByDepartamento(): void
    {
        $svPais     = $this->findPaisByName('El Salvador');
        $idPaisSv   = (int) $svPais['id_pais'];
        $sanSalv    = $this->findDepartamentoByName('San Salvador', $idPaisSv);
        $laLibertad = $this->findDepartamentoByName('La Libertad', $idPaisSv);

        $ssMuni = $this->findAnyMunicipioForDepartamento((int) $sanSalv['id_departamento']);
        $ssDist = $this->findAnyDistritoForMunicipio((int) $ssMuni['id_municipio']);
        $llMuni = $this->findAnyMunicipioForDepartamento((int) $laLibertad['id_departamento']);
        $llDist = $this->findAnyDistritoForMunicipio((int) $llMuni['id_municipio']);

        $this->assertNotNull($ssDist);
        $this->assertNotNull($llDist);

        $a1 = $this->createAgencyViaHttp('supervisor_sv', [
            'id_departamento' => (int) $sanSalv['id_departamento'],
            'id_municipio'    => (int) $ssMuni['id_municipio'],
            'id_distrito'     => (int) $ssDist['id_distrito'],
        ]);
        $a2 = $this->createAgencyViaHttp('supervisor_sv', [
            'id_departamento' => (int) $laLibertad['id_departamento'],
            'id_municipio'    => (int) $llMuni['id_municipio'],
            'id_distrito'     => (int) $llDist['id_distrito'],
        ]);

        $this->actingAsUser('supervisor_sv');
        $json = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion', [
            'id_departamento' => (int) $laLibertad['id_departamento'],
        ]));

        $ids = array_map(static fn (array $x): int => (int) $x['id'], $json['agencias'] ?? []);
        $this->assertContains((int) $a2['idagencia'], $ids);
        $this->assertNotContains((int) $a1['idagencia'], $ids);
    }

    /**
     * T1-F13-04: Filtering by id_municipio and id_distrito narrows results accurately.
     */
    public function testT1F13_04_GeolocationApiFiltersByMunicipioAndDistrito(): void
    {
        $svPais   = $this->findPaisByName('El Salvador');
        $sanSalv  = $this->findDepartamentoByName('San Salvador', (int) $svPais['id_pais']);
        $muni     = $this->findMunicipioByName('San Salvador Centro', (int) $sanSalv['id_departamento']);
        $distSS   = $this->findDistritoByName('San Salvador', (int) $muni['id_municipio']);
        $distMej  = $this->findDistritoByName('Mejicanos', (int) $muni['id_municipio']);

        $a1 = $this->createAgencyViaHttp('supervisor_sv', [
            'id_departamento' => (int) $sanSalv['id_departamento'],
            'id_municipio'    => (int) $muni['id_municipio'],
            'id_distrito'     => (int) $distSS['id_distrito'],
        ]);
        $a2 = $this->createAgencyViaHttp('supervisor_sv', [
            'id_departamento' => (int) $sanSalv['id_departamento'],
            'id_municipio'    => (int) $muni['id_municipio'],
            'id_distrito'     => (int) $distMej['id_distrito'],
        ]);

        $this->actingAsUser('supervisor_sv');

        $byMuni = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion', [
            'id_municipio' => (int) $muni['id_municipio'],
        ]));
        $muniIds = array_map(static fn (array $x): int => (int) $x['id'], $byMuni['agencias'] ?? []);
        $this->assertContains((int) $a1['idagencia'], $muniIds);
        $this->assertContains((int) $a2['idagencia'], $muniIds);

        $byDist = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion', [
            'id_municipio' => (int) $muni['id_municipio'],
            'id_distrito'  => (int) $distMej['id_distrito'],
        ]));
        $distIds = array_map(static fn (array $x): int => (int) $x['id'], $byDist['agencias'] ?? []);
        $this->assertContains((int) $a2['idagencia'], $distIds);
        $this->assertNotContains((int) $a1['idagencia'], $distIds);
    }

    /**
     * T1-F13-05: Default query excludes inactive agencies (activo = 0).
     */
    public function testT1F13_05_GeolocationApiExcludesInactiveAgenciesByDefault(): void
    {
        $active   = $this->createAgencyViaHttp('supervisor_sv');
        $disabled = $this->createAgencyViaHttp('supervisor_sv');

        $this->actingAsUser('supervisor_sv');
        $disRes = $this->postForm('agencias/deshabilitar/' . $disabled['idagencia'], [
            'motivo_deshabilitacion' => 'Fuera de servicio temporal',
        ]);
        $this->assertTrue(in_array($disRes->response()->getStatusCode(), [200, 302, 303], true));

        $json = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $ids  = array_map(static fn (array $x): int => (int) $x['id'], $json['agencias'] ?? []);

        $this->assertContains((int) $active['idagencia'], $ids);
        $this->assertNotContains((int) $disabled['idagencia'], $ids);
    }

    // =========================================================================
    // FEATURE F14: PostgreSQL (Base_Datos.sql) + SQLite Schema & FK Integrity
    // =========================================================================

    /**
     * T1-F14-01: Base_Datos.sql defines all HU-002 tables and contract columns.
     */
    public function testT1F14_01_BaseDatosSqlDefinesAllHu002TablesAndContractColumns(): void
    {
        $sqlPath = ROOTPATH . 'Base_Datos.sql';
        $this->assertFileExists($sqlPath);
        $sql = file_get_contents($sqlPath);
        $this->assertIsString($sql);

        foreach (self::REQUIRED_HU002_TABLES as $table) {
            $this->assertMatchesRegularExpression(
                '/CREATE\s+TABLE\s+' . preg_quote($table, '/') . '\s*\(/i',
                $sql,
                "Base_Datos.sql must define CREATE TABLE {$table}."
            );
        }

        $this->assertMatchesRegularExpression('/CREATE\s+TABLE\s+Pais\s*\([^;]*requiere_distrito/is', $sql);
        $this->assertMatchesRegularExpression('/CREATE\s+TABLE\s+Marca\s*\([^;]*Marca[^;]*UNIQUE/is', $sql);
        $this->assertMatchesRegularExpression('/CREATE\s+TABLE\s+Agencia_Marca\s*\([^;]*UNIQUE\s*\(\s*Idagencia\s*,\s*Id_marca\s*\)/is', $sql);
        $this->assertMatchesRegularExpression('/CREATE\s+TABLE\s+Auditoria_Agencias\s*\([^;]*CHECK\s*\([^;]*CREAR[^;]*ACTUALIZAR[^;]*DESHABILITAR/is', $sql);

        foreach (['Idagencia', 'nombre_agencia', 'direccion', 'Id_pais', 'Id_departamento', 'Id_municipio', 'Id_distrito', 'latitud', 'longitud', 'Id_marca', 'activo', 'motivo_deshabilitacion', 'deshabilitado_en', 'deshabilitado_por', 'creado_en', 'actualizado_en'] as $col) {
            $this->assertMatchesRegularExpression('/CREATE\s+TABLE\s+Agencias\s*\([^;]*\b' . preg_quote($col, '/') . '\b/is', $sql);
        }

        foreach (['intentos_fallidos', 'bloqueado', 'ultimo_fallo', 'ultimo_login', 'creado_en', 'actualizado_en'] as $col) {
            $this->assertMatchesRegularExpression('/CREATE\s+TABLE\s+Usuario\s*\([^;]*\b' . preg_quote($col, '/') . '\b/is', $sql);
        }

        foreach (['Id_visita', 'Idagencia', 'Codigo_Asesor', 'fecha_visita', 'hora_visita', 'latitud', 'longitud', 'observaciones', 'creado_en'] as $col) {
            $this->assertMatchesRegularExpression('/CREATE\s+TABLE\s+Visitas\s*\([^;]*\b' . preg_quote($col, '/') . '\b/is', $sql);
        }
    }

    /**
     * T1-F14-02: SQLite test DB exposes all 10 required tables and contract columns.
     */
    public function testT1F14_02_SqliteTestDbExposesAll10TablesAndContractColumns(): void
    {
        $this->requireHU002Schema();

        $expectedColumns = [
            'Pais'               => ['id_pais', 'pais', 'requiere_distrito'],
            'Marca'              => ['id_marca', 'marca'],
            'Departamento'       => ['id_departamento', 'departamento', 'id_pais'],
            'Municipio'          => ['id_municipio', 'municipio', 'id_departamento'],
            'Distrito'           => ['id_distrito', 'distrito', 'id_municipio'],
            'Agencias'           => ['idagencia', 'nombre_agencia', 'direccion', 'id_pais', 'id_departamento', 'id_municipio', 'id_distrito', 'latitud', 'longitud', 'id_marca', 'activo', 'motivo_deshabilitacion', 'deshabilitado_en', 'deshabilitado_por', 'creado_en', 'actualizado_en'],
            'Agencia_Marca'      => ['id_agencia_marca', 'idagencia', 'id_marca'],
            'Auditoria_Agencias' => ['id_auditoria', 'idagencia', 'codigo_empleado', 'fecha_hora', 'tipo_operacion', 'datos_anteriores', 'datos_nuevos', 'motivo_deshabilitacion'],
            'Visitas'            => ['id_visita', 'idagencia', 'codigo_asesor', 'fecha_visita', 'hora_visita', 'latitud', 'longitud', 'observaciones', 'creado_en'],
            'Usuario'            => ['codigo_empleado', 'nombre', 'usuario', 'contrasena', 'id_pais', 'telefono', 'rol', 'intentos_fallidos', 'bloqueado', 'ultimo_fallo', 'ultimo_login', 'creado_en', 'actualizado_en'],
        ];

        foreach ($expectedColumns as $table => $requiredCols) {
            $actualCols = $this->getTableColumnsNormalized($table);
            foreach ($requiredCols as $col) {
                $this->assertContains($col, $actualCols, "Table '{$table}' must contain column '{$col}'.");
            }
        }
    }

    /**
     * T1-F14-03: SQLite PRAGMA foreign_keys = ON verified along with FKs on Agencias.
     */
    public function testT1F14_03_SqliteForeignKeysEnabledAndAgenciasFksVerified(): void
    {
        $this->requireHU002Schema();
        $this->assertSqliteForeignKeysEnabled();

        $fks = $this->getForeignKeysNormalized('Agencias');
        $expectedFks = [
            ['from' => 'id_pais', 'table' => 'pais', 'to' => 'id_pais'],
            ['from' => 'id_departamento', 'table' => 'departamento', 'to' => 'id_departamento'],
            ['from' => 'id_municipio', 'table' => 'municipio', 'to' => 'id_municipio'],
            ['from' => 'id_distrito', 'table' => 'distrito', 'to' => 'id_distrito'],
            ['from' => 'id_marca', 'table' => 'marca', 'to' => 'id_marca'],
            ['from' => 'deshabilitado_por', 'table' => 'usuario', 'to' => 'codigo_empleado'],
        ];

        foreach ($expectedFks as $expected) {
            $matched = array_filter(
                $fks,
                static fn (array $fk): bool => $fk['from'] === $expected['from']
                    && $fk['table'] === $expected['table']
                    && $fk['to'] === $expected['to']
            );
            $this->assertNotEmpty(
                $matched,
                "Expected FK Agencias.{$expected['from']} -> {$expected['table']}.{$expected['to']}."
            );
        }
    }

    /**
     * T1-F14-04: Agencia_Marca unique constraint (Idagencia, Id_marca) and FKs verified.
     */
    public function testT1F14_04_AgenciaMarcaUniqueConstraintAndFksVerified(): void
    {
        $this->requireHU002Schema();

        $fks = $this->getForeignKeysNormalized('Agencia_Marca');
        $this->assertNotEmpty(array_filter(
            $fks,
            static fn (array $fk): bool => $fk['from'] === 'idagencia' && $fk['table'] === 'agencias' && $fk['to'] === 'idagencia'
        ), 'Expected FK Agencia_Marca.Idagencia -> Agencias.Idagencia.');
        $this->assertNotEmpty(array_filter(
            $fks,
            static fn (array $fk): bool => $fk['from'] === 'id_marca' && $fk['table'] === 'marca' && $fk['to'] === 'id_marca'
        ), 'Expected FK Agencia_Marca.Id_marca -> Marca.Id_marca.');

        $table   = $this->resolveTableName('Agencia_Marca');
        $this->assertNotNull($table);
        $escaped = str_replace('"', '""', $table);
        $indexes = $this->db->query("PRAGMA index_list(\"{$escaped}\")")->getResultArray();

        $hasCompositeUnique = false;
        foreach ($indexes as $idx) {
            $normIdx = $this->normalizeRow($idx);
            if ((int) ($normIdx['unique'] ?? 0) !== 1) {
                continue;
            }
            $idxName = str_replace('"', '""', (string) ($normIdx['name'] ?? ''));
            $cols    = $this->db->query("PRAGMA index_info(\"{$idxName}\")")->getResultArray();
            $colNames = array_map(
                fn (array $c): string => strtolower((string) ($this->normalizeRow($c)['name'] ?? '')),
                $cols
            );
            sort($colNames);
            if ($colNames === ['id_marca', 'idagencia']) {
                $hasCompositeUnique = true;
                break;
            }
        }

        $this->assertTrue($hasCompositeUnique, 'Agencia_Marca must enforce UNIQUE(Idagencia, Id_marca).');
    }

    /**
     * T1-F14-05: Zero environment-file reads or references exist in tests/e2e/ or test bootstrap.
     */
    public function testT1F14_05_ZeroEnvironmentFileReadsOrReferencesInE2eAndBootstrap(): void
    {
        $envMarker    = '.' . 'env';
        $dotEnvClass  = 'Dot' . 'Env';
        $loadEnvToken = 'load' . 'Env';

        $filesToScan = array_merge(
            glob(TESTPATH . 'e2e/*.php') ?: [],
            [
                TESTPATH . '_support/AuthTestCase.php',
                TESTPATH . '_support/Database/Seeds/TestAuthSeeder.php',
                APPPATH . 'Config/Boot/testing.php',
                SYSTEMPATH . 'Test/bootstrap.php',
            ]
        );

        foreach ($filesToScan as $filePath) {
            if (! is_file($filePath)) {
                continue;
            }
            $contents = file_get_contents($filePath);
            $this->assertIsString($contents);

            $this->assertFalse(
                stripos($contents, $envMarker) !== false,
                "Forbidden environment-file reference found in {$filePath}."
            );
            if (realpath($filePath) !== realpath(__FILE__)) {
                $this->assertFalse(
                    stripos($contents, $dotEnvClass) !== false,
                    "Forbidden environment-loader class reference found in {$filePath}."
                );
                $this->assertFalse(
                    stripos($contents, $loadEnvToken) !== false,
                    "Forbidden environment-loader call found in {$filePath}."
                );
            }
        }

        $this->assertSame('testing', ENVIRONMENT);
        $this->assertSame('SQLite3', $this->db->DBDriver);
    }

    // =========================================================================
    // FEATURE F15: Commercial Advisor Visits Data Model (T1-F15-01..T1-F15-05)
    // =========================================================================

    /**
     * T1-F15-01: Visitas table exists in Base_Datos.sql and SQLite DB with required columns.
     */
    public function testT1F15_01_VisitasTableExistsInSqlAndSqliteWithRequiredColumns(): void
    {
        $this->requireHU002Schema();

        $sql = file_get_contents(ROOTPATH . 'Base_Datos.sql');
        $this->assertIsString($sql);
        $this->assertMatchesRegularExpression('/CREATE\s+TABLE\s+Visitas\s*\(/i', $sql);

        $cols     = $this->getTableColumnsNormalized('Visitas');
        $required = ['id_visita', 'idagencia', 'codigo_asesor', 'fecha_visita', 'hora_visita', 'latitud', 'longitud', 'observaciones', 'creado_en'];

        foreach ($required as $col) {
            $this->assertContains($col, $cols, "Visitas table must include column '{$col}'.");
        }
    }

    /**
     * T1-F15-02: Visitas FK to Agencias(Idagencia) verified.
     */
    public function testT1F15_02_VisitasForeignKeyToAgenciasVerified(): void
    {
        $this->requireHU002Schema();

        $fks     = $this->getForeignKeysNormalized('Visitas');
        $matched = array_filter(
            $fks,
            static fn (array $fk): bool => $fk['from'] === 'idagencia'
                && $fk['table'] === 'agencias'
                && $fk['to'] === 'idagencia'
        );

        $this->assertNotEmpty($matched, 'Visitas.Idagencia must reference Agencias.Idagencia.');
    }

    /**
     * T1-F15-03: Visitas FK to Usuario(Codigo_Empleado) verified.
     */
    public function testT1F15_03_VisitasForeignKeyToUsuarioVerified(): void
    {
        $this->requireHU002Schema();

        $fks     = $this->getForeignKeysNormalized('Visitas');
        $matched = array_filter(
            $fks,
            static fn (array $fk): bool => $fk['from'] === 'codigo_asesor'
                && $fk['table'] === 'usuario'
                && $fk['to'] === 'codigo_empleado'
        );

        $this->assertNotEmpty($matched, 'Visitas.Codigo_Asesor must reference Usuario.Codigo_Empleado.');
    }

    /**
     * T1-F15-04: Valid visit record inserts and persists date, time, and decimal coordinates.
     */
    public function testT1F15_04_ValidVisitRecordInsertsAndPersistsDateTimeAndDecimalCoords(): void
    {
        $this->requireHU002Schema();

        $idAgencia = $this->insertAgencyRowDirectlyForSv('F15-04');
        $asesor    = $this->findUserRowByUsername('asesor_sv');

        $visitasTable = $this->resolveTableName('Visitas');
        $this->assertNotNull($visitasTable);

        $this->db->table($visitasTable)->insert([
            'Idagencia'     => $idAgencia,
            'Codigo_Asesor' => (string) $asesor['codigo_empleado'],
            'fecha_visita'  => '2026-11-18',
            'hora_visita'   => '09:30:00',
            'latitud'       => 13.69294123,
            'longitud'      => -89.21819456,
            'observaciones' => 'Visita comercial inicial',
            'creado_en'     => '2026-11-18 09:30:05',
        ]);
        $visitaId = (int) $this->db->insertID();
        $this->assertGreaterThan(0, $visitaId);

        $row = $this->db->table($visitasTable)->where('Id_visita', $visitaId)->get()->getRowArray();
        $this->assertNotNull($row);
        $norm = $this->normalizeRow($row);

        $this->assertSame($idAgencia, (int) $norm['idagencia']);
        $this->assertSame((string) $asesor['codigo_empleado'], (string) $norm['codigo_asesor']);
        $this->assertSame('2026-11-18', (string) $norm['fecha_visita']);
        $this->assertSame('09:30:00', (string) $norm['hora_visita']);
        $this->assertEqualsWithDelta(13.69294123, (float) $norm['latitud'], 0.000001);
        $this->assertEqualsWithDelta(-89.21819456, (float) $norm['longitud'], 0.000001);
        $this->assertSame('Visita comercial inicial', (string) $norm['observaciones']);
    }

    /**
     * T1-F15-05: Multiple visits to the same agency by commercial advisors persist cleanly.
     */
    public function testT1F15_05_MultipleVisitsToSameAgencyPersistCleanly(): void
    {
        $this->requireHU002Schema();

        $idAgencia = $this->insertAgencyRowDirectlyForSv('F15-05');
        $asesorSv  = $this->findUserRowByUsername('asesor_sv');
        $asesorGt  = $this->findUserRowByUsername('asesor_gt');

        $visitasTable = $this->resolveTableName('Visitas');
        $this->assertNotNull($visitasTable);

        $this->db->table($visitasTable)->insert([
            'Idagencia'     => $idAgencia,
            'Codigo_Asesor' => (string) $asesorSv['codigo_empleado'],
            'fecha_visita'  => '2026-11-18',
            'hora_visita'   => '08:15:00',
            'latitud'       => 13.69294000,
            'longitud'      => -89.21819000,
            'observaciones' => 'Primera visita matutina',
            'creado_en'     => '2026-11-18 08:15:05',
        ]);
        $firstId = (int) $this->db->insertID();

        $this->db->table($visitasTable)->insert([
            'Idagencia'     => $idAgencia,
            'Codigo_Asesor' => (string) $asesorGt['codigo_empleado'],
            'fecha_visita'  => '2026-11-18',
            'hora_visita'   => '14:45:00',
            'latitud'       => 13.69305000,
            'longitud'      => -89.21830000,
            'observaciones' => 'Segunda visita vespertina',
            'creado_en'     => '2026-11-18 14:45:05',
        ]);
        $secondId = (int) $this->db->insertID();

        $this->assertGreaterThan(0, $firstId);
        $this->assertGreaterThan($firstId, $secondId);

        $rows = $this->normalizeRows(
            $this->db->table($visitasTable)
                ->where('Idagencia', $idAgencia)
                ->orderBy('Id_visita', 'ASC')
                ->get()
                ->getResultArray()
        );

        $this->assertCount(2, $rows);
        $this->assertSame((string) $asesorSv['codigo_empleado'], (string) $rows[0]['codigo_asesor']);
        $this->assertSame((string) $asesorGt['codigo_empleado'], (string) $rows[1]['codigo_asesor']);
        $this->assertSame('08:15:00', (string) $rows[0]['hora_visita']);
        $this->assertSame('14:45:00', (string) $rows[1]['hora_visita']);
    }

    // =========================================================================
    // FEATURE F16: Main Menu "Gestión de agencias" Dropdown & Corporate Palette
    // =========================================================================

    /**
     * T1-F16-01: Supervisor home.php includes "Gestión de agencias" dropdown with Consulta, Registro, and Mapa links.
     */
    public function testT1F16_01_SupervisorHomePhpIncludesGestionDeAgenciasDropdown(): void
    {
        $this->actingAsUser('supervisor_sv');

        $res = $this->getPage('home.php');
        $res->assertStatus(200);
        $this->assertAgencyManagementMenuPresent($res->getBody());
    }

    /**
     * T1-F16-02: All 5 corporate palette colors appear in agencias, agencias/crear, and agencias/mapa.
     */
    public function testT1F16_02_CorporatePaletteColorsPresentAcrossAgencyViews(): void
    {
        $ag = $this->createAgencyViaHttp('supervisor_sv');

        $this->actingAsUser('supervisor_sv');
        foreach (['agencias', 'agencias/crear', 'agencias/mapa', 'agencias/ver/' . $ag['idagencia'], 'agencias/editar/' . $ag['idagencia']] as $route) {
            $res = $this->getPage($route);
            $res->assertStatus(200);
            $this->assertCorporatePalettePresent($res->getBody());
        }
    }

    /**
     * T1-F16-03: Navbar dropdown and logout/home controls appear consistently across all agency views.
     */
    public function testT1F16_03_NavbarDropdownAndLogoutHomeControlsConsistentAcrossAgencyViews(): void
    {
        $ag = $this->createAgencyViaHttp('supervisor_sv');

        $this->actingAsUser('supervisor_sv');
        foreach (['agencias', 'agencias/crear', 'agencias/mapa', 'agencias/ver/' . $ag['idagencia'], 'agencias/editar/' . $ag['idagencia']] as $route) {
            $res = $this->getPage($route);
            $res->assertStatus(200);
            $body = $res->getBody();

            $this->assertAgencyManagementMenuPresent($body);
            $this->assertMatchesRegularExpression('/href=["\'][^"\']*logout[^"\']*["\']/i', $body, "Logout link missing on {$route}.");
            $this->assertMatchesRegularExpression('/href=["\'][^"\']*home(?:\.php)?[^"\']*["\']/i', $body, "Home link missing on {$route}.");
        }
    }

    /**
     * T1-F16-04: Existing home.php session/profile/attribution assertions remain intact.
     */
    public function testT1F16_04_ExistingHomePhpSessionProfileAttributionAssertionsIntact(): void
    {
        $this->loginAsSupervisorSv();

        $res = $this->getPage('home.php');
        $res->assertStatus(200);
        $body = $res->getBody();

        $this->assertMatchesRegularExpression('/(¡?inicio de sesi[oó]n exitoso!?|bienvenido|sesi[oó]n iniciada)/i', $body);
        $this->assertMatchesRegularExpression('/(estado de la sesi[oó]n:\s*activa|sesi[oó]n\s*activa)/i', $body);
        $res->assertSee((string) ($this->activeSession['codigo_empleado'] ?? ''));
        $res->assertSee((string) ($this->activeSession['nombre'] ?? ''));
        $res->assertSee((string) ($this->activeSession['usuario'] ?? ''));
        $this->assertMatchesRegularExpression('/(rol|rol asignado):?\s*supervisor/i', $body);
        $this->assertMatchesRegularExpression('/(pa[ií]s|pa[ií]s asignado):?\s*el salvador/i', $body);
        $this->assertMatchesRegularExpression('/(diseñad[oa]|desarrollad[oa]|equipo de desarrollo|autores)/i', $body);
    }

    /**
     * T1-F16-05: Responsive <meta name="viewport"...> tag is present on all agency views.
     */
    public function testT1F16_05_ResponsiveViewportMetaTagPresentOnAllAgencyViews(): void
    {
        $ag = $this->createAgencyViaHttp('supervisor_sv');

        $this->actingAsUser('supervisor_sv');
        foreach (['agencias', 'agencias/crear', 'agencias/mapa', 'agencias/ver/' . $ag['idagencia'], 'agencias/editar/' . $ag['idagencia']] as $route) {
            $res = $this->getPage($route);
            $res->assertStatus(200);
            $this->assertMatchesRegularExpression(
                '/<meta[^>]+name=["\']viewport["\'][^>]+content=["\'][^"\']*width=device-width/i',
                $res->getBody(),
                "Viewport meta tag missing on {$route}."
            );
        }
    }

    // =========================================================================
    // Private Test Data Helpers (Strictly Opaque-Box)
    // =========================================================================

    private function insertAgencyRowDirectlyForSv(string $suffix): int
    {
        $chain    = $this->getGeoChainForPais('El Salvador');
        $brandIds = $this->getDefaultBrandIds(1);
        $table    = $this->resolveTableName('Agencias');
        $this->assertNotNull($table, 'Agencias table must exist.');

        $this->db->table($table)->insert([
            'nombre_agencia'  => 'Agencia Visita E2E ' . $suffix . ' ' . uniqid(),
            'direccion'       => 'Alameda Roosevelt #200, San Salvador',
            'Id_pais'         => $chain['id_pais'],
            'Id_departamento' => $chain['id_departamento'],
            'Id_municipio'    => $chain['id_municipio'],
            'Id_distrito'     => $chain['id_distrito'],
            'latitud'         => 13.69294000,
            'longitud'        => -89.21819000,
            'Id_marca'        => $brandIds[0],
            'activo'          => 1,
            'creado_en'       => '2026-11-18 08:00:00',
            'actualizado_en'  => '2026-11-18 08:00:00',
        ]);

        $id = (int) $this->db->insertID();
        $this->assertGreaterThan(0, $id);

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function findUserRowByUsername(string $username): array
    {
        $table = $this->resolveTableName('Usuario');
        $this->assertNotNull($table, 'Usuario table must exist.');

        $row = $this->db->table($table)->where('Usuario', $username)->get()->getRowArray();
        $this->assertNotNull($row, "Seeded user '{$username}' not found in Usuario table.");

        return $this->normalizeRow($row);
    }
}
