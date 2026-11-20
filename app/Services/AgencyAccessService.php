<?php

namespace App\Services;

use App\Models\AgenciaModel;
use App\Models\DepartamentoModel;
use App\Models\DistritoModel;
use App\Models\MunicipioModel;
use App\Models\PaisModel;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Session\Session;
use Config\Services;

class AgencyAccessService
{
    public const ROLE_SUPERVISOR    = 'Supervisor';
    public const ROLE_ADMINISTRADOR = 'Administrador';
    public const ALLOWED_ROLES      = [self::ROLE_SUPERVISOR, self::ROLE_ADMINISTRADOR];

    /**
     * @var list<string>
     */
    private const COUNTRY_PARAM_KEYS_LOWER = ['id_pais', 'pais'];

    private const MAX_PARAM_SCAN_DEPTH = 64;

    /**
     * @var list<string>
     */
    private const COUNTRY_RECORD_KEYS = ['id_pais', 'Id_pais', 'Id_Pais'];

    public function __construct(
        protected ?Session $sessionOverride = null,
        protected ?PaisModel $paisModel = null,
        protected ?AgenciaModel $agenciaModel = null,
        protected ?DepartamentoModel $departamentoModel = null,
        protected ?MunicipioModel $municipioModel = null,
        protected ?DistritoModel $distritoModel = null,
    ) {
    }

    protected function getSession(): Session
    {
        return $this->sessionOverride ?? Services::session();
    }

    protected function getPaisModel(): PaisModel
    {
        return $this->paisModel ?? new PaisModel();
    }

    protected function getAgenciaModel(): AgenciaModel
    {
        return $this->agenciaModel ?? new AgenciaModel();
    }

    protected function getDepartamentoModel(): DepartamentoModel
    {
        return $this->departamentoModel ?? new DepartamentoModel();
    }

    protected function getMunicipioModel(): MunicipioModel
    {
        return $this->municipioModel ?? new MunicipioModel();
    }

    protected function getDistritoModel(): DistritoModel
    {
        return $this->distritoModel ?? new DistritoModel();
    }

    /**
     * Parse a strictly positive integer, rejecting booleans, arrays, objects, floats, zero, and negatives.
     *
     * @param mixed $value
     */
    public static function parsePositiveInt($value): ?int
    {
        if ($value === null || is_bool($value) || is_array($value) || is_object($value) || is_float($value)) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if ($parsed === false || $parsed <= 0) {
            return null;
        }

        return (int) $parsed;
    }

    public function isApiRequest(RequestInterface $request): bool
    {
        $rawPath = '';
        if (method_exists($request, 'getPath')) {
            $rawPath = (string) $request->getPath();
        } elseif (method_exists($request, 'getUri')) {
            $rawPath = (string) $request->getUri()->getPath();
        }

        if (str_contains($rawPath, '?')) {
            $rawPath = explode('?', $rawPath, 2)[0];
        }

        $normalizedPath = strtolower(trim($rawPath, '/ '));
        while (str_starts_with($normalizedPath, 'index.php/')) {
            $normalizedPath = ltrim(substr($normalizedPath, 10), '/');
        }

        if ($normalizedPath === 'api' || str_starts_with($normalizedPath, 'api/')) {
            return true;
        }

        if (method_exists($request, 'isAJAX') && $request->isAJAX()) {
            return true;
        }

        $accept = strtolower($request->getHeaderLine('Accept'));
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        if (str_contains($contentType, 'application/json')) {
            return true;
        }

        return false;
    }

    /**
     * Validate active session for exact Supervisor role and strictly positive integer id_pais.
     *
     * @return array<string, mixed>
     */
    public function inspectSessionContext(): array
    {
        $session = $this->getSession();

        if ($session->get('isLoggedIn') !== true) {
            return [
                'status' => 'unauthenticated',
                'reason' => 'Debe iniciar sesión para acceder al sistema',
            ];
        }

        $rawRol = $session->get('rol');
        if (! is_string($rawRol) || ! in_array($rawRol, self::ALLOWED_ROLES, true)) {
            return [
                'status' => 'forbidden',
                'reason' => 'Acceso denegado: Se requiere rol de Supervisor o Administrador.',
            ];
        }

        $idPais = self::parsePositiveInt($session->get('id_pais'));
        if ($idPais === null) {
            return [
                'status' => 'forbidden',
                'reason' => 'Acceso denegado: Se requiere un país asignado válido.',
            ];
        }

        $paisNombre = $this->resolveCountryName($idPais);
        if ($paisNombre === '') {
            return [
                'status' => 'forbidden',
                'reason' => 'Acceso denegado: Se requiere un país asignado válido.',
            ];
        }

        $rawCodigo  = $session->get('codigo_empleado');
        $rawUsuario = $session->get('usuario');
        $rawName    = $session->get('nombre');

        return [
            'status'          => 'ok',
            'codigo_empleado' => is_string($rawCodigo) ? trim($rawCodigo) : '',
            'usuario'         => is_string($rawUsuario) ? trim($rawUsuario) : '',
            'nombre'          => is_string($rawName) ? trim($rawName) : '',
            'rol'             => $rawRol,
            'id_pais'         => $idPais,
            'pais_nombre'     => $paisNombre,
        ];
    }

    public function isSupervisor(): bool
    {
        return $this->inspectSessionContext()['status'] === 'ok';
    }

    public function getSupervisorCountryId(): ?int
    {
        $ctx = $this->inspectSessionContext();

        return $ctx['status'] === 'ok' ? (int) $ctx['id_pais'] : null;
    }

    public function getSupervisorCountryName(): string
    {
        $ctx = $this->inspectSessionContext();

        return $ctx['status'] === 'ok' ? (string) $ctx['pais_nombre'] : '';
    }

    public function resolveCountryName(int $idPais): string
    {
        if ($idPais <= 0) {
            return '';
        }

        $paisRow = $this->getPaisModel()->findById($idPais);
        if (is_array($paisRow)) {
            $dbName = trim((string) ($paisRow['pais'] ?? $paisRow['Pais'] ?? ''));
            if ($dbName !== '') {
                return $dbName;
            }
        }

        return '';
    }

    /**
     * Validate all request country inputs across GET, POST, raw input, and JSON body
     * without short-circuiting so parameter pollution cannot bypass isolation.
     */
    public function checkRequestCountryParams(
        RequestInterface $request,
        int $supervisorPaisId,
        ?string $supervisorPaisName = null,
    ): bool {
        if ($supervisorPaisId <= 0) {
            return false;
        }

        $candidates = [];

        if (method_exists($request, 'getGet')) {
            $getData = $request->getGet();
            if (is_array($getData) && ! $this->collectCountryParamCandidates($getData, $candidates)) {
                return false;
            }
        }

        if (method_exists($request, 'getPost')) {
            $postData = $request->getPost();
            if (is_array($postData) && ! $this->collectCountryParamCandidates($postData, $candidates)) {
                return false;
            }
        }

        $rawBody     = method_exists($request, 'getBody') ? $request->getBody() : null;
        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        $isJsonBody  = str_contains($contentType, 'application/json');
        $decodedJson = null;

        if (is_string($rawBody)) {
            $trimmedBody = trim($rawBody);
            if (
                $trimmedBody !== ''
                && (
                    $isJsonBody
                    || str_starts_with($trimmedBody, '{')
                    || str_starts_with($trimmedBody, '[')
                )
            ) {
                $decoded = json_decode($trimmedBody, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $isJsonBody  = true;
                    $decodedJson = $decoded;
                }
            }
        }

        if (! $isJsonBody) {
            $rawInput = null;
            if (method_exists($request, 'getRawInput')) {
                $rawInput = $request->getRawInput();
            } elseif (method_exists($request, 'getRawInputVar')) {
                $rawInput = $request->getRawInputVar(null);
            }

            if (is_array($rawInput) && ! $this->collectCountryParamCandidates($rawInput, $candidates)) {
                return false;
            }
        }

        if (is_array($decodedJson) && ! $this->collectCountryParamCandidates($decodedJson, $candidates)) {
            return false;
        }

        $resolvedExpectedName = null;

        foreach ($candidates as [$key, $rawValue]) {
            if ($rawValue === null) {
                continue;
            }

            if (! is_scalar($rawValue) || is_bool($rawValue) || is_float($rawValue)) {
                return false;
            }

            $trimmed = trim((string) $rawValue);
            if ($trimmed === '') {
                continue;
            }

            if ($key === 'id_pais') {
                $parsed = self::parsePositiveInt($trimmed);
                if ($parsed === null || $parsed !== $supervisorPaisId) {
                    return false;
                }

                continue;
            }

            // Key is 'pais': allow positive integer ID OR UTF-8 case-insensitive country name
            $parsedInt = filter_var($trimmed, FILTER_VALIDATE_INT);
            if ($parsedInt !== false) {
                if ($parsedInt <= 0 || $parsedInt !== $supervisorPaisId) {
                    return false;
                }

                continue;
            }

            if (is_numeric($trimmed)) {
                return false;
            }

            if ($resolvedExpectedName === null) {
                $candidateName        = $supervisorPaisName !== null ? trim($supervisorPaisName) : '';
                $resolvedExpectedName = $candidateName !== ''
                    ? $candidateName
                    : $this->resolveCountryName($supervisorPaisId);
            }

            if ($resolvedExpectedName === '') {
                return false;
            }

            if (mb_strtolower($trimmed, 'UTF-8') !== mb_strtolower($resolvedExpectedName, 'UTF-8')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<array-key, mixed>          $data
     * @param list<array{0: string, 1: mixed}> $candidates
     */
    private function collectCountryParamCandidates(array $data, array &$candidates, int $depth = 0): bool
    {
        if ($depth > self::MAX_PARAM_SCAN_DEPTH) {
            return false;
        }

        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $normalizedKey = strtolower(trim($key));
                if (in_array($normalizedKey, self::COUNTRY_PARAM_KEYS_LOWER, true)) {
                    $candidates[] = [$normalizedKey, $value];
                    continue;
                }
            }

            if (is_array($value) && ! $this->collectCountryParamCandidates($value, $candidates, $depth + 1)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Extract strictly positive country ID from any normalized or raw DB record.
     *
     * @param array<string, mixed> $record
     */
    public function extractCountryIdFromRecord(array $record): ?int
    {
        foreach (self::COUNTRY_RECORD_KEYS as $key) {
            if (array_key_exists($key, $record)) {
                return self::parsePositiveInt($record[$key]);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $agency
     */
    public function isAgencyOwnedByCountry(array $agency, int $supervisorPaisId): bool
    {
        if ($supervisorPaisId <= 0) {
            return false;
        }

        $agencyCountryId = $this->extractCountryIdFromRecord($agency);

        return $agencyCountryId !== null && $agencyCountryId === $supervisorPaisId;
    }

    /**
     * @param array<string, mixed> $agency
     */
    public function isAgencyOwnedByCurrentSupervisor(array $agency): bool
    {
        $supervisorPaisId = $this->getSupervisorCountryId();
        if ($supervisorPaisId === null) {
            return false;
        }

        return $this->isAgencyOwnedByCountry($agency, $supervisorPaisId);
    }

    /**
     * Authorize access to a specific agency ID, distinguishing 404 (missing) from 403 (cross-country).
     *
     * @return array<string, mixed>
     */
    public function authorizeAgencyAccess(int $idAgencia, bool $hydrated = true): array
    {
        $ctx = $this->inspectSessionContext();
        if ($ctx['status'] !== 'ok') {
            return [
                'status'  => $ctx['status'],
                'message' => $ctx['reason'],
            ];
        }

        if ($idAgencia <= 0) {
            return [
                'status'  => 'not_found',
                'message' => 'Agencia no encontrada.',
            ];
        }

        $agenciaModel = $this->getAgenciaModel();
        $raw          = $agenciaModel->findByIdRaw($idAgencia);
        if ($raw === null) {
            return [
                'status'  => 'not_found',
                'message' => 'Agencia no encontrada.',
            ];
        }

        $supervisorPaisId = (int) $ctx['id_pais'];
        if (! $this->isAgencyOwnedByCountry($raw, $supervisorPaisId)) {
            return [
                'status'  => 'forbidden',
                'message' => 'Acceso denegado: la agencia pertenece a otro país.',
            ];
        }

        $agency = $hydrated ? ($agenciaModel->findByIdAndPais($idAgencia, $supervisorPaisId) ?? $raw) : $raw;

        return [
            'status'  => 'ok',
            'agency'  => $agency,
            'context' => $ctx,
        ];
    }

    /**
     * @return array{status: 'ok'|'forbidden'|'not_found', pais_id?: int, message?: string}
     */
    public function checkDepartamentoOwnership(int $idDepartamento, int $supervisorPaisId): array
    {
        if ($supervisorPaisId <= 0) {
            return [
                'status'  => 'forbidden',
                'message' => 'País de supervisor inválido.',
            ];
        }

        if ($idDepartamento <= 0) {
            return [
                'status'  => 'not_found',
                'message' => 'Departamento no encontrado.',
            ];
        }

        $depto = $this->getDepartamentoModel()->find($idDepartamento);
        if (! is_array($depto)) {
            return [
                'status'  => 'not_found',
                'message' => 'Departamento no encontrado.',
            ];
        }

        $paisId = $this->extractCountryIdFromRecord($depto);
        if ($paisId === null || $paisId !== $supervisorPaisId) {
            return [
                'status'  => 'forbidden',
                'pais_id' => $paisId ?? 0,
                'message' => 'Acceso denegado: el departamento pertenece a otro país.',
            ];
        }

        return [
            'status'  => 'ok',
            'pais_id' => $paisId,
        ];
    }

    /**
     * @return array{status: 'ok'|'forbidden'|'not_found', pais_id?: int, message?: string}
     */
    public function checkMunicipioOwnership(int $idMunicipio, int $supervisorPaisId): array
    {
        if ($supervisorPaisId <= 0) {
            return [
                'status'  => 'forbidden',
                'message' => 'País de supervisor inválido.',
            ];
        }

        if ($idMunicipio <= 0) {
            return [
                'status'  => 'not_found',
                'message' => 'Municipio no encontrado.',
            ];
        }

        $muni = $this->getMunicipioModel()->find($idMunicipio);
        if (! is_array($muni)) {
            return [
                'status'  => 'not_found',
                'message' => 'Municipio no encontrado.',
            ];
        }

        $deptoId = self::parsePositiveInt($muni['id_departamento'] ?? $muni['Id_departamento'] ?? null);
        if ($deptoId === null) {
            return [
                'status'  => 'not_found',
                'message' => 'Departamento padre no encontrado.',
            ];
        }

        $deptoCheck = $this->checkDepartamentoOwnership($deptoId, $supervisorPaisId);
        if ($deptoCheck['status'] === 'forbidden') {
            return [
                'status'  => 'forbidden',
                'pais_id' => $deptoCheck['pais_id'] ?? 0,
                'message' => 'Acceso denegado: el municipio pertenece a otro país.',
            ];
        }

        if ($deptoCheck['status'] === 'not_found') {
            return [
                'status'  => 'not_found',
                'message' => 'Departamento padre no encontrado.',
            ];
        }

        return [
            'status'  => 'ok',
            'pais_id' => $deptoCheck['pais_id'],
        ];
    }

    /**
     * @return array{status: 'ok'|'forbidden'|'not_found', pais_id?: int, message?: string}
     */
    public function checkDistritoOwnership(int $idDistrito, int $supervisorPaisId): array
    {
        if ($supervisorPaisId <= 0) {
            return [
                'status'  => 'forbidden',
                'message' => 'País de supervisor inválido.',
            ];
        }

        if ($idDistrito <= 0) {
            return [
                'status'  => 'not_found',
                'message' => 'Distrito no encontrado.',
            ];
        }

        $dist = $this->getDistritoModel()->find($idDistrito);
        if (! is_array($dist)) {
            return [
                'status'  => 'not_found',
                'message' => 'Distrito no encontrado.',
            ];
        }

        $muniId = self::parsePositiveInt($dist['id_municipio'] ?? $dist['Id_municipio'] ?? null);
        if ($muniId === null) {
            return [
                'status'  => 'not_found',
                'message' => 'Municipio padre no encontrado.',
            ];
        }

        $muniCheck = $this->checkMunicipioOwnership($muniId, $supervisorPaisId);
        if ($muniCheck['status'] === 'forbidden') {
            return [
                'status'  => 'forbidden',
                'pais_id' => $muniCheck['pais_id'] ?? 0,
                'message' => 'Acceso denegado: el distrito pertenece a otro país.',
            ];
        }

        if ($muniCheck['status'] === 'not_found') {
            return [
                'status'  => 'not_found',
                'message' => 'Municipio padre no encontrado.',
            ];
        }

        return [
            'status'  => 'ok',
            'pais_id' => $muniCheck['pais_id'],
        ];
    }

    public function isDepartamentoInCountry(int $idDepartamento, int $supervisorPaisId): bool
    {
        return $this->checkDepartamentoOwnership($idDepartamento, $supervisorPaisId)['status'] === 'ok';
    }

    public function isMunicipioInCountry(int $idMunicipio, int $supervisorPaisId): bool
    {
        return $this->checkMunicipioOwnership($idMunicipio, $supervisorPaisId)['status'] === 'ok';
    }

    public function isDistritoInCountry(int $idDistrito, int $supervisorPaisId): bool
    {
        return $this->checkDistritoOwnership($idDistrito, $supervisorPaisId)['status'] === 'ok';
    }

    public function unauthorizedResponse(
        RequestInterface $request,
        string $message = 'Debe iniciar sesión para acceder al sistema',
    ): ResponseInterface {
        if ($this->isApiRequest($request)) {
            return Services::response()
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 'error',
                    'error'   => 'unauthenticated',
                    'message' => $message,
                ]);
        }

        return redirect()->to(site_url('login'))->with('error', $message);
    }

    public function forbiddenResponse(
        RequestInterface $request,
        string $message = 'Acceso denegado.',
    ): ResponseInterface {
        if ($this->isApiRequest($request)) {
            return Services::response()
                ->setStatusCode(403)
                ->setJSON([
                    'status'  => 'error',
                    'error'   => 'forbidden',
                    'message' => $message,
                ]);
        }

        return Services::response()
            ->setStatusCode(403)
            ->setContentType('text/html', 'UTF-8')
            ->setBody(esc($message));
    }

    public function notFoundResponse(
        RequestInterface $request,
        string $message = 'Recurso no encontrado.',
    ): ResponseInterface {
        if ($this->isApiRequest($request)) {
            return Services::response()
                ->setStatusCode(404)
                ->setJSON([
                    'status'  => 'error',
                    'error'   => 'not_found',
                    'message' => $message,
                ]);
        }

        return Services::response()
            ->setStatusCode(404)
            ->setContentType('text/html', 'UTF-8')
            ->setBody(view('errors/html/error_404', ['message' => $message]));
    }

    /**
     * One-call guard for SupervisorFilter and M3 agency/geo controllers.
     * Returns null when authorized, or a ready-to-return ResponseInterface (302/401/403).
     */
    public function authorizeSupervisorRequest(RequestInterface $request): ?ResponseInterface
    {
        $ctx = $this->inspectSessionContext();

        if ($ctx['status'] === 'unauthenticated') {
            return $this->unauthorizedResponse($request, (string) $ctx['reason']);
        }

        if ($ctx['status'] !== 'ok') {
            return $this->forbiddenResponse($request, (string) $ctx['reason']);
        }

        if (! $this->checkRequestCountryParams($request, (int) $ctx['id_pais'], (string) $ctx['pais_nombre'])) {
            return $this->forbiddenResponse(
                $request,
                'Acceso denegado: Restricción de jurisdicción territorial por país.'
            );
        }

        return null;
    }
}
