<?php

declare(strict_types=1);

namespace Tests\E2E;

require_once __DIR__ . '/E2ETestCase.php';

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\TestResponse;

/**
 * Tier 2 E2E Boundary, Corner, Negative, and Security Cases (80 tests: T2-F01-01 .. T2-F16-05).
 *
 * Opaque-box, requirement-driven suite targeting public HTTP endpoints, rendered HTML/JSON
 * responses, and observable SQLite :memory: schema/FK integrity against Base_Datos.sql.
 */
final class Tier2BoundaryCasesTest extends E2ETestCase
{
    // =========================================================================
    // Private Test Helpers
    // =========================================================================

    /**
     * @return array<string, mixed>
     */
    private function findUserByUsername(string $username): array
    {
        $table = $this->resolveTableName('Usuario');
        $this->assertNotNull($table, 'Usuario table must exist.');

        $row = $this->db->table($table)
            ->where('Usuario', $username)
            ->get()
            ->getRowArray();

        $this->assertNotNull($row, "User '{$username}' not found in Usuario table.");

        return $this->normalizeRow($row);
    }

    private function nonExistentAgencyId(): int
    {
        return $this->getMaxAgencyId() + 99999;
    }

    private function countTableRows(string $preferredTable): int
    {
        $table = $this->resolveTableName($preferredTable);
        $this->assertNotNull($table, "Table '{$preferredTable}' does not exist.");

        return (int) $this->db->table($table)->countAllResults();
    }

    /**
     * @param array<string, mixed> $overrides
     * @param list<int>            $marcaIds
     */
    private function insertRawAgency(array $overrides = [], array $marcaIds = []): int
    {
        $agenciasTable = $this->resolveTableName('Agencias');
        $this->assertNotNull($agenciasTable, 'Agencias table must exist.');

        $chain = $this->getGeoChainForPais('El Salvador');
        if ($marcaIds === []) {
            $marcaIds = $this->getDefaultBrandIds(1);
        }

        $data = array_merge([
            'nombre_agencia'         => 'Agencia Raw ' . str_replace('.', '', uniqid('', true)),
            'direccion'              => 'Direccion Raw #100',
            'Id_pais'                => $chain['id_pais'],
            'Id_departamento'        => $chain['id_departamento'],
            'Id_municipio'           => $chain['id_municipio'],
            'Id_distrito'            => $chain['id_distrito'],
            'latitud'                => 13.69294000,
            'longitud'               => -89.21819000,
            'Id_marca'               => $marcaIds[0] ?? null,
            'activo'                 => 1,
            'motivo_deshabilitacion' => null,
            'deshabilitado_en'       => null,
            'deshabilitado_por'      => null,
            'creado_en'              => '2026-11-18 10:00:00',
            'actualizado_en'         => '2026-11-18 10:00:00',
        ], $overrides);

        $this->db->table($agenciasTable)->insert($data);
        $idAgencia = (int) $this->db->insertID();
        $this->assertGreaterThan(0, $idAgencia, 'Failed to insert raw agency row.');

        $amTable = $this->resolveTableName('Agencia_Marca');
        $this->assertNotNull($amTable, 'Agencia_Marca table must exist.');
        foreach ($marcaIds as $idMarca) {
            $this->db->table($amTable)->insert([
                'Idagencia' => $idAgencia,
                'Id_marca'  => $idMarca,
            ]);
        }

        return $idAgencia;
    }

    private function insertSupervisorForCountry(string $username, string $codigoEmpleado, string $nombre, int $idPais): void
    {
        $usuarioTable = $this->resolveTableName('Usuario');
        $this->assertNotNull($usuarioTable, 'Usuario table must exist.');

        $this->db->table($usuarioTable)->insert([
            'Codigo_Empleado'   => $codigoEmpleado,
            'Nombre'            => $nombre,
            'Usuario'           => $username,
            'Contrasena'        => password_hash('Password123!', PASSWORD_BCRYPT),
            'Id_Pais'           => $idPais,
            'telefono'          => '555-9900',
            'rol'               => 'Supervisor',
            'intentos_fallidos' => 0,
            'bloqueado'         => 0,
            'ultimo_fallo'      => null,
            'ultimo_login'      => null,
            'creado_en'         => '2026-11-18 10:00:00',
            'actualizado_en'    => '2026-11-18 10:00:00',
        ]);
    }

    private function assertFormRejectedWithoutMutation(TestResponse $response, int $expectedAgencyCount, int $expectedPivotCount, int $expectedAuditCount, string $context): void
    {
        $status = $response->response()->getStatusCode();
        $this->assertTrue(
            in_array($status, [200, 302, 303, 400, 422], true),
            "{$context}: expected rejection status in [200, 302, 303, 400, 422], got {$status}."
        );
        $this->assertSame($expectedAgencyCount, $this->countAgencies(), "{$context}: Agencias count must remain unchanged.");
        $this->assertSame($expectedPivotCount, $this->countTableRows('Agencia_Marca'), "{$context}: Agencia_Marca count must remain unchanged.");
        $this->assertSame($expectedAuditCount, $this->countTableRows('Auditoria_Agencias'), "{$context}: Auditoria_Agencias count must remain unchanged.");
    }

    private function assertOperationalMapViewRendered(string $html): void
    {
        $this->assertStringContainsStringIgnoringCase('leaflet', $html, 'Expected Leaflet asset/script reference in operational map view.');
        $this->assertMatchesRegularExpression('/openstreetmap|tile\.openstreetmap\.org/i', $html, 'Expected OpenStreetMap reference in operational map view.');
        $this->assertStringNotContainsStringIgnoringCase('Whoops', $html);
        $this->assertStringNotContainsStringIgnoringCase('Fatal error', $html);
    }

    /**
     * @param array<string, mixed> $decodedJson
     * @return list<int>
     */
    private function extractGeoApiAgencyIds(array $decodedJson): array
    {
        $agencias = $decodedJson['agencias'] ?? null;
        $this->assertIsArray($agencias, 'Expected agencias array in geolocation JSON.');

        return array_values(array_map(
            static fn (array $item): int => (int) ($item['id'] ?? 0),
            $agencias
        ));
    }

    // =========================================================================
    // F01 — Supervisor-Only Agency Access (T2-F01-01 .. T2-F01-05)
    // =========================================================================

    public function testT2F0101_UnauthenticatedWebAgencyRoutesRedirectToLogin(): void
    {
        $created     = $this->createAgencyViaHttp('supervisor_sv');
        $id          = (int) $created['idagencia'];
        $beforeCount = $this->countAgencies();

        $this->actingAsGuest();

        $getRoutes = [
            'agencias',
            'agencias/crear',
            'agencias/mapa',
            "agencias/ver/{$id}",
            "agencias/editar/{$id}",
        ];

        foreach ($getRoutes as $uri) {
            $res = $this->getPage($uri);
            $res->assertStatus(302);
            $this->assertTrue($res->isRedirect(), "Expected redirect for guest GET {$uri}.");
            $this->assertMatchesRegularExpression('/login/i', (string) $res->getRedirectUrl());
        }

        $resCreate = $this->postForm('agencias/guardar', $this->buildValidAgencyPayload('El Salvador'));
        $resCreate->assertStatus(302);
        $this->assertTrue($resCreate->isRedirect());
        $this->assertMatchesRegularExpression('/login/i', (string) $resCreate->getRedirectUrl());

        $resDisable = $this->postForm("agencias/deshabilitar/{$id}", ['motivo_deshabilitacion' => 'Guest attempt']);
        $resDisable->assertStatus(302);
        $this->assertTrue($resDisable->isRedirect());
        $this->assertMatchesRegularExpression('/login/i', (string) $resDisable->getRedirectUrl());

        $this->assertSame($beforeCount, $this->countAgencies());
        $after = $this->findAgencyById($id);
        $this->assertNotNull($after);
        $this->assertSame(1, (int) $after['activo']);
    }

    public function testT2F0102_UnauthenticatedApiGeolocationReturns401Json(): void
    {
        $this->actingAsGuest();

        $res = $this->getJson('api/agencias/geolocalizacion');
        $res->assertStatus(401);

        $json = $this->decodeJsonResponse($res);
        $this->assertSame('error', $json['status'] ?? null);
        $this->assertSame('unauthenticated', $json['error'] ?? null);
        $this->assertArrayNotHasKey('agencias', $json);
    }

    public function testT2F0103_AdministradorDenied403OnAgencyWebAndApiRoutes(): void
    {
        $created      = $this->createAgencyViaHttp('supervisor_sv');
        $id           = (int) $created['idagencia'];
        $beforeCount  = $this->countAgencies();
        $beforeAudits = count($this->getAuditLogsForAgency($id));

        $this->actingAsUser('admin_sv');

        foreach (['agencias', 'agencias/crear', 'agencias/mapa', "agencias/ver/{$id}", "agencias/editar/{$id}"] as $uri) {
            $res = $this->getPage($uri);
            $res->assertStatus(403);
        }

        $this->postForm('agencias/guardar', $this->buildValidAgencyPayload('El Salvador'))->assertStatus(403);
        $this->postForm("agencias/actualizar/{$id}", $this->buildValidAgencyPayload('El Salvador'))->assertStatus(403);
        $this->postForm("agencias/deshabilitar/{$id}", ['motivo_deshabilitacion' => 'Admin attempt'])->assertStatus(403);

        $apiRes = $this->getJson('api/agencias/geolocalizacion');
        $apiRes->assertStatus(403);
        $json = $this->decodeJsonResponse($apiRes);
        $this->assertSame('error', $json['status'] ?? null);
        $this->assertSame('forbidden', $json['error'] ?? null);
        $this->assertArrayNotHasKey('agencias', $json);

        $this->assertSame($beforeCount, $this->countAgencies());
        $this->assertSame(1, (int) ($this->findAgencyById($id)['activo'] ?? -1));
        $this->assertCount($beforeAudits, $this->getAuditLogsForAgency($id));
    }

    public function testT2F0104_AsesorDenied403OnAgencyWebAndApiRoutes(): void
    {
        $created      = $this->createAgencyViaHttp('supervisor_sv');
        $id           = (int) $created['idagencia'];
        $beforeCount  = $this->countAgencies();
        $beforeAudits = count($this->getAuditLogsForAgency($id));

        $this->actingAsUser('asesor_sv');

        foreach (['agencias', 'agencias/crear', 'agencias/mapa', "agencias/ver/{$id}", "agencias/editar/{$id}"] as $uri) {
            $res = $this->getPage($uri);
            $res->assertStatus(403);
        }

        $this->postForm('agencias/guardar', $this->buildValidAgencyPayload('El Salvador'))->assertStatus(403);
        $this->postForm("agencias/actualizar/{$id}", $this->buildValidAgencyPayload('El Salvador'))->assertStatus(403);
        $this->postForm("agencias/deshabilitar/{$id}", ['motivo_deshabilitacion' => 'Asesor attempt'])->assertStatus(403);

        $apiRes = $this->getJson('api/agencias/geolocalizacion');
        $apiRes->assertStatus(403);
        $json = $this->decodeJsonResponse($apiRes);
        $this->assertSame('error', $json['status'] ?? null);
        $this->assertSame('forbidden', $json['error'] ?? null);
        $this->assertArrayNotHasKey('agencias', $json);

        $this->assertSame($beforeCount, $this->countAgencies());
        $this->assertSame(1, (int) ($this->findAgencyById($id)['activo'] ?? -1));
        $this->assertCount($beforeAudits, $this->getAuditLogsForAgency($id));
    }

    public function testT2F0105_TamperedOrUnknownRoleInSessionDenied403(): void
    {
        $sv          = $this->findPaisByName('El Salvador');
        $idPais      = (int) $sv['id_pais'];
        $paisNombre  = (string) $sv['pais'];
        $beforeCount = $this->countAgencies();
        $payload     = $this->buildValidAgencyPayload('El Salvador');

        $vectors = [
            ['rol' => 'SuperAdmin', 'overrides' => []],
            ['rol' => 'supervisor', 'overrides' => []],
            ['rol' => '', 'overrides' => []],
            ['rol' => 'Supervisor', 'overrides' => ['id_pais' => 0]],
        ];

        foreach ($vectors as $vector) {
            $this->withAuthenticatedSession(
                rol: $vector['rol'],
                idPais: $idPais,
                paisNombre: $paisNombre,
                overrides: $vector['overrides']
            );

            $this->getPage('agencias')->assertStatus(403);
            $this->postForm('agencias/guardar', $payload)->assertStatus(403);

            $apiRes = $this->getJson('api/agencias/geolocalizacion');
            $apiRes->assertStatus(403);
            $json = $this->decodeJsonResponse($apiRes);
            $this->assertSame('error', $json['status'] ?? null);
            $this->assertSame('forbidden', $json['error'] ?? null);
        }

        $this->assertSame($beforeCount, $this->countAgencies());
    }

    // =========================================================================
    // F02 — Country-Scoped Agency Isolation (T2-F02-01 .. T2-F02-05)
    // =========================================================================

    public function testT2F0201_SvSupervisorViewingGtAgencyDetailReturns403(): void
    {
        $gtAg = $this->createAgencyViaHttp('supervisor_gt', ['nombre_agencia' => 'Agencia GT Aislada 01']);
        $idGt = (int) $gtAg['idagencia'];

        $this->actingAsUser('supervisor_sv');
        $res = $this->getPage("agencias/ver/{$idGt}");
        $res->assertStatus(403);
        $this->assertStringNotContainsString('Agencia GT Aislada 01', (string) $res->getBody());
    }

    public function testT2F0202_SvSupervisorEditAndUpdateGtAgencyReturns403AndPreservesDb(): void
    {
        $gtAg         = $this->createAgencyViaHttp('supervisor_gt', ['nombre_agencia' => 'Agencia GT Protegida 02']);
        $idGt         = (int) $gtAg['idagencia'];
        $beforeBrands = $this->getAgencyBrandIds($idGt);
        $beforeAudits = count($this->getAuditLogsForAgency($idGt));

        $this->actingAsUser('supervisor_sv');

        $getRes = $this->getPage("agencias/editar/{$idGt}");
        $getRes->assertStatus(403);
        $this->assertStringNotContainsString('Agencia GT Protegida 02', (string) $getRes->getBody());

        $this->postForm(
            "agencias/actualizar/{$idGt}",
            $this->buildValidAgencyPayload('El Salvador', ['nombre_agencia' => 'Secuestro SV'])
        )->assertStatus(403);

        $this->postForm(
            "agencias/actualizar/{$idGt}",
            $this->buildValidAgencyPayload('Guatemala', ['nombre_agencia' => 'Secuestro GT'])
        )->assertStatus(403);

        $after = $this->findAgencyById($idGt);
        $this->assertNotNull($after);
        $this->assertSame((string) $gtAg['nombre_agencia'], (string) $after['nombre_agencia']);
        $this->assertSame((string) $gtAg['direccion'], (string) $after['direccion']);
        $this->assertSame((int) $gtAg['id_pais'], (int) $after['id_pais']);
        $this->assertEqualsWithDelta((float) $gtAg['latitud'], (float) $after['latitud'], 1e-6);
        $this->assertEqualsWithDelta((float) $gtAg['longitud'], (float) $after['longitud'], 1e-6);
        $this->assertSame($beforeBrands, $this->getAgencyBrandIds($idGt));
        $this->assertCount($beforeAudits, $this->getAuditLogsForAgency($idGt));
    }

    public function testT2F0203_SvSupervisorDisableGtAgencyReturns403AndKeepsActive(): void
    {
        $gtAg         = $this->createAgencyViaHttp('supervisor_gt');
        $idGt         = (int) $gtAg['idagencia'];
        $beforeAudits = count($this->getAuditLogsForAgency($idGt));

        $this->actingAsUser('supervisor_sv');
        $res = $this->postForm("agencias/deshabilitar/{$idGt}", [
            'motivo_deshabilitacion' => 'Intento cruzado SV',
        ]);
        $res->assertStatus(403);

        $after = $this->findAgencyById($idGt);
        $this->assertNotNull($after);
        $this->assertSame(1, (int) $after['activo']);
        $this->assertNull($after['motivo_deshabilitacion'] ?? null);
        $this->assertNull($after['deshabilitado_en'] ?? null);
        $this->assertNull($after['deshabilitado_por'] ?? null);
        $this->assertSame([], $this->getAuditLogsForAgency($idGt, 'DESHABILITAR'));
        $this->assertCount($beforeAudits, $this->getAuditLogsForAgency($idGt));
    }

    public function testT2F0204_SvSupervisorCreateWithForeignCountryReturns403AndZeroInserts(): void
    {
        $this->actingAsUser('supervisor_sv');
        $beforeCount = $this->countAgencies();
        $gtPayload   = $this->buildValidAgencyPayload('Guatemala', ['nombre_agencia' => 'Intento GT por SV']);

        $this->postForm('agencias/guardar', $gtPayload)->assertStatus(403);
        $this->postForm('agencias', $gtPayload)->assertStatus(403);

        $this->assertSame($beforeCount, $this->countAgencies());
        $this->assertNull($this->findAgencyByName('Intento GT por SV'));
    }

    public function testT2F0205_SvSupervisorGeolocationApiWithForeignCountryParamReturns403(): void
    {
        $this->createAgencyViaHttp('supervisor_gt', ['nombre_agencia' => 'GT Geo Privada']);
        $gt = $this->findPaisByName('Guatemala');
        $sv = $this->findPaisByName('El Salvador');

        $this->actingAsUser('supervisor_sv');

        $queries = [
            ['id_pais' => (int) $gt['id_pais']],
            ['pais' => (string) $gt['pais']],
            ['id_pais' => (int) $sv['id_pais'], 'pais' => (string) $gt['pais']],
        ];

        foreach ($queries as $query) {
            $res = $this->getJson('api/agencias/geolocalizacion', $query);
            $res->assertStatus(403);

            $json = $this->decodeJsonResponse($res);
            $this->assertSame('error', $json['status'] ?? null);
            $this->assertSame('forbidden', $json['error'] ?? null);
            $this->assertArrayNotHasKey('agencias', $json);
            $this->assertStringNotContainsString('GT Geo Privada', (string) $res->getBody());
        }
    }

    // =========================================================================
    // F03 — Supervisor Prohibition on User/Role Admin (T2-F03-01 .. T2-F03-05)
    // =========================================================================

    public function testT2F0301_SupervisorGetEditUserRouteReturns403(): void
    {
        $target = $this->findUserByUsername('asesor_sv');
        $code   = (string) $target['codigo_empleado'];

        $this->actingAsUser('supervisor_sv');

        $resWeb = $this->getPage("usuarios/editar/{$code}");
        $resWeb->assertStatus(403);
        $webBody = (string) $resWeb->getBody();
        $this->assertStringNotContainsString('<form', $webBody);
        $this->assertStringNotContainsString('$2y$', $webBody);

        $resApi = $this->getJson("api/usuarios/editar/{$code}");
        $resApi->assertStatus(403);
        $json = $this->decodeJsonResponse($resApi);
        $this->assertSame('error', $json['status'] ?? null);
        $this->assertSame('forbidden', $json['error'] ?? null);
    }

    public function testT2F0302_SupervisorPostDisableUserReturns403AndLeavesUserUntouched(): void
    {
        $before = $this->findUserByUsername('asesor_sv');
        $code   = (string) $before['codigo_empleado'];

        $this->actingAsUser('supervisor_sv');

        $resWeb = $this->postForm("usuarios/deshabilitar/{$code}", ['motivo' => 'Intento supervisor']);
        $resWeb->assertStatus(403);

        $resApi = $this->postForm(
            "api/usuarios/deshabilitar/{$code}",
            ['motivo' => 'Intento supervisor'],
            ['Accept' => 'application/json']
        );
        $resApi->assertStatus(403);
        $json = $this->decodeJsonResponse($resApi);
        $this->assertSame('error', $json['status'] ?? null);
        $this->assertSame('forbidden', $json['error'] ?? null);

        $after = $this->findUserByUsername('asesor_sv');
        $this->assertSame((int) $before['bloqueado'], (int) $after['bloqueado']);
        $this->assertSame((string) $before['rol'], (string) $after['rol']);
        $this->assertSame((int) $before['id_pais'], (int) $after['id_pais']);
        $this->assertSame((string) $before['nombre'], (string) $after['nombre']);
        $this->assertSame((string) $before['codigo_empleado'], (string) $after['codigo_empleado']);
    }

    public function testT2F0303_SupervisorPostRolesAssignReturns403(): void
    {
        $before = $this->findUserByUsername('asesor_sv');
        $code   = (string) $before['codigo_empleado'];

        $this->actingAsUser('supervisor_sv');

        $resWeb = $this->postForm('roles/asignar', [
            'codigo_empleado' => $code,
            'rol'             => 'Administrador',
        ]);
        $resWeb->assertStatus(403);

        $resApi = $this->postForm(
            'api/roles/asignar',
            ['codigo_empleado' => $code, 'rol' => 'Administrador'],
            ['Accept' => 'application/json']
        );
        $resApi->assertStatus(403);
        $json = $this->decodeJsonResponse($resApi);
        $this->assertSame('error', $json['status'] ?? null);
        $this->assertSame('forbidden', $json['error'] ?? null);

        $after = $this->findUserByUsername('asesor_sv');
        $this->assertSame('Asesor', (string) $after['rol']);
    }

    public function testT2F0304_SupervisorPostPermisosUpdateReturns403(): void
    {
        $this->actingAsUser('supervisor_sv');

        $resWeb = $this->postForm('permisos/actualizar', [
            'rol'     => 'Supervisor',
            'permiso' => 'gestion_usuarios',
        ]);
        $resWeb->assertStatus(403);

        $resApi = $this->postForm(
            'api/permisos/actualizar',
            ['rol' => 'Supervisor', 'permiso' => 'gestion_usuarios'],
            ['Accept' => 'application/json']
        );
        $resApi->assertStatus(403);
        $json = $this->decodeJsonResponse($resApi);
        $this->assertSame('error', $json['status'] ?? null);
        $this->assertSame('forbidden', $json['error'] ?? null);
    }

    public function testT2F0305_SupervisorUserRoleAdminTrailingSlashAndCaseVariantsDenied(): void
    {
        $this->actingAsUser('supervisor_sv');

        $webPaths = [
            'usuarios/',
            'USUARIOS',
            'Usuarios/Crear',
            'roles/',
            'ROLES',
            'permisos/',
            'PERMISOS/1',
        ];

        foreach ($webPaths as $uri) {
            $res    = $this->getPage($uri);
            $status = $res->response()->getStatusCode();
            $this->assertContains($status, [403, 404], "Expected 403 or 404 for web GET {$uri}, got {$status}.");
        }

        $apiPaths = [
            'api/usuarios/',
            'API/Usuarios',
            'API/Roles/1',
            'API/Permisos',
        ];

        foreach ($apiPaths as $uri) {
            $res    = $this->getJson($uri);
            $status = $res->response()->getStatusCode();
            $this->assertContains($status, [403, 404], "Expected 403 or 404 for API GET {$uri}, got {$status}.");
        }
    }

    // =========================================================================
    // F04 — Agency Registration (Create) (T2-F04-01 .. T2-F04-05)
    // =========================================================================

    public function testT2F0401_MissingNombreOrDireccionRejected(): void
    {
        $this->actingAsUser('supervisor_sv');

        $beforeAgencies = $this->countAgencies();
        $beforePivots   = $this->countTableRows('Agencia_Marca');
        $beforeAudits   = $this->countTableRows('Auditoria_Agencias');

        $payloadEmptyNombre      = $this->buildValidAgencyPayload('El Salvador', ['nombre_agencia' => '']);
        $payloadWhitespaceNombre = $this->buildValidAgencyPayload('El Salvador', ['nombre_agencia' => '   ']);
        $payloadEmptyDir         = $this->buildValidAgencyPayload('El Salvador', ['direccion' => '']);
        $payloadMissingDir       = $this->buildValidAgencyPayload('El Salvador');
        unset($payloadMissingDir['direccion']);

        foreach (
            [
                'empty nombre_agencia'      => $payloadEmptyNombre,
                'whitespace nombre_agencia' => $payloadWhitespaceNombre,
                'empty direccion'           => $payloadEmptyDir,
                'missing direccion'         => $payloadMissingDir,
            ] as $label => $payload
        ) {
            $res = $this->postForm('agencias/guardar', $payload);
            $this->assertFormRejectedWithoutMutation($res, $beforeAgencies, $beforePivots, $beforeAudits, $label);
        }
    }

    public function testT2F0402_EmptyMarcasArrayRejected(): void
    {
        $this->actingAsUser('supervisor_sv');

        $beforeAgencies = $this->countAgencies();
        $beforePivots   = $this->countTableRows('Agencia_Marca');
        $beforeAudits   = $this->countTableRows('Auditoria_Agencias');

        $payloadEmpty = $this->buildValidAgencyPayload('El Salvador', ['marcas' => []]);
        $resEmpty     = $this->postForm('agencias/guardar', $payloadEmpty);
        $this->assertFormRejectedWithoutMutation($resEmpty, $beforeAgencies, $beforePivots, $beforeAudits, 'empty marcas[]');

        $payloadUnset = $this->buildValidAgencyPayload('El Salvador');
        unset($payloadUnset['marcas']);
        $resUnset = $this->postForm('agencias/guardar', $payloadUnset);
        $this->assertFormRejectedWithoutMutation($resUnset, $beforeAgencies, $beforePivots, $beforeAudits, 'missing marcas');
    }

    public function testT2F0403_OutOfRangeCoordinatesRejected(): void
    {
        $this->actingAsUser('supervisor_sv');

        $beforeAgencies = $this->countAgencies();
        $beforePivots   = $this->countTableRows('Agencia_Marca');
        $beforeAudits   = $this->countTableRows('Auditoria_Agencias');

        $cases = [
            ['latitud' => '90.000001', 'longitud' => '-89.21819'],
            ['latitud' => '-90.1', 'longitud' => '-89.21819'],
            ['latitud' => '13.69294', 'longitud' => '180.000001'],
            ['latitud' => '13.69294', 'longitud' => '-180.1'],
        ];

        foreach ($cases as $coords) {
            $payload = $this->buildValidAgencyPayload('El Salvador', $coords);
            $res     = $this->postForm('agencias/guardar', $payload);
            $this->assertFormRejectedWithoutMutation(
                $res,
                $beforeAgencies,
                $beforePivots,
                $beforeAudits,
                "out-of-range ({$coords['latitud']}, {$coords['longitud']})"
            );
        }
    }

    public function testT2F0404_NonExistentOrDuplicateBrandIdsHandledSafely(): void
    {
        $this->actingAsUser('supervisor_sv');

        $beforeAgencies = $this->countAgencies();
        $beforePivots   = $this->countTableRows('Agencia_Marca');
        $beforeAudits   = $this->countTableRows('Auditoria_Agencias');

        // Subcase A: non-existent brand ID rejected atomically without 500
        $badBrandPayload = $this->buildValidAgencyPayload('El Salvador', [
            'nombre_agencia' => 'Agencia Marca Inexistente',
            'marcas'         => [999999],
        ]);
        $resBad = $this->postForm('agencias/guardar', $badBrandPayload);
        $this->assertFormRejectedWithoutMutation($resBad, $beforeAgencies, $beforePivots, $beforeAudits, 'non-existent brand ID');

        // Subcase B: duplicate brand IDs either deduplicate cleanly or reject without 500/UNIQUE failure
        $hondaId     = (int) $this->findMarcaByName('Honda')['id_marca'];
        $dupName     = 'Agencia Marca Duplicada ' . str_replace('.', '', uniqid('', true));
        $dupPayload  = $this->buildValidAgencyPayload('El Salvador', [
            'nombre_agencia' => $dupName,
            'marcas'         => [$hondaId, $hondaId],
        ]);

        $resDup = $this->postForm('agencias/guardar', $dupPayload);
        $status = $resDup->response()->getStatusCode();
        $this->assertTrue(
            in_array($status, [200, 201, 302, 303, 400, 422], true),
            "Duplicate brand IDs must not trigger 500 or 404, got {$status}."
        );

        $created = $this->findAgencyByName($dupName);
        if ($created !== null) {
            $this->assertSame([$hondaId], $this->getAgencyBrandIds((int) $created['idagencia']));
        } else {
            $this->assertSame($beforeAgencies, $this->countAgencies());
        }
    }

    public function testT2F0405_XssPayloadInNombreAndDireccionEscapedInViews(): void
    {
        $this->actingAsUser('supervisor_sv');
        $actorCode = (string) $this->activeSession['codigo_empleado'];

        $xssNombre = 'Agencia <script>alert("xss-name")</script> Norte';
        $xssDir    = 'Calle <img src=x onerror=alert("xss-dir")> #45';

        $created = $this->createAgencyViaHttp('supervisor_sv', [
            'nombre_agencia' => $xssNombre,
            'direccion'      => $xssDir,
        ]);
        $id = (int) $created['idagencia'];

        $this->assertAuditRecorded($id, 'CREAR', $actorCode);

        foreach (['agencias', "agencias/ver/{$id}", "agencias/editar/{$id}"] as $uri) {
            $res = $this->getPage($uri);
            $res->assertStatus(200);
            $body = (string) $res->getBody();
            $this->assertStringNotContainsString('<script>alert("xss-name")</script>', $body);
            $this->assertStringNotContainsString('<img src=x onerror=alert("xss-dir")>', $body);
        }
    }

    // =========================================================================
    // F05 — Agency Query / Consultation List & Detail (T2-F05-01 .. T2-F05-05)
    // =========================================================================

    public function testT2F0501_ViewNonExistentAgencyIdReturns404(): void
    {
        $this->actingAsUser('supervisor_sv');
        $missingId = $this->nonExistentAgencyId();

        $res = $this->getPage("agencias/ver/{$missingId}");
        $res->assertStatus(404);
    }

    public function testT2F0502_SearchQuerySqlInjectionPayloadSafelyParameterized(): void
    {
        $this->createAgencyViaHttp('supervisor_sv', ['nombre_agencia' => 'Agencia Segura Norte']);
        $this->createAgencyViaHttp('supervisor_sv', ['nombre_agencia' => 'Agencia Segura Sur']);
        $this->createAgencyViaHttp('supervisor_gt', ['nombre_agencia' => 'Agencia GT Oculta']);

        $beforeCount = $this->countAgencies();
        $this->actingAsUser('supervisor_sv');

        $payloads = [
            "' OR 1=1 --",
            "'; DROP TABLE Agencias; --",
            '%_',
        ];

        foreach ($payloads as $q) {
            $res = $this->getPage('agencias', ['q' => $q]);
            $res->assertStatus(200);
            $body = (string) $res->getBody();
            $this->assertStringNotContainsString('Agencia Segura Norte', $body);
            $this->assertStringNotContainsString('Agencia Segura Sur', $body);
            $this->assertStringNotContainsString('Agencia GT Oculta', $body);
        }

        $this->assertTrue($this->tableExistsNormalized('Agencias'));
        $this->assertSame($beforeCount, $this->countAgencies());
    }

    public function testT2F0503_ZeroMatchFilterRendersEmptyStateCleanly(): void
    {
        $this->createAgencyViaHttp('supervisor_sv', ['nombre_agencia' => 'Agencia Presente SV']);
        $this->actingAsUser('supervisor_sv');

        $res = $this->getPage('agencias', ['q' => 'TERMINO_SIN_COINCIDENCIAS_999']);
        $res->assertStatus(200);

        $body = (string) $res->getBody();
        $this->assertStringNotContainsString('Agencia Presente SV', $body);
        $this->assertMatchesRegularExpression('/(no se encontraron|sin resultados|no hay agencias|ninguna agencia)/i', $body);
        $this->assertAgencyManagementMenuPresent($body);
    }

    public function testT2F0504_InvalidEstadoFilterHandledGracefullyWithout500(): void
    {
        $this->createAgencyViaHttp('supervisor_sv', ['nombre_agencia' => 'Agencia Estado Activa']);
        $disabled = $this->createAgencyViaHttp('supervisor_sv', ['nombre_agencia' => 'Agencia Estado Inactiva']);
        $this->postForm("agencias/deshabilitar/{$disabled['idagencia']}", [
            'motivo_deshabilitacion' => 'Prueba estado invalido',
        ]);

        $this->actingAsUser('supervisor_sv');
        $res    = $this->getPage('agencias', ['estado' => 'estado_invalido_xyz']);
        $status = $res->response()->getStatusCode();

        $this->assertTrue(
            in_array($status, [200, 400, 422], true),
            "Invalid estado filter should return 200, 400, or 422 (not 404/500), got {$status}."
        );

        if ($status === 200) {
            $body = (string) $res->getBody();
            $this->assertStringContainsStringIgnoringCase('CrediTrack', $body);
        }
    }

    public function testT2F0505_InactiveAgencyDetailAndListDisplayDisableReasonAndActor(): void
    {
        $ag        = $this->createAgencyViaHttp('supervisor_sv', ['nombre_agencia' => 'Agencia Auditada Inactiva']);
        $id        = (int) $ag['idagencia'];
        $actorCode = (string) $this->activeSession['codigo_empleado'];
        $actorName = (string) ($this->activeSession['nombre'] ?? '');
        $motivo    = 'Cierre operativo por reubicación';

        $disableRes = $this->postForm("agencias/deshabilitar/{$id}", [
            'motivo_deshabilitacion' => $motivo,
        ]);
        $this->assertContains($disableRes->response()->getStatusCode(), [200, 302, 303]);

        $resList = $this->getPage('agencias', ['estado' => 'inactivas']);
        $resList->assertStatus(200);
        $listBody = (string) $resList->getBody();
        $this->assertStringContainsString('Agencia Auditada Inactiva', $listBody);
        $this->assertMatchesRegularExpression('/inactiv|deshabilitad/i', $listBody);
        $this->assertStringContainsString($motivo, $listBody);

        $resDetail = $this->getPage("agencias/ver/{$id}");
        $resDetail->assertStatus(200);
        $detailBody = (string) $resDetail->getBody();
        $this->assertStringContainsString('Agencia Auditada Inactiva', $detailBody);
        $this->assertMatchesRegularExpression('/inactiv|deshabilitad/i', $detailBody);
        $this->assertStringContainsString($motivo, $detailBody);
        $this->assertTrue(
            str_contains($detailBody, $actorCode) || ($actorName !== '' && str_contains($detailBody, $actorName)),
            'Expected detail view to display disable actor code or name.'
        );
    }

    // =========================================================================
    // F06 — Agency Update (Modify) (T2-F06-01 .. T2-F06-05)
    // =========================================================================

    public function testT2F0601_UpdateNonExistentAgencyReturns404(): void
    {
        $this->actingAsUser('supervisor_sv');
        $missingId    = $this->nonExistentAgencyId();
        $beforeCounts = [
            $this->countAgencies(),
            $this->countTableRows('Agencia_Marca'),
            $this->countTableRows('Auditoria_Agencias'),
        ];

        $this->getPage("agencias/editar/{$missingId}")->assertStatus(404);
        $this->postForm("agencias/actualizar/{$missingId}", $this->buildValidAgencyPayload('El Salvador'))->assertStatus(404);

        $this->assertSame($beforeCounts, [
            $this->countAgencies(),
            $this->countTableRows('Agencia_Marca'),
            $this->countTableRows('Auditoria_Agencias'),
        ]);
    }

    public function testT2F0602_UpdateRemovingAllMarcasRejectedPreservesOriginal(): void
    {
        $brandIds = $this->getDefaultBrandIds(2);
        $created  = $this->createAgencyViaHttp('supervisor_sv', [
            'nombre_agencia' => 'Agencia Marcas Protegidas',
            'marcas'         => $brandIds,
        ]);
        $id = (int) $created['idagencia'];

        $payloadEmpty = $this->buildValidAgencyPayload('El Salvador', [
            'nombre_agencia' => 'Nombre No Guardado Empty',
            'marcas'         => [],
        ]);
        $resEmpty = $this->postForm("agencias/actualizar/{$id}", $payloadEmpty);
        $this->assertContains($resEmpty->response()->getStatusCode(), [200, 302, 303, 400, 422]);

        $payloadOmitted = $this->buildValidAgencyPayload('El Salvador', [
            'nombre_agencia' => 'Nombre No Guardado Omitted',
        ]);
        unset($payloadOmitted['marcas']);
        $resOmitted = $this->postForm("agencias/actualizar/{$id}", $payloadOmitted);
        $this->assertContains($resOmitted->response()->getStatusCode(), [200, 302, 303, 400, 422]);

        $after = $this->findAgencyById($id);
        $this->assertNotNull($after);
        $this->assertSame('Agencia Marcas Protegidas', (string) $after['nombre_agencia']);
        $this->assertSame($brandIds, $this->getAgencyBrandIds($id));
        $this->assertSame([], $this->getAuditLogsForAgency($id, 'ACTUALIZAR'));
    }

    public function testT2F0603_UpdateWithInvalidCoordinatesRejectedPreservesOriginal(): void
    {
        $created = $this->createAgencyViaHttp('supervisor_sv', [
            'latitud'  => '13.69294000',
            'longitud' => '-89.21819000',
        ]);
        $id = (int) $created['idagencia'];

        $badCases = [
            ['latitud' => '95.0', 'longitud' => '-89.21819'],
            ['latitud' => '13.69294', 'longitud' => 'abc'],
        ];

        foreach ($badCases as $coords) {
            $payload = $this->buildValidAgencyPayload('El Salvador', $coords);
            $res     = $this->postForm("agencias/actualizar/{$id}", $payload);
            $this->assertContains($res->response()->getStatusCode(), [200, 302, 303, 400, 422]);
        }

        $after = $this->findAgencyById($id);
        $this->assertNotNull($after);
        $this->assertEqualsWithDelta(13.69294, (float) $after['latitud'], 1e-6);
        $this->assertEqualsWithDelta(-89.21819, (float) $after['longitud'], 1e-6);
        $this->assertSame([], $this->getAuditLogsForAgency($id, 'ACTUALIZAR'));
    }

    public function testT2F0604_UpdateWithMismatchedParentChildGeoIdsRejected(): void
    {
        $created = $this->createAgencyViaHttp('supervisor_sv');
        $id      = (int) $created['idagencia'];

        $sv         = $this->findPaisByName('El Salvador');
        $idPaisSv   = (int) $sv['id_pais'];
        $ssDepto    = $this->findDepartamentoByName('San Salvador', $idPaisSv);
        $llDepto    = $this->findDepartamentoByName('La Libertad', $idPaisSv);
        $llSurMuni  = $this->findMunicipioByName('La Libertad Sur', (int) $llDepto['id_departamento']);
        $santaTecla = $this->findDistritoByName('Santa Tecla', (int) $llSurMuni['id_municipio']);

        $badPayload = $this->buildValidAgencyPayload('El Salvador', [
            'id_departamento' => (int) $ssDepto['id_departamento'],
            'id_municipio'    => (int) $llSurMuni['id_municipio'],
            'id_distrito'     => (int) $santaTecla['id_distrito'],
        ]);

        $res = $this->postForm("agencias/actualizar/{$id}", $badPayload);
        $this->assertContains($res->response()->getStatusCode(), [200, 302, 303, 400, 422]);

        $after = $this->findAgencyById($id);
        $this->assertNotNull($after);
        $this->assertSame((int) $created['id_departamento'], (int) $after['id_departamento']);
        $this->assertSame((int) $created['id_municipio'], (int) $after['id_municipio']);
        $this->assertSame((int) $created['id_distrito'], (int) $after['id_distrito']);
        $this->assertSame([], $this->getAuditLogsForAgency($id, 'ACTUALIZAR'));
    }

    public function testT2F0605_UpdateAttemptingCountryChangeReturns403AndIsImmutable(): void
    {
        $created      = $this->createAgencyViaHttp('supervisor_sv');
        $id           = (int) $created['idagencia'];
        $beforeBrands = $this->getAgencyBrandIds($id);
        $gtChain      = $this->getGeoChainForPais('Guatemala');

        $payload = $this->buildValidAgencyPayload('El Salvador', [
            'id_pais'         => $gtChain['id_pais'],
            'id_departamento' => $gtChain['id_departamento'],
            'id_municipio'    => $gtChain['id_municipio'],
            'id_distrito'     => '',
        ]);

        $res = $this->postForm("agencias/actualizar/{$id}", $payload);
        $res->assertStatus(403);

        $after = $this->findAgencyById($id);
        $this->assertNotNull($after);
        $this->assertSame((int) $created['id_pais'], (int) $after['id_pais']);
        $this->assertSame((int) $created['id_departamento'], (int) $after['id_departamento']);
        $this->assertSame((int) $created['id_municipio'], (int) $after['id_municipio']);
        $this->assertSame((int) $created['id_distrito'], (int) $after['id_distrito']);
        $this->assertSame($beforeBrands, $this->getAgencyBrandIds($id));
        $this->assertSame([], $this->getAuditLogsForAgency($id, 'ACTUALIZAR'));
    }

    // =========================================================================
    // F07 — Logical (Soft) Disable of Agency (T2-F07-01 .. T2-F07-05)
    // =========================================================================

    public function testT2F0701_MissingOrWhitespaceDisableReasonRejectedAndAgencyRemainsActive(): void
    {
        $created      = $this->createAgencyViaHttp('supervisor_sv');
        $id           = (int) $created['idagencia'];
        $beforeAudits = count($this->getAuditLogsForAgency($id));

        $attempts = [
            [],
            ['motivo_deshabilitacion' => ''],
            ['motivo_deshabilitacion' => "   \t\n  "],
        ];

        foreach ($attempts as $payload) {
            $res    = $this->postForm("agencias/deshabilitar/{$id}", $payload);
            $status = $res->response()->getStatusCode();
            $this->assertContains($status, [302, 303, 400, 422], "Expected rejection status in [302, 303, 400, 422], got {$status}.");
        }

        $after = $this->findAgencyById($id);
        $this->assertNotNull($after);
        $this->assertSame(1, (int) $after['activo']);
        $this->assertNull($after['motivo_deshabilitacion'] ?? null);
        $this->assertNull($after['deshabilitado_en'] ?? null);
        $this->assertNull($after['deshabilitado_por'] ?? null);
        $this->assertSame([], $this->getAuditLogsForAgency($id, 'DESHABILITAR'));
        $this->assertCount($beforeAudits, $this->getAuditLogsForAgency($id));
    }

    public function testT2F0702_DisableNonExistentAgencyIdReturns404(): void
    {
        $this->actingAsUser('supervisor_sv');
        $missingId   = $this->nonExistentAgencyId();
        $beforeCount = $this->countAgencies();

        $res = $this->postForm("agencias/deshabilitar/{$missingId}", [
            'motivo_deshabilitacion' => 'Motivo valido',
        ]);
        $res->assertStatus(404);

        $this->assertSame($beforeCount, $this->countAgencies());
        $this->assertSame([], $this->getAuditLogsForAgency($missingId));
    }

    public function testT2F0703_DisablingAlreadyDisabledAgencyHandledCleanlyWithoutCorruptingAudit(): void
    {
        $created   = $this->createAgencyViaHttp('supervisor_sv');
        $id        = (int) $created['idagencia'];
        $actorCode = (string) $this->activeSession['codigo_empleado'];
        $motivo1   = 'Primer cierre definitivo';

        $res1 = $this->postForm("agencias/deshabilitar/{$id}", ['motivo_deshabilitacion' => $motivo1]);
        $this->assertContains($res1->response()->getStatusCode(), [200, 302, 303]);
        $this->assertAgencySoftDisabled($id, $actorCode, $motivo1);

        $res2    = $this->postForm("agencias/deshabilitar/{$id}", ['motivo_deshabilitacion' => 'Segundo intento']);
        $status2 = $res2->response()->getStatusCode();
        $this->assertContains(
            $status2,
            [200, 302, 303, 400, 409, 422],
            "Second disable on already-disabled agency returned unexpected status {$status2}."
        );

        $after = $this->findAgencyById($id);
        $this->assertNotNull($after);
        $this->assertSame(0, (int) $after['activo']);
        $this->assertSame($motivo1, (string) $after['motivo_deshabilitacion']);
        $this->assertSame($actorCode, (string) $after['deshabilitado_por']);
        $this->assertCount(1, $this->getAuditLogsForAgency($id, 'DESHABILITAR'));
    }

    public function testT2F0704_CrossCountryDisableAttemptReturns403AndPreservesAgency(): void
    {
        $svAg         = $this->createAgencyViaHttp('supervisor_sv');
        $idSv         = (int) $svAg['idagencia'];
        $beforeAudits = count($this->getAuditLogsForAgency($idSv));

        $this->actingAsUser('supervisor_gt');
        $res = $this->postForm("agencias/deshabilitar/{$idSv}", [
            'motivo_deshabilitacion' => 'Intento GT sobre SV',
        ]);
        $res->assertStatus(403);

        $after = $this->findAgencyById($idSv);
        $this->assertNotNull($after);
        $this->assertSame(1, (int) $after['activo']);
        $this->assertNull($after['motivo_deshabilitacion'] ?? null);
        $this->assertNull($after['deshabilitado_en'] ?? null);
        $this->assertNull($after['deshabilitado_por'] ?? null);
        $this->assertSame([], $this->getAuditLogsForAgency($idSv, 'DESHABILITAR'));
        $this->assertCount($beforeAudits, $this->getAuditLogsForAgency($idSv));
    }

    public function testT2F0705_PhysicalHttpDeleteDisallowedOnAgencyRoutes(): void
    {
        $created     = $this->createAgencyViaHttp('supervisor_sv');
        $id          = (int) $created['idagencia'];
        $beforeCount = $this->countAgencies();

        foreach (["agencias/{$id}", "agencias/eliminar/{$id}", "api/agencias/{$id}"] as $deleteUri) {
            $this->resetResponseServices();
            $this->withSession($this->activeSession);
            $res    = $this->delete($deleteUri);
            $status = $res->response()->getStatusCode();
            $this->assertContains($status, [404, 405], "Expected 404 or 405 for DELETE {$deleteUri}, got {$status}.");
        }

        $this->assertSame($beforeCount, $this->countAgencies());
        $row = $this->findAgencyById($id);
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row['activo']);
    }

    // =========================================================================
    // F08 — Comprehensive Agency Audit Logging (T2-F08-01 .. T2-F08-05)
    // =========================================================================

    public function testT2F0801_FailedCreateWritesZeroCrearAuditRows(): void
    {
        $this->actingAsUser('supervisor_sv');

        $beforeAgencies = $this->countAgencies();
        $beforePivots   = $this->countTableRows('Agencia_Marca');
        $beforeAudits   = $this->countTableRows('Auditoria_Agencias');

        $badPayload = $this->buildValidAgencyPayload('El Salvador', [
            'nombre_agencia' => '',
            'latitud'        => '999',
            'marcas'         => [],
        ]);

        $res = $this->postForm('agencias/guardar', $badPayload);
        $this->assertFormRejectedWithoutMutation($res, $beforeAgencies, $beforePivots, $beforeAudits, 'failed create');
    }

    public function testT2F0802_FailedUpdateWritesZeroActualizarAuditRows(): void
    {
        $created = $this->createAgencyViaHttp('supervisor_sv');
        $id      = (int) $created['idagencia'];

        $this->assertCount(1, $this->getAuditLogsForAgency($id));

        $badPayload = $this->buildValidAgencyPayload('El Salvador', [
            'direccion' => '',
            'longitud'  => '-181.5',
        ]);
        $res = $this->postForm("agencias/actualizar/{$id}", $badPayload);
        $this->assertContains($res->response()->getStatusCode(), [200, 302, 303, 400, 422]);

        $this->assertSame([], $this->getAuditLogsForAgency($id, 'ACTUALIZAR'));
        $this->assertCount(1, $this->getAuditLogsForAgency($id));
    }

    public function testT2F0803_FailedDisableWritesZeroDeshabilitarAuditRows(): void
    {
        $created = $this->createAgencyViaHttp('supervisor_sv');
        $id      = (int) $created['idagencia'];

        $this->assertCount(1, $this->getAuditLogsForAgency($id));

        foreach (['   ', ''] as $badMotivo) {
            $res = $this->postForm("agencias/deshabilitar/{$id}", [
                'motivo_deshabilitacion' => $badMotivo,
            ]);
            $this->assertContains($res->response()->getStatusCode(), [302, 303, 400, 422]);
        }

        $this->assertSame(1, (int) ($this->findAgencyById($id)['activo'] ?? -1));
        $this->assertSame([], $this->getAuditLogsForAgency($id, 'DESHABILITAR'));
        $this->assertCount(1, $this->getAuditLogsForAgency($id));
    }

    public function testT2F0804_AuditJsonSnapshotsParseCleanlyAndReflectFieldAndBrandDiffs(): void
    {
        $hondaId  = (int) $this->findMarcaByName('Honda')['id_marca'];
        $yamahaId = (int) $this->findMarcaByName('Yamaha')['id_marca'];
        $suzukiId = (int) $this->findMarcaByName('Suzuki')['id_marca'];

        $created = $this->createAgencyViaHttp('supervisor_sv', [
            'nombre_agencia' => 'Agencia Original SV',
            'direccion'      => 'Direccion Inicial #101',
            'marcas'         => [$hondaId],
        ]);
        $id        = (int) $created['idagencia'];
        $actorCode = (string) $this->activeSession['codigo_empleado'];

        $updatePayload = $this->buildValidAgencyPayload('El Salvador', [
            'nombre_agencia' => 'Agencia Renovada SV',
            'direccion'      => 'Nueva Direccion #202',
            'marcas'         => [$yamahaId, $suzukiId],
        ]);

        $res = $this->postForm("agencias/actualizar/{$id}", $updatePayload);
        $this->assertContains($res->response()->getStatusCode(), [200, 302, 303]);

        $audit = $this->assertAuditRecorded($id, 'ACTUALIZAR', $actorCode);

        $beforeRaw  = (string) ($audit['datos_anteriores'] ?? '');
        $afterRaw   = (string) ($audit['datos_nuevos'] ?? '');
        $beforeJson = json_decode($beforeRaw, true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'datos_anteriores must be valid JSON.');
        $this->assertIsArray($beforeJson);

        $afterJson = json_decode($afterRaw, true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'datos_nuevos must be valid JSON.');
        $this->assertIsArray($afterJson);

        $this->assertStringContainsString('Agencia Original SV', $beforeRaw);
        $this->assertTrue(
            str_contains($beforeRaw, 'Honda') || str_contains($beforeRaw, (string) $hondaId),
            'datos_anteriores must reflect previous brand Honda.'
        );

        $this->assertStringContainsString('Agencia Renovada SV', $afterRaw);
        $this->assertStringContainsString('Nueva Direccion #202', $afterRaw);
        $this->assertTrue(
            str_contains($afterRaw, 'Yamaha') || str_contains($afterRaw, (string) $yamahaId),
            'datos_nuevos must reflect updated brand Yamaha.'
        );
        $this->assertTrue(
            str_contains($afterRaw, 'Suzuki') || str_contains($afterRaw, (string) $suzukiId),
            'datos_nuevos must reflect updated brand Suzuki.'
        );
    }

    public function testT2F0805_AuditActorUsesAuthenticatedSessionAndIgnoresSpoofedFields(): void
    {
        $spoofedUser = $this->findUserByUsername('admin_sv');
        $spoofedCode = (string) $spoofedUser['codigo_empleado'];

        $this->actingAsUser('supervisor_sv');
        $realActor = (string) $this->activeSession['codigo_empleado'];
        $this->assertNotSame($spoofedCode, $realActor);

        $name          = 'Agencia Spoof Check ' . str_replace('.', '', uniqid('', true));
        $createPayload = $this->buildValidAgencyPayload('El Salvador', [
            'nombre_agencia'  => $name,
            'codigo_empleado' => $spoofedCode,
            'Codigo_Empleado' => $spoofedCode,
        ]);

        $resCreate = $this->postForm('agencias/guardar', $createPayload);
        $this->assertContains($resCreate->response()->getStatusCode(), [200, 201, 302, 303]);

        $created = $this->findAgencyByName($name);
        $this->assertNotNull($created);
        $id = (int) $created['idagencia'];

        $updatePayload = $this->buildValidAgencyPayload('El Salvador', [
            'nombre_agencia'  => $name . ' Editada',
            'codigo_empleado' => $spoofedCode,
            'Codigo_Empleado' => $spoofedCode,
        ]);
        $resUpdate = $this->postForm("agencias/actualizar/{$id}", $updatePayload);
        $this->assertContains($resUpdate->response()->getStatusCode(), [200, 302, 303]);

        $resDisable = $this->postForm("agencias/deshabilitar/{$id}", [
            'motivo_deshabilitacion' => 'Deshabilitada con spoof',
            'codigo_empleado'        => $spoofedCode,
            'Codigo_Empleado'        => $spoofedCode,
            'deshabilitado_por'      => $spoofedCode,
        ]);
        $this->assertContains($resDisable->response()->getStatusCode(), [200, 302, 303]);

        $this->assertAuditRecorded($id, 'CREAR', $realActor);
        $this->assertAuditRecorded($id, 'ACTUALIZAR', $realActor);
        $this->assertAuditRecorded($id, 'DESHABILITAR', $realActor, 'Deshabilitada con spoof');

        $agency = $this->findAgencyById($id);
        $this->assertNotNull($agency);
        $this->assertSame($realActor, (string) $agency['deshabilitado_por']);
    }

    // =========================================================================
    // F09 — Dynamic Cascading Geographic Selectors & API (T2-F09-01 .. T2-F09-05)
    // =========================================================================

    public function testT2F0901_UnauthenticatedGeoApiRequestsReturn401Json(): void
    {
        $svChain = $this->getGeoChainForPais('El Salvador');
        $this->actingAsGuest();

        $endpoints = [
            ['api/geo/paises', []],
            ['api/geo/departamentos', []],
            ['api/geo/municipios', ['id_departamento' => $svChain['id_departamento']]],
            ['api/geo/distritos', ['id_municipio' => $svChain['id_municipio']]],
        ];

        foreach ($endpoints as [$uri, $query]) {
            $res = $this->getJson($uri, $query);
            $res->assertStatus(401);
            $json = $this->decodeJsonResponse($res);
            $this->assertSame('error', $json['status'] ?? null);
            $this->assertSame('unauthenticated', $json['error'] ?? null);
        }
    }

    public function testT2F0902_NonSupervisorGeoApiRequestsReturn403Json(): void
    {
        $svChain = $this->getGeoChainForPais('El Salvador');

        $endpoints = [
            ['api/geo/paises', []],
            ['api/geo/departamentos', []],
            ['api/geo/municipios', ['id_departamento' => $svChain['id_departamento']]],
            ['api/geo/distritos', ['id_municipio' => $svChain['id_municipio']]],
        ];

        foreach (['admin_sv', 'asesor_sv'] as $username) {
            $this->actingAsUser($username);
            foreach ($endpoints as [$uri, $query]) {
                $res = $this->getJson($uri, $query);
                $res->assertStatus(403);
                $json = $this->decodeJsonResponse($res);
                $this->assertSame('error', $json['status'] ?? null);
                $this->assertSame('forbidden', $json['error'] ?? null);
            }
        }
    }

    public function testT2F0903_CrossCountryGeoApiQueriesDeniedOrScopedWithoutForeignLeak(): void
    {
        $gtChain = $this->getGeoChainForPais('Guatemala');
        $hnChain = $this->getGeoChainForPais('Honduras');

        $this->actingAsUser('supervisor_sv');

        // 1. Explicit foreign id_pais must return 403 JSON
        $resDeptos = $this->getJson('api/geo/departamentos', ['id_pais' => $gtChain['id_pais']]);
        $resDeptos->assertStatus(403);
        $jsonDeptos = $this->decodeJsonResponse($resDeptos);
        $this->assertSame('error', $jsonDeptos['status'] ?? null);
        $this->assertSame('forbidden', $jsonDeptos['error'] ?? null);

        // 2. Foreign id_departamento must return 403 JSON or scoped-empty 200 without GT leakage
        $resMunis   = $this->getJson('api/geo/municipios', ['id_departamento' => $gtChain['id_departamento']]);
        $muniStatus = $resMunis->response()->getStatusCode();
        $this->assertContains($muniStatus, [200, 403], "Expected 403 or empty 200 for foreign id_departamento, got {$muniStatus}.");
        $muniJson = $this->decodeJsonResponse($resMunis);
        if ($muniStatus === 200) {
            $this->assertSame([], $muniJson['municipios'] ?? null);
        } else {
            $this->assertSame('forbidden', $muniJson['error'] ?? null);
        }
        $this->assertStringNotContainsString((string) $gtChain['municipio'], (string) $resMunis->getBody());

        // 3. Foreign id_municipio must return 403 JSON or scoped-empty 200 without HN leakage
        $resDists   = $this->getJson('api/geo/distritos', ['id_municipio' => $hnChain['id_municipio']]);
        $distStatus = $resDists->response()->getStatusCode();
        $this->assertContains($distStatus, [200, 403], "Expected 403 or empty 200 for foreign id_municipio, got {$distStatus}.");
        $distJson = $this->decodeJsonResponse($resDists);
        if ($distStatus === 200) {
            $this->assertSame([], $distJson['distritos'] ?? null);
        } else {
            $this->assertSame('forbidden', $distJson['error'] ?? null);
        }
        if ($hnChain['distrito'] !== null) {
            $this->assertStringNotContainsString((string) $hnChain['distrito'], (string) $resDists->getBody());
        }
    }

    public function testT2F0904_MissingOrNonNumericGeoParentParamsHandledCleanlyWithout500(): void
    {
        $this->actingAsUser('supervisor_sv');

        $calls = [
            ['api/geo/municipios', []],
            ['api/geo/municipios', ['id_departamento' => 'abc']],
            ['api/geo/distritos', []],
            ['api/geo/distritos', ['id_municipio' => '-5']],
        ];

        foreach ($calls as [$uri, $query]) {
            $res    = $this->getJson($uri, $query);
            $status = $res->response()->getStatusCode();
            $this->assertContains(
                $status,
                [200, 400, 422],
                "Expected 200, 400, or 422 for {$uri} with bad/missing param, got {$status}."
            );
            $this->decodeJsonResponse($res);
        }
    }

    public function testT2F0905_CreateRejectsMismatchedGeoHierarchyAtomically(): void
    {
        $this->actingAsUser('supervisor_sv');

        $beforeAgencies = $this->countAgencies();
        $beforePivots   = $this->countTableRows('Agencia_Marca');
        $beforeAudits   = $this->countTableRows('Auditoria_Agencias');

        $sv         = $this->findPaisByName('El Salvador');
        $idPaisSv   = (int) $sv['id_pais'];
        $ssDepto    = $this->findDepartamentoByName('San Salvador', $idPaisSv);
        $llDepto    = $this->findDepartamentoByName('La Libertad', $idPaisSv);
        $llSurMuni  = $this->findMunicipioByName('La Libertad Sur', (int) $llDepto['id_departamento']);
        $santaTecla = $this->findDistritoByName('Santa Tecla', (int) $llSurMuni['id_municipio']);

        $gt         = $this->findPaisByName('Guatemala');
        $gtDepto    = $this->findDepartamentoByName('Guatemala', (int) $gt['id_pais']);
        $mixcoMuni  = $this->findMunicipioByName('Mixco', (int) $gtDepto['id_departamento']);

        // Case A: SV San Salvador depto + La Libertad Sur muni + Santa Tecla distrito
        $mismatchedSv = $this->buildValidAgencyPayload('El Salvador', [
            'id_departamento' => (int) $ssDepto['id_departamento'],
            'id_municipio'    => (int) $llSurMuni['id_municipio'],
            'id_distrito'     => (int) $santaTecla['id_distrito'],
        ]);
        $resA = $this->postForm('agencias/guardar', $mismatchedSv);
        $this->assertFormRejectedWithoutMutation($resA, $beforeAgencies, $beforePivots, $beforeAudits, 'mismatched SV hierarchy');

        // Case B: SV San Salvador depto + foreign GT Mixco muni
        $mismatchedForeign = $this->buildValidAgencyPayload('El Salvador', [
            'id_departamento' => (int) $ssDepto['id_departamento'],
            'id_municipio'    => (int) $mixcoMuni['id_municipio'],
        ]);
        $resB = $this->postForm('agencias/guardar', $mismatchedForeign);
        $this->assertFormRejectedWithoutMutation($resB, $beforeAgencies, $beforePivots, $beforeAudits, 'foreign muni under SV depto');
    }

    // =========================================================================
    // F10 — Guatemala Optional District & Country Extensibility (T2-F10-01 .. T2-F10-05)
    // =========================================================================

    public function testT2F1001_ElSalvadorSupervisorOmittingDistritoRejected(): void
    {
        $this->actingAsUser('supervisor_sv');

        $beforeAgencies = $this->countAgencies();
        $beforePivots   = $this->countTableRows('Agencia_Marca');
        $beforeAudits   = $this->countTableRows('Auditoria_Agencias');

        $payloadOmitted = $this->buildValidAgencyPayload('El Salvador');
        unset($payloadOmitted['id_distrito']);
        $resOmitted = $this->postForm('agencias/guardar', $payloadOmitted);
        $this->assertFormRejectedWithoutMutation($resOmitted, $beforeAgencies, $beforePivots, $beforeAudits, 'SV omitted id_distrito');

        $payloadEmpty = $this->buildValidAgencyPayload('El Salvador', ['id_distrito' => '']);
        $resEmpty     = $this->postForm('agencias/guardar', $payloadEmpty);
        $this->assertFormRejectedWithoutMutation($resEmpty, $beforeAgencies, $beforePivots, $beforeAudits, 'SV empty id_distrito');
    }

    public function testT2F1002_GuatemalaEmptyStringDistritoStoresNullWithoutFkError(): void
    {
        $this->actingAsUser('supervisor_gt');
        $actorCode = (string) $this->activeSession['codigo_empleado'];
        $name      = 'Agencia GT String Vacio ' . str_replace('.', '', uniqid('', true));

        $payload = $this->buildValidAgencyPayload('Guatemala', [
            'nombre_agencia' => $name,
            'id_distrito'    => '',
        ]);

        $res = $this->postForm('agencias/guardar', $payload);
        $this->assertContains($res->response()->getStatusCode(), [200, 201, 302, 303]);

        $created = $this->findAgencyByName($name);
        $this->assertNotNull($created);
        $this->assertNull($created['id_distrito']);
        $this->assertNotEmpty($this->getAgencyBrandIds((int) $created['idagencia']));
        $this->assertAuditRecorded((int) $created['idagencia'], 'CREAR', $actorCode);
    }

    public function testT2F1003_GuatemalaInvalidOrForeignDistritoRejected(): void
    {
        $svChain = $this->getGeoChainForPais('El Salvador');

        $this->actingAsUser('supervisor_gt');

        $beforeAgencies = $this->countAgencies();
        $beforePivots   = $this->countTableRows('Agencia_Marca');
        $beforeAudits   = $this->countTableRows('Auditoria_Agencias');

        $cases = [
            'foreign SV distrito'  => $svChain['id_distrito'],
            'non-existent ID'      => 999999,
            'non-numeric string'   => 'bogus',
        ];

        foreach ($cases as $label => $badDistrito) {
            $payload = $this->buildValidAgencyPayload('Guatemala', ['id_distrito' => $badDistrito]);
            $res     = $this->postForm('agencias/guardar', $payload);
            $this->assertFormRejectedWithoutMutation($res, $beforeAgencies, $beforePivots, $beforeAudits, "GT {$label}");
        }
    }

    public function testT2F1004_ExtensibleCountryObeysRequiereDistritoFlagDynamically(): void
    {
        $crChain = $this->seedExtensibleCountryWithDivisions('Costa Rica', 0, 'San José', 'Central', null);
        $niChain = $this->seedExtensibleCountryWithDivisions('Nicaragua', 1, 'Managua', 'Managua', 'Distrito I');

        $this->insertSupervisorForCountry('supervisor_cr', 'EMP-CR-901', 'Supervisor CR', $crChain['id_pais']);
        $this->insertSupervisorForCountry('supervisor_ni', 'EMP-NI-901', 'Supervisor NI', $niChain['id_pais']);

        // 1. Costa Rica (requiere_distrito = 0): omitting id_distrito succeeds
        $this->actingAsUser('supervisor_cr');
        $crName    = 'Agencia CR Extensible ' . str_replace('.', '', uniqid('', true));
        $crPayload = [
            'nombre_agencia'  => $crName,
            'direccion'       => 'Av. Central #10, San Jose',
            'id_pais'         => $crChain['id_pais'],
            'id_departamento' => $crChain['id_departamento'],
            'id_municipio'    => $crChain['id_municipio'],
            'latitud'         => '9.92806900',
            'longitud'        => '-84.09072500',
            'marcas'          => $this->getDefaultBrandIds(1),
        ];

        $resCr = $this->postForm('agencias/guardar', $crPayload);
        $this->assertContains($resCr->response()->getStatusCode(), [200, 201, 302, 303]);
        $crAgency = $this->findAgencyByName($crName);
        $this->assertNotNull($crAgency);
        $this->assertNull($crAgency['id_distrito']);

        // 2. Nicaragua (requiere_distrito = 1): omitting id_distrito fails; providing valid distrito succeeds
        $this->actingAsUser('supervisor_ni');
        $beforeAgencies = $this->countAgencies();
        $beforePivots   = $this->countTableRows('Agencia_Marca');
        $beforeAudits   = $this->countTableRows('Auditoria_Agencias');

        $niName          = 'Agencia NI Extensible ' . str_replace('.', '', uniqid('', true));
        $niMissingDist   = [
            'nombre_agencia'  => $niName,
            'direccion'       => 'Carretera Masaya Km 4',
            'id_pais'         => $niChain['id_pais'],
            'id_departamento' => $niChain['id_departamento'],
            'id_municipio'    => $niChain['id_municipio'],
            'latitud'         => '12.11499300',
            'longitud'        => '-86.23617400',
            'marcas'          => $this->getDefaultBrandIds(1),
        ];

        $resNiFail = $this->postForm('agencias/guardar', $niMissingDist);
        $this->assertFormRejectedWithoutMutation($resNiFail, $beforeAgencies, $beforePivots, $beforeAudits, 'Nicaragua requires distrito');

        $niValidPayload                = $niMissingDist;
        $niValidPayload['id_distrito'] = $niChain['id_distrito'];

        $resNiOk = $this->postForm('agencias/guardar', $niValidPayload);
        $this->assertContains($resNiOk->response()->getStatusCode(), [200, 201, 302, 303]);
        $niAgency = $this->findAgencyByName($niName);
        $this->assertNotNull($niAgency);
        $this->assertSame($niChain['id_distrito'], (int) $niAgency['id_distrito']);
    }

    public function testT2F1005_GuatemalaNullDistritoHandledGracefullyInDetailAndGeoApi(): void
    {
        $gtAg = $this->createAgencyViaHttp('supervisor_gt', [
            'nombre_agencia' => 'Agencia GT Sin Distrito Detalle',
            'id_distrito'    => null,
        ]);
        $idGt = (int) $gtAg['idagencia'];
        $this->assertNull($gtAg['id_distrito']);

        $detailRes = $this->getPage("agencias/ver/{$idGt}");
        $detailRes->assertStatus(200);
        $detailBody = (string) $detailRes->getBody();
        $this->assertStringContainsString('Agencia GT Sin Distrito Detalle', $detailBody);
        $this->assertStringNotContainsStringIgnoringCase('Whoops', $detailBody);
        $this->assertDoesNotMatchRegularExpression('/Undefined\s+(?:array key|variable|index|property)|Fatal error/i', $detailBody);

        $apiRes = $this->getJson('api/agencias/geolocalizacion');
        $json   = $this->assertJsonOk($apiRes);

        $matched = null;
        foreach ($json['agencias'] as $item) {
            if ((int) ($item['id'] ?? 0) === $idGt) {
                $matched = $item;
                break;
            }
        }

        $this->assertNotNull($matched, 'Expected GT agency with null distrito in geolocation API response.');
        $this->assertArrayHasKey('distrito', $matched);
        $this->assertNull($matched['distrito']);
    }

    // =========================================================================
    // F11 — Interactive Operational Map (Leaflet + OSM) (T2-F11-01 .. T2-F11-05)
    // =========================================================================

    public function testT2F1101_SoftDisabledAgencyExcludedFromOperationalMap(): void
    {
        $active = $this->createAgencyViaHttp('supervisor_sv', [
            'nombre_agencia' => 'Agencia Activa Mapa SV',
            'latitud'        => '13.70100000',
            'longitud'       => '-89.20100000',
        ]);
        $disabled = $this->createAgencyViaHttp('supervisor_sv', [
            'nombre_agencia' => 'Agencia Deshabilitada Mapa SV',
            'latitud'        => '13.71200000',
            'longitud'       => '-89.21200000',
        ]);

        $actorCode  = (string) $this->activeSession['codigo_empleado'];
        $idDisabled = (int) $disabled['idagencia'];

        $resDisable = $this->postForm("agencias/deshabilitar/{$idDisabled}", [
            'motivo_deshabilitacion' => 'Cierre temporal',
        ]);
        $this->assertContains($resDisable->response()->getStatusCode(), [200, 302, 303]);
        $this->assertAgencySoftDisabled($idDisabled, $actorCode, 'Cierre temporal');

        $mapRes = $this->getPage('agencias/mapa');
        $mapRes->assertStatus(200);
        $mapBody = (string) $mapRes->getBody();
        $this->assertOperationalMapViewRendered($mapBody);
        $this->assertStringNotContainsString('Agencia Deshabilitada Mapa SV', $mapBody);

        $apiData = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $ids     = $this->extractGeoApiAgencyIds($apiData);
        $this->assertContains((int) $active['idagencia'], $ids);
        $this->assertNotContains($idDisabled, $ids);
    }

    public function testT2F1102_NullCoordinatesAgencyExcludedFromMap(): void
    {
        $valid = $this->createAgencyViaHttp('supervisor_sv', [
            'nombre_agencia' => 'Agencia Con Coords SV',
            'latitud'        => '13.69500000',
            'longitud'       => '-89.21500000',
        ]);
        $noCoords = $this->createAgencyViaHttp('supervisor_sv', [
            'nombre_agencia' => 'Agencia Null Coords SV',
        ]);
        $idNull = (int) $noCoords['idagencia'];

        $agenciasTable = $this->resolveTableName('Agencias');
        $this->assertNotNull($agenciasTable);
        $this->db->table($agenciasTable)
            ->where('Idagencia', $idNull)
            ->update(['latitud' => null, 'longitud' => null]);

        $mapRes = $this->getPage('agencias/mapa');
        $mapRes->assertStatus(200);
        $mapBody = (string) $mapRes->getBody();
        $this->assertOperationalMapViewRendered($mapBody);
        $this->assertStringNotContainsString('Agencia Null Coords SV', $mapBody);

        $apiData = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $ids     = $this->extractGeoApiAgencyIds($apiData);
        $this->assertContains((int) $valid['idagencia'], $ids);
        $this->assertNotContains($idNull, $ids);
    }

    public function testT2F1103_ZeroCoordinatesAgencyExcludedFromMap(): void
    {
        $valid = $this->createAgencyViaHttp('supervisor_sv', [
            'nombre_agencia' => 'Agencia Valida Control SV',
        ]);
        $zero = $this->createAgencyViaHttp('supervisor_sv', [
            'nombre_agencia' => 'Agencia Cero Cero SV',
        ]);
        $idZero = (int) $zero['idagencia'];

        $agenciasTable = $this->resolveTableName('Agencias');
        $this->assertNotNull($agenciasTable);
        $this->db->table($agenciasTable)
            ->where('Idagencia', $idZero)
            ->update(['latitud' => 0.0, 'longitud' => 0.0]);

        $mapRes = $this->getPage('agencias/mapa');
        $mapRes->assertStatus(200);
        $mapBody = (string) $mapRes->getBody();
        $this->assertOperationalMapViewRendered($mapBody);
        $this->assertStringNotContainsString('Agencia Cero Cero SV', $mapBody);

        $apiData = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $ids     = $this->extractGeoApiAgencyIds($apiData);
        $this->assertContains((int) $valid['idagencia'], $ids);
        $this->assertNotContains($idZero, $ids);
    }

    public function testT2F1104_ZeroValidAgenciesCountryRendersMapCleanly(): void
    {
        $this->actingAsUser('supervisor_hn');

        $mapRes = $this->getPage('agencias/mapa');
        $mapRes->assertStatus(200);
        $html = (string) $mapRes->getBody();
        $this->assertOperationalMapViewRendered($html);
        $this->assertCorporatePalettePresent($html);
        $this->assertStringNotContainsStringIgnoringCase('NaN', $html);

        $apiData = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $this->assertSame('Honduras', $apiData['pais'] ?? null);
        $this->assertSame(0, (int) ($apiData['total'] ?? -1));
        $this->assertSame([], $apiData['agencias'] ?? null);
    }

    public function testT2F1105_XssPayloadsEscapedInMapOutput(): void
    {
        $this->createAgencyViaHttp('supervisor_sv', [
            'nombre_agencia' => 'Agencia <script>alert("xss_map")</script> & "Moto"',
            'direccion'      => 'Calle <img src=x onerror=alert(1)> #99',
            'latitud'        => '13.69294000',
            'longitud'       => '-89.21819000',
        ]);

        $mapRes = $this->getPage('agencias/mapa');
        $mapRes->assertStatus(200);
        $html = (string) $mapRes->getBody();
        $this->assertOperationalMapViewRendered($html);
        $this->assertStringNotContainsString('<script>alert("xss_map")</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);

        $apiRes  = $this->getJson('api/agencias/geolocalizacion');
        $apiData = $this->assertJsonOk($apiRes);
        $this->assertCount(1, $apiData['agencias']);
    }

    // =========================================================================
    // F12 — Map Click-to-Set Coordinates (Create & Edit) (T2-F12-01 .. T2-F12-05)
    // =========================================================================

    public function testT2F1201_EditFormWithNullCoordsInitializesMapCleanly(): void
    {
        $agency = $this->createAgencyViaHttp('supervisor_sv');
        $id     = (int) $agency['idagencia'];

        $agenciasTable = $this->resolveTableName('Agencias');
        $this->assertNotNull($agenciasTable);
        $this->db->table($agenciasTable)
            ->where('Idagencia', $id)
            ->update(['latitud' => null, 'longitud' => null]);

        $res = $this->getPage("agencias/editar/{$id}");
        $res->assertStatus(200);
        $html = (string) $res->getBody();

        $this->assertMatchesRegularExpression('/name=["\']latitud["\']/i', $html);
        $this->assertMatchesRegularExpression('/name=["\']longitud["\']/i', $html);
        $this->assertOperationalMapViewRendered($html);
        $this->assertStringContainsString('L.map', $html);
        $this->assertDoesNotMatchRegularExpression('/setView\s*\(\s*\[\s*(?:null|NaN|undefined|,)/i', $html);
        $this->assertDoesNotMatchRegularExpression('/name=["\']latitud["\'][^>]*value=["\'](?:null|NaN)["\']/i', $html);
        $this->assertDoesNotMatchRegularExpression('/name=["\']longitud["\'][^>]*value=["\'](?:null|NaN)["\']/i', $html);
    }

    public function testT2F1202_ExtremeValidCoordinateBoundsPreservePrecision(): void
    {
        $this->actingAsUser('supervisor_sv');

        $name    = 'Agencia Polos Bounds ' . str_replace('.', '', uniqid('', true));
        $payload = $this->buildValidAgencyPayload('El Salvador', [
            'nombre_agencia' => $name,
            'latitud'        => '-90.00000000',
            'longitud'       => '-180.00000000',
        ]);

        $resCreate = $this->postForm('agencias/guardar', $payload);
        $this->assertContains($resCreate->response()->getStatusCode(), [200, 201, 302, 303]);

        $created = $this->findAgencyByName($name);
        $this->assertNotNull($created);
        $id = (int) $created['idagencia'];
        $this->assertEqualsWithDelta(-90.0, (float) $created['latitud'], 1e-8);
        $this->assertEqualsWithDelta(-180.0, (float) $created['longitud'], 1e-8);

        $updatePayload             = $payload;
        $updatePayload['latitud']  = '90.00000000';
        $updatePayload['longitud'] = '180.00000000';

        $resUpdate = $this->postForm("agencias/actualizar/{$id}", $updatePayload);
        $this->assertContains($resUpdate->response()->getStatusCode(), [200, 302, 303]);

        $updated = $this->findAgencyById($id);
        $this->assertNotNull($updated);
        $this->assertEqualsWithDelta(90.0, (float) $updated['latitud'], 1e-8);
        $this->assertEqualsWithDelta(180.0, (float) $updated['longitud'], 1e-8);

        $apiData = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
        $matched = null;
        foreach ($apiData['agencias'] as $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                $matched = $item;
                break;
            }
        }
        $this->assertNotNull($matched);
        $this->assertEqualsWithDelta(90.0, (float) $matched['latitud'], 1e-8);
        $this->assertEqualsWithDelta(180.0, (float) $matched['longitud'], 1e-8);
    }

    public function testT2F1203_NonNumericCoordinatesRejected(): void
    {
        $this->actingAsUser('supervisor_sv');

        $beforeAgencies = $this->countAgencies();
        $beforePivots   = $this->countTableRows('Agencia_Marca');
        $beforeAudits   = $this->countTableRows('Auditoria_Agencias');

        foreach (
            [
                ['latitud' => 'abc', 'longitud' => '-89.21819'],
                ['latitud' => '13.69294', 'longitud' => 'xyz'],
            ] as $badCoords
        ) {
            $res = $this->postForm('agencias/guardar', $this->buildValidAgencyPayload('El Salvador', $badCoords));
            $this->assertFormRejectedWithoutMutation($res, $beforeAgencies, $beforePivots, $beforeAudits, 'non-numeric create coords');
        }

        $agency = $this->createAgencyViaHttp('supervisor_sv', [
            'latitud'  => '13.69294000',
            'longitud' => '-89.21819000',
        ]);
        $id = (int) $agency['idagencia'];

        $resUpdate = $this->postForm(
            "agencias/actualizar/{$id}",
            $this->buildValidAgencyPayload('El Salvador', ['latitud' => 'abc', 'longitud' => 'def'])
        );
        $this->assertContains($resUpdate->response()->getStatusCode(), [200, 302, 303, 400, 422]);

        $after = $this->findAgencyById($id);
        $this->assertNotNull($after);
        $this->assertEqualsWithDelta(13.69294, (float) $after['latitud'], 1e-6);
        $this->assertEqualsWithDelta(-89.21819, (float) $after['longitud'], 1e-6);
        $this->assertSame([], $this->getAuditLogsForAgency($id, 'ACTUALIZAR'));
    }

    public function testT2F1204_PartialCoordinatesRejected(): void
    {
        $this->actingAsUser('supervisor_sv');

        $beforeAgencies = $this->countAgencies();
        $beforePivots   = $this->countTableRows('Agencia_Marca');
        $beforeAudits   = $this->countTableRows('Auditoria_Agencias');

        foreach (
            [
                ['latitud' => '13.69294000', 'longitud' => ''],
                ['latitud' => '', 'longitud' => '-89.21819000'],
            ] as $partialCoords
        ) {
            $res = $this->postForm('agencias/guardar', $this->buildValidAgencyPayload('El Salvador', $partialCoords));
            $this->assertFormRejectedWithoutMutation($res, $beforeAgencies, $beforePivots, $beforeAudits, 'partial create coords');
        }

        $agency = $this->createAgencyViaHttp('supervisor_sv', [
            'latitud'  => '13.69294000',
            'longitud' => '-89.21819000',
        ]);
        $id = (int) $agency['idagencia'];

        $resUpdate = $this->postForm(
            "agencias/actualizar/{$id}",
            $this->buildValidAgencyPayload('El Salvador', ['latitud' => '13.75000000', 'longitud' => ''])
        );
        $this->assertContains($resUpdate->response()->getStatusCode(), [200, 302, 303, 400, 422]);

        $after = $this->findAgencyById($id);
        $this->assertNotNull($after);
        $this->assertEqualsWithDelta(13.69294, (float) $after['latitud'], 1e-6);
        $this->assertEqualsWithDelta(-89.21819, (float) $after['longitud'], 1e-6);
        $this->assertSame([], $this->getAuditLogsForAgency($id, 'ACTUALIZAR'));
    }

    public function testT2F1205_ZeroZeroCoordinatesRejectedOrExcludedFromMap(): void
    {
        $this->actingAsUser('supervisor_sv');

        $zeroName = 'Agencia Zero Zero Test ' . str_replace('.', '', uniqid('', true));
        $payload  = $this->buildValidAgencyPayload('El Salvador', [
            'nombre_agencia' => $zeroName,
            'latitud'        => '0',
            'longitud'       => '0',
        ]);

        $res    = $this->postForm('agencias/guardar', $payload);
        $status = $res->response()->getStatusCode();
        $this->assertContains($status, [200, 201, 302, 303, 400, 422], "Unexpected status {$status} on (0,0) create.");

        $created = $this->findAgencyByName($zeroName);
        if ($created !== null) {
            $mapRes = $this->getPage('agencias/mapa');
            $mapRes->assertStatus(200);
            $this->assertStringNotContainsString($zeroName, (string) $mapRes->getBody());

            $apiData = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
            $this->assertNotContains((int) $created['idagencia'], $this->extractGeoApiAgencyIds($apiData));
        }

        $valid = $this->createAgencyViaHttp('supervisor_sv', [
            'nombre_agencia' => 'Agencia Valid Pre Zero Edit',
            'latitud'        => '13.69294000',
            'longitud'       => '-89.21819000',
        ]);
        $validId = (int) $valid['idagencia'];

        $resUpd    = $this->postForm("agencias/actualizar/{$validId}", $this->buildValidAgencyPayload('El Salvador', [
            'nombre_agencia' => 'Agencia Valid Pre Zero Edit',
            'latitud'        => '0.0',
            'longitud'       => '0.0',
        ]));
        $updStatus = $resUpd->response()->getStatusCode();
        $this->assertContains($updStatus, [200, 302, 303, 400, 422], "Unexpected status {$updStatus} on (0,0) update.");

        $afterValid = $this->findAgencyById($validId);
        $this->assertNotNull($afterValid);
        if (abs((float) $afterValid['latitud']) < 1e-6 && abs((float) $afterValid['longitud']) < 1e-6) {
            $mapRes2 = $this->getPage('agencias/mapa');
            $mapRes2->assertStatus(200);
            $this->assertStringNotContainsString('Agencia Valid Pre Zero Edit', (string) $mapRes2->getBody());

            $apiData2 = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));
            $this->assertNotContains($validId, $this->extractGeoApiAgencyIds($apiData2));
        } else {
            $this->assertEqualsWithDelta(13.69294, (float) $afterValid['latitud'], 1e-6);
            $this->assertEqualsWithDelta(-89.21819, (float) $afterValid['longitud'], 1e-6);
        }
    }

    // =========================================================================
    // F13 — Agency Geolocation REST/JSON API (T2-F13-01 .. T2-F13-05)
    // =========================================================================

    public function testT2F1301_UnauthenticatedGeolocationApiReturns401Json(): void
    {
        $this->actingAsGuest();

        $res = $this->getJson('api/agencias/geolocalizacion');
        $res->assertStatus(401);
        $json = $this->decodeJsonResponse($res);
        $this->assertSame('error', $json['status'] ?? null);
        $this->assertSame('unauthenticated', $json['error'] ?? null);
    }

    public function testT2F1302_NonSupervisorGeolocationApiReturns403Json(): void
    {
        foreach (['admin_sv', 'asesor_sv'] as $username) {
            $this->actingAsUser($username);
            $res = $this->getJson('api/agencias/geolocalizacion');
            $res->assertStatus(403);
            $json = $this->decodeJsonResponse($res);
            $this->assertSame('error', $json['status'] ?? null);
            $this->assertSame('forbidden', $json['error'] ?? null);
        }
    }

    public function testT2F1303_CrossCountryQueryParamsOnGeolocationApiReturn403Json(): void
    {
        $gtChain = $this->getGeoChainForPais('Guatemala');
        $hnChain = $this->getGeoChainForPais('Honduras');

        $this->actingAsUser('supervisor_sv');

        $queries = [
            ['id_pais' => $gtChain['id_pais']],
            ['pais' => $gtChain['pais']],
            ['id_departamento' => $gtChain['id_departamento']],
            ['id_municipio' => $gtChain['id_municipio']],
            ['id_distrito' => $hnChain['id_distrito']],
        ];

        foreach ($queries as $query) {
            $res = $this->getJson('api/agencias/geolocalizacion', $query);
            $res->assertStatus(403);
            $json = $this->decodeJsonResponse($res);
            $this->assertSame('error', $json['status'] ?? null);
            $this->assertSame('forbidden', $json['error'] ?? null);
            $this->assertArrayNotHasKey('agencias', $json);
        }
    }

    public function testT2F1304_GeolocationApiExcludesNullZeroZeroAndOutOfRangeCoords(): void
    {
        $valid = $this->createAgencyViaHttp('supervisor_sv', [
            'nombre_agencia' => 'Agencia Geo Valida Unica',
            'latitud'        => '13.69294000',
            'longitud'       => '-89.21819000',
        ]);
        $validId = (int) $valid['idagencia'];

        $this->insertRawAgency(['nombre_agencia' => 'Agencia Raw Null', 'latitud' => null, 'longitud' => null]);
        $this->insertRawAgency(['nombre_agencia' => 'Agencia Raw Zero', 'latitud' => 0.0, 'longitud' => 0.0]);
        $this->insertRawAgency(['nombre_agencia' => 'Agencia Raw Lat95', 'latitud' => 95.0, 'longitud' => -89.2]);
        $this->insertRawAgency(['nombre_agencia' => 'Agencia Raw Lng185', 'latitud' => 13.7, 'longitud' => 185.0]);

        $this->actingAsUser('supervisor_sv');
        $apiData = $this->assertJsonOk($this->getJson('api/agencias/geolocalizacion'));

        $this->assertSame(1, (int) ($apiData['total'] ?? -1));
        $this->assertCount(1, $apiData['agencias']);
        $this->assertSame([$validId], $this->extractGeoApiAgencyIds($apiData));
    }

    public function testT2F1305_CombinedBrandAndDepartamentoNoMatchReturnsEmpty200(): void
    {
        $sv        = $this->findPaisByName('El Salvador');
        $idPaisSv  = (int) $sv['id_pais'];
        $ssDepto   = $this->findDepartamentoByName('San Salvador', $idPaisSv);
        $saDepto   = $this->findDepartamentoByName('Santa Ana', $idPaisSv);
        $hondaId   = (int) $this->findMarcaByName('Honda')['id_marca'];
        $yamahaId  = (int) $this->findMarcaByName('Yamaha')['id_marca'];

        $ssMuni    = $this->findAnyMunicipioForDepartamento((int) $ssDepto['id_departamento']);
        $ssDist    = $this->findAnyDistritoForMunicipio((int) $ssMuni['id_municipio']);
        $this->assertNotNull($ssDist);

        $this->createAgencyViaHttp('supervisor_sv', [
            'id_departamento' => (int) $ssDepto['id_departamento'],
            'id_municipio'    => (int) $ssMuni['id_municipio'],
            'id_distrito'     => (int) $ssDist['id_distrito'],
            'marcas'          => [$hondaId],
        ]);

        $res1 = $this->getJson('api/agencias/geolocalizacion', [
            'id_marca'        => $yamahaId,
            'id_departamento' => (int) $ssDepto['id_departamento'],
        ]);
        $json1 = $this->assertJsonOk($res1);
        $this->assertSame('El Salvador', $json1['pais'] ?? null);
        $this->assertSame(0, (int) ($json1['total'] ?? -1));
        $this->assertSame([], $json1['agencias'] ?? null);

        $res2 = $this->getJson('api/agencias/geolocalizacion', [
            'id_marca'        => $hondaId,
            'id_departamento' => (int) $saDepto['id_departamento'],
        ]);
        $json2 = $this->assertJsonOk($res2);
        $this->assertSame('El Salvador', $json2['pais'] ?? null);
        $this->assertSame(0, (int) ($json2['total'] ?? -1));
        $this->assertSame([], $json2['agencias'] ?? null);
    }

    // =========================================================================
    // F14 — PostgreSQL (Base_Datos.sql) + SQLite Schema & FK Integrity (T2-F14-01 .. T2-F14-05)
    // =========================================================================

    public function testT2F1401_AgenciasInvalidPaisFailsForeignKey(): void
    {
        $this->requireHU002Schema();
        $this->assertSqliteForeignKeysEnabled();

        $chain         = $this->getGeoChainForPais('El Salvador');
        $agenciasTable = $this->resolveTableName('Agencias');
        $this->assertNotNull($agenciasTable);
        $beforeCount = $this->countAgencies();

        $threw = false;
        try {
            $this->db->table($agenciasTable)->insert([
                'nombre_agencia'  => 'Agencia FK Pais Invalido',
                'direccion'       => 'Calle Falsa 123',
                'Id_pais'         => 999999,
                'Id_departamento' => $chain['id_departamento'],
                'Id_municipio'    => $chain['id_municipio'],
                'Id_distrito'     => $chain['id_distrito'],
                'latitud'         => 13.69294,
                'longitud'        => -89.21819,
                'activo'          => 1,
            ]);
        } catch (DatabaseException $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'Expected DatabaseException for invalid Id_pais foreign key on Agencias.');
        $this->assertSame($beforeCount, $this->countAgencies());
    }

    public function testT2F1402_AgenciasInvalidGeoForeignKeysFail(): void
    {
        $this->requireHU002Schema();
        $this->assertSqliteForeignKeysEnabled();

        $chain         = $this->getGeoChainForPais('El Salvador');
        $agenciasTable = $this->resolveTableName('Agencias');
        $this->assertNotNull($agenciasTable);
        $beforeCount = $this->countAgencies();

        $invalidCases = [
            'Id_departamento' => ['Id_departamento' => 999999],
            'Id_municipio'    => ['Id_municipio' => 999999],
            'Id_distrito'     => ['Id_distrito' => 999999],
        ];

        foreach ($invalidCases as $column => $override) {
            $threw = false;
            try {
                $this->db->table($agenciasTable)->insert(array_merge([
                    'nombre_agencia'  => 'Agencia FK Geo Invalido',
                    'direccion'       => 'Calle Falsa 456',
                    'Id_pais'         => $chain['id_pais'],
                    'Id_departamento' => $chain['id_departamento'],
                    'Id_municipio'    => $chain['id_municipio'],
                    'Id_distrito'     => $chain['id_distrito'],
                    'latitud'         => 13.69294,
                    'longitud'        => -89.21819,
                    'activo'          => 1,
                ], $override));
            } catch (DatabaseException $e) {
                $threw = true;
            }

            $this->assertTrue($threw, "Expected DatabaseException for invalid {$column} foreign key on Agencias.");
        }

        $this->assertSame($beforeCount, $this->countAgencies());
    }

    public function testT2F1403_AgenciaMarcaDuplicatePairFailsUniqueConstraint(): void
    {
        $this->requireHU002Schema();
        $this->assertSqliteForeignKeysEnabled();

        $brandId   = $this->getDefaultBrandIds(1)[0];
        $idAgencia = $this->insertRawAgency([], [$brandId]);
        $amTable   = $this->resolveTableName('Agencia_Marca');
        $this->assertNotNull($amTable);

        $threw = false;
        try {
            $this->db->table($amTable)->insert([
                'Idagencia' => $idAgencia,
                'Id_marca'  => $brandId,
            ]);
        } catch (DatabaseException $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'Expected DatabaseException for duplicate (Idagencia, Id_marca) in Agencia_Marca.');
        $count = (int) $this->db->table($amTable)->where('Idagencia', $idAgencia)->countAllResults();
        $this->assertSame(1, $count);
    }

    public function testT2F1404_AuditoriaAgenciasInvalidForeignKeysFail(): void
    {
        $this->requireHU002Schema();
        $this->assertSqliteForeignKeysEnabled();

        $idAgencia  = $this->insertRawAgency();
        $this->actingAsUser('supervisor_sv');
        $empCode    = (string) $this->activeSession['codigo_empleado'];
        $auditTable = $this->resolveTableName('Auditoria_Agencias');
        $this->assertNotNull($auditTable);

        $threwBadAgency = false;
        try {
            $this->db->table($auditTable)->insert([
                'Idagencia'       => 999999,
                'Codigo_Empleado' => $empCode,
                'fecha_hora'      => '2026-11-18 10:30:00',
                'tipo_operacion'  => 'CREAR',
                'datos_nuevos'    => '{}',
            ]);
        } catch (DatabaseException $e) {
            $threwBadAgency = true;
        }
        $this->assertTrue($threwBadAgency, 'Expected DatabaseException for invalid Idagencia FK on Auditoria_Agencias.');

        $threwBadEmp = false;
        try {
            $this->db->table($auditTable)->insert([
                'Idagencia'       => $idAgencia,
                'Codigo_Empleado' => 'EMP-NO-EXISTE-999',
                'fecha_hora'      => '2026-11-18 10:30:00',
                'tipo_operacion'  => 'CREAR',
                'datos_nuevos'    => '{}',
            ]);
        } catch (DatabaseException $e) {
            $threwBadEmp = true;
        }
        $this->assertTrue($threwBadEmp, 'Expected DatabaseException for invalid Codigo_Empleado FK on Auditoria_Agencias.');

        $this->assertSame(0, $this->countTableRows('Auditoria_Agencias'));
    }

    public function testT2F1405_BaseDatosSqlHasNoMysqlOnlyOrLegacyIncompatibleSyntax(): void
    {
        $path = ROOTPATH . 'Base_Datos.sql';
        $this->assertFileExists($path);

        $sql = file_get_contents($path);
        $this->assertIsString($sql);
        $this->assertNotSame('', trim($sql));

        $forbiddenPatterns = [
            '/\bAUTO_INCREMENT\b/i',
            '/\bENGINE\s*=/i',
            '/\bcordenadas\b/i',
            '/\bPOINT\b/i',
            '/\bTINYINT\b/i',
            '/\bUNSIGNED\b/i',
            '/`/',
        ];

        foreach ($forbiddenPatterns as $pattern) {
            $this->assertDoesNotMatchRegularExpression($pattern, $sql, "Base_Datos.sql must not contain {$pattern}.");
        }

        foreach (self::REQUIRED_HU002_TABLES as $table) {
            $this->assertMatchesRegularExpression(
                '/CREATE\s+TABLE\s+' . preg_quote($table, '/') . '\b/i',
                $sql,
                "Expected CREATE TABLE {$table} in Base_Datos.sql."
            );
        }

        $this->assertMatchesRegularExpression('/\bSERIAL\s+PRIMARY\s+KEY\b/i', $sql);
        $this->assertMatchesRegularExpression('/\blatitud\s+DECIMAL\s*\(\s*10\s*,\s*8\s*\)/i', $sql);
        $this->assertMatchesRegularExpression('/\blongitud\s+DECIMAL\s*\(\s*11\s*,\s*8\s*\)/i', $sql);
    }

    // =========================================================================
    // F15 — Commercial Advisor Visits Data Model (T2-F15-01 .. T2-F15-05)
    // =========================================================================

    public function testT2F1501_VisitasInvalidAgenciaFailsForeignKey(): void
    {
        $this->requireHU002Schema();
        $this->assertSqliteForeignKeysEnabled();

        $this->actingAsUser('asesor_sv');
        $asesorCode   = (string) $this->activeSession['codigo_empleado'];
        $visitasTable = $this->resolveTableName('Visitas');
        $this->assertNotNull($visitasTable);

        $threw = false;
        try {
            $this->db->table($visitasTable)->insert([
                'Idagencia'     => 999999,
                'Codigo_Asesor' => $asesorCode,
                'fecha_visita'  => '2026-11-18',
                'hora_visita'   => '09:30:00',
                'latitud'       => 13.69294,
                'longitud'      => -89.21819,
            ]);
        } catch (DatabaseException $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'Expected DatabaseException for invalid Idagencia FK on Visitas.');
        $this->assertSame(0, $this->countTableRows('Visitas'));
    }

    public function testT2F1502_VisitasInvalidAsesorFailsForeignKey(): void
    {
        $this->requireHU002Schema();
        $this->assertSqliteForeignKeysEnabled();

        $idAgencia    = $this->insertRawAgency();
        $visitasTable = $this->resolveTableName('Visitas');
        $this->assertNotNull($visitasTable);

        $threw = false;
        try {
            $this->db->table($visitasTable)->insert([
                'Idagencia'     => $idAgencia,
                'Codigo_Asesor' => 'EMP-ASESOR-NO-EXISTE',
                'fecha_visita'  => '2026-11-18',
                'hora_visita'   => '09:30:00',
                'latitud'       => 13.69294,
                'longitud'      => -89.21819,
            ]);
        } catch (DatabaseException $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'Expected DatabaseException for invalid Codigo_Asesor FK on Visitas.');
        $this->assertSame(0, $this->countTableRows('Visitas'));
    }

    public function testT2F1503_VisitasMissingDateOrTimeFailsNotNull(): void
    {
        $this->requireHU002Schema();
        $this->assertSqliteForeignKeysEnabled();

        $idAgencia = $this->insertRawAgency();
        $this->actingAsUser('asesor_sv');
        $asesorCode   = (string) $this->activeSession['codigo_empleado'];
        $visitasTable = $this->resolveTableName('Visitas');
        $this->assertNotNull($visitasTable);

        $base = [
            'Idagencia'     => $idAgencia,
            'Codigo_Asesor' => $asesorCode,
            'fecha_visita'  => '2026-11-18',
            'hora_visita'   => '09:30:00',
            'latitud'       => 13.69294,
            'longitud'      => -89.21819,
        ];

        $cases = [
            'null fecha_visita'    => array_merge($base, ['fecha_visita' => null]),
            'omitted fecha_visita' => array_diff_key($base, ['fecha_visita' => true]),
            'null hora_visita'     => array_merge($base, ['hora_visita' => null]),
            'omitted hora_visita'  => array_diff_key($base, ['hora_visita' => true]),
        ];

        foreach ($cases as $label => $row) {
            $threw = false;
            try {
                $this->db->table($visitasTable)->insert($row);
            } catch (DatabaseException $e) {
                $threw = true;
            }
            $this->assertTrue($threw, "Expected NOT NULL DatabaseException on Visitas ({$label}).");
        }

        $this->assertSame(0, $this->countTableRows('Visitas'));
    }

    public function testT2F1504_VisitasMissingCoordinatesFailsNotNull(): void
    {
        $this->requireHU002Schema();
        $this->assertSqliteForeignKeysEnabled();

        $idAgencia = $this->insertRawAgency();
        $this->actingAsUser('asesor_sv');
        $asesorCode   = (string) $this->activeSession['codigo_empleado'];
        $visitasTable = $this->resolveTableName('Visitas');
        $this->assertNotNull($visitasTable);

        $base = [
            'Idagencia'     => $idAgencia,
            'Codigo_Asesor' => $asesorCode,
            'fecha_visita'  => '2026-11-18',
            'hora_visita'   => '09:30:00',
            'latitud'       => 13.69294,
            'longitud'      => -89.21819,
        ];

        $cases = [
            'null latitud'     => array_merge($base, ['latitud' => null]),
            'omitted latitud'  => array_diff_key($base, ['latitud' => true]),
            'null longitud'    => array_merge($base, ['longitud' => null]),
            'omitted longitud' => array_diff_key($base, ['longitud' => true]),
        ];

        foreach ($cases as $label => $row) {
            $threw = false;
            try {
                $this->db->table($visitasTable)->insert($row);
            } catch (DatabaseException $e) {
                $threw = true;
            }
            $this->assertTrue($threw, "Expected NOT NULL DatabaseException on Visitas ({$label}).");
        }

        $this->assertSame(0, $this->countTableRows('Visitas'));
    }

    public function testT2F1505_JoinedVisitasAgenciasUsuarioPreservesLinkage(): void
    {
        $this->requireHU002Schema();
        $this->assertSqliteForeignKeysEnabled();

        $svChain = $this->getGeoChainForPais('El Salvador');
        $gtChain = $this->getGeoChainForPais('Guatemala');

        $idAgenciaSv = $this->insertRawAgency([
            'nombre_agencia'  => 'Agencia Visita SV',
            'Id_pais'         => $svChain['id_pais'],
            'Id_departamento' => $svChain['id_departamento'],
            'Id_municipio'    => $svChain['id_municipio'],
            'Id_distrito'     => $svChain['id_distrito'],
        ]);
        $idAgenciaGt = $this->insertRawAgency([
            'nombre_agencia'  => 'Agencia Visita GT',
            'Id_pais'         => $gtChain['id_pais'],
            'Id_departamento' => $gtChain['id_departamento'],
            'Id_municipio'    => $gtChain['id_municipio'],
            'Id_distrito'     => null,
            'latitud'         => 14.63491,
            'longitud'        => -90.50688,
        ]);

        $this->actingAsUser('asesor_sv');
        $asesorSv = (string) $this->activeSession['codigo_empleado'];
        $this->actingAsUser('asesor_gt');
        $asesorGt = (string) $this->activeSession['codigo_empleado'];

        $visitasTable  = $this->resolveTableName('Visitas');
        $agenciasTable = $this->resolveTableName('Agencias');
        $usuarioTable  = $this->resolveTableName('Usuario');
        $paisTable     = $this->resolveTableName('Pais');

        $this->assertNotNull($visitasTable);
        $this->assertNotNull($agenciasTable);
        $this->assertNotNull($usuarioTable);
        $this->assertNotNull($paisTable);

        $this->db->table($visitasTable)->insert([
            'Idagencia'     => $idAgenciaSv,
            'Codigo_Asesor' => $asesorSv,
            'fecha_visita'  => '2026-11-18',
            'hora_visita'   => '10:00:00',
            'latitud'       => 13.69294,
            'longitud'      => -89.21819,
            'observaciones' => 'Visita SV',
        ]);
        $this->db->table($visitasTable)->insert([
            'Idagencia'     => $idAgenciaGt,
            'Codigo_Asesor' => $asesorGt,
            'fecha_visita'  => '2026-11-18',
            'hora_visita'   => '11:00:00',
            'latitud'       => 14.63491,
            'longitud'      => -90.50688,
            'observaciones' => 'Visita GT',
        ]);

        $rows = $this->normalizeRows(
            $this->db->table($visitasTable . ' v')
                ->select('v.Idagencia, v.Codigo_Asesor, a.Id_pais AS agencia_pais_id, u.Id_Pais AS asesor_pais_id, p.Pais AS pais_nombre')
                ->join($agenciasTable . ' a', 'v.Idagencia = a.Idagencia', 'inner')
                ->join($usuarioTable . ' u', 'v.Codigo_Asesor = u.Codigo_Empleado', 'inner')
                ->join($paisTable . ' p', 'a.Id_pais = p.Id_pais', 'inner')
                ->orderBy('v.Id_visita', 'ASC')
                ->get()
                ->getResultArray()
        );

        $this->assertCount(2, $rows);
        $this->assertSame($idAgenciaSv, (int) $rows[0]['idagencia']);
        $this->assertSame($asesorSv, (string) $rows[0]['codigo_asesor']);
        $this->assertSame($svChain['id_pais'], (int) $rows[0]['agencia_pais_id']);
        $this->assertSame($svChain['id_pais'], (int) $rows[0]['asesor_pais_id']);
        $this->assertSame('El Salvador', (string) $rows[0]['pais_nombre']);

        $this->assertSame($idAgenciaGt, (int) $rows[1]['idagencia']);
        $this->assertSame($asesorGt, (string) $rows[1]['codigo_asesor']);
        $this->assertSame($gtChain['id_pais'], (int) $rows[1]['agencia_pais_id']);
        $this->assertSame($gtChain['id_pais'], (int) $rows[1]['asesor_pais_id']);
        $this->assertSame('Guatemala', (string) $rows[1]['pais_nombre']);

        $svFiltered = $this->normalizeRows(
            $this->db->table($visitasTable . ' v')
                ->select('v.Idagencia')
                ->join($agenciasTable . ' a', 'v.Idagencia = a.Idagencia', 'inner')
                ->where('a.Id_pais', $svChain['id_pais'])
                ->get()
                ->getResultArray()
        );
        $this->assertCount(1, $svFiltered);
        $this->assertSame($idAgenciaSv, (int) $svFiltered[0]['idagencia']);
    }

    // =========================================================================
    // F16 — Main Menu "Gestión de agencias" Dropdown & Corporate Palette (T2-F16-01 .. T2-F16-05)
    // =========================================================================

    public function testT2F1601_AdministradorDoesNotSeeAgencyManagementMenuOnHome(): void
    {
        $this->actingAsUser('admin_sv');

        $res = $this->getPage('home.php');
        $res->assertStatus(200);
        $decoded = html_entity_decode((string) $res->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->assertStringNotContainsStringIgnoringCase('Gestión de agencias', $decoded);
        $this->assertDoesNotMatchRegularExpression('~href=["\'](?:[^"\']*/)?agencias(?:/crear|/mapa)?(?=[?#"\'])~i', $decoded);
    }

    public function testT2F1602_AsesorDoesNotSeeAgencyManagementMenuOnHome(): void
    {
        $this->actingAsUser('asesor_sv');

        $res = $this->getPage('home.php');
        $res->assertStatus(200);
        $decoded = html_entity_decode((string) $res->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->assertStringNotContainsStringIgnoringCase('Gestión de agencias', $decoded);
        $this->assertDoesNotMatchRegularExpression('~href=["\'](?:[^"\']*/)?agencias(?:/crear|/mapa)?(?=[?#"\'])~i', $decoded);
    }

    public function testT2F1603_HomeFooterAttributionRemainsVisibleForSupervisor(): void
    {
        $this->actingAsUser('supervisor_sv');

        $res = $this->getPage('home.php');
        $res->assertStatus(200);
        $body = (string) $res->getBody();

        $this->assertMatchesRegularExpression('/<footer\b[^>]*>.*?(Equipo de Desarrollo|diseñad|desarrollad).*?<\/footer>/is', $body);
        $this->assertDoesNotMatchRegularExpression('/\.app-footer\s*\{[^}]*(?:display\s*:\s*none|visibility\s*:\s*hidden)/is', $body);
        $this->assertDoesNotMatchRegularExpression('/<footer\b[^>]*style=["\'][^"\']*(?:display\s*:\s*none|visibility\s*:\s*hidden)/is', $body);

        $this->assertAgencyManagementMenuPresent($body);
        $this->assertCorporatePalettePresent($body);
    }

    public function testT2F1604_AgencyViewsDisplayActiveUserIdentityAndCountryBadge(): void
    {
        $gtAg = $this->createAgencyViaHttp('supervisor_gt');
        $idGt = (int) $gtAg['idagencia'];

        $nombre  = (string) ($this->activeSession['nombre'] ?? '');
        $usuario = (string) ($this->activeSession['usuario'] ?? '');

        $routes = [
            'agencias',
            'agencias/crear',
            "agencias/ver/{$idGt}",
            "agencias/editar/{$idGt}",
            'agencias/mapa',
        ];

        foreach ($routes as $uri) {
            $res = $this->getPage($uri);
            $res->assertStatus(200);
            $body = (string) $res->getBody();

            $this->assertStringContainsStringIgnoringCase('Guatemala', $body);
            $this->assertStringContainsStringIgnoringCase('Supervisor', $body);
            $this->assertTrue(
                ($nombre !== '' && str_contains($body, $nombre)) || ($usuario !== '' && str_contains($body, $usuario)),
                "Expected active user identity on {$uri}."
            );
            $this->assertCorporatePalettePresent($body);
            $this->assertStringNotContainsStringIgnoringCase('El Salvador', $body);
            $this->assertStringNotContainsStringIgnoringCase('Honduras', $body);
        }
    }

    public function testT2F1605_AgencyDropdownLinksUseRealRouteUrlsNotPlaceholders(): void
    {
        $this->actingAsUser('supervisor_sv');

        foreach (['home.php', 'agencias', 'agencias/crear', 'agencias/mapa'] as $uri) {
            $res = $this->getPage($uri);
            $res->assertStatus(200);
            $body = (string) $res->getBody();

            $this->assertAgencyManagementMenuPresent($body);
            $this->assertDoesNotMatchRegularExpression(
                '/<a\b[^>]*href=["\'](?:#|\s*|javascript:[^"\']*)["\'][^>]*>\s*(?:Consulta|Registro|Mapa)\b/is',
                $body,
                "Dropdown items on {$uri} must not use placeholder hrefs."
            );

            if ($uri !== 'home.php') {
                $this->assertMatchesRegularExpression('/href=["\'][^"\']*home(?:\.php)?["\']/i', $body);
                $this->assertMatchesRegularExpression('/href=["\'][^"\']*logout["\']/i', $body);
            }
        }
    }
}
