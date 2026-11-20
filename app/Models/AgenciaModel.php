<?php

namespace App\Models;

use App\Models\Traits\DualDriverTrait;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Model;
use CodeIgniter\Validation\ValidationInterface;
use DomainException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class AgenciaModel extends Model
{
    use DualDriverTrait;

    protected $table            = 'Agencias';
    protected $primaryKey       = 'Idagencia';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'creado_en';
    protected $updatedField  = 'actualizado_en';

    protected $allowedFields = [
        'nombre_agencia',
        'direccion',
        'Id_pais',
        'id_pais',
        'Id_departamento',
        'id_departamento',
        'Id_municipio',
        'id_municipio',
        'Id_distrito',
        'id_distrito',
        'latitud',
        'longitud',
        'Id_marca',
        'id_marca',
        'activo',
        'motivo_deshabilitacion',
        'deshabilitado_en',
        'deshabilitado_por',
        'creado_en',
        'actualizado_en',
    ];

    protected $beforeInsert = ['mapBeforeWrite'];
    protected $beforeUpdate = ['mapBeforeWrite'];
    protected $afterFind    = ['normalizeAfterFind'];

    private const COLUMN_MAP = [
        'nombre_agencia'         => 'nombre_agencia',
        'direccion'              => 'direccion',
        'id_pais'                => 'Id_pais',
        'id_departamento'        => 'Id_departamento',
        'id_municipio'           => 'Id_municipio',
        'id_distrito'            => 'Id_distrito',
        'latitud'                => 'latitud',
        'longitud'               => 'longitud',
        'id_marca'               => 'Id_marca',
        'activo'                 => 'activo',
        'motivo_deshabilitacion' => 'motivo_deshabilitacion',
        'deshabilitado_en'       => 'deshabilitado_en',
        'deshabilitado_por'      => 'deshabilitado_por',
        'creado_en'              => 'creado_en',
        'actualizado_en'         => 'actualizado_en',
    ];

    private const MIXED_KEYS = [
        'Idagencia',
        'Id_pais',
        'Id_departamento',
        'Id_municipio',
        'Id_distrito',
        'Id_marca',
        'activo',
        'requiere_distrito',
        'latitud',
        'longitud',
    ];

    private const INT_KEYS = [
        'Idagencia',
        'Id_pais',
        'Id_departamento',
        'Id_municipio',
        'Id_distrito',
        'Id_marca',
        'activo',
        'requiere_distrito',
    ];

    private const FLOAT_KEYS = ['latitud', 'longitud'];

    protected ?PaisModel $paisModel                  = null;
    protected ?DepartamentoModel $departamentoModel  = null;
    protected ?MunicipioModel $municipioModel        = null;
    protected ?DistritoModel $distritoModel          = null;
    protected ?MarcaModel $marcaModel                = null;
    protected ?AuditoriaAgenciaModel $auditoriaModel = null;

    public function __construct(?ConnectionInterface $db = null, ?ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);
        $this->initDualDriverTable('Agencias', 'Idagencia');
    }

    public function setAuditoriaModel(AuditoriaAgenciaModel $auditoriaModel): self
    {
        $this->auditoriaModel = $auditoriaModel;

        return $this;
    }

    /**
     * Parse a raw coordinate value into a finite float, or null if invalid.
     *
     * @param mixed $value
     */
    public static function parseCoordinate($value): ?float
    {
        if ($value === null || is_bool($value) || is_array($value) || is_object($value)) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        if (! is_numeric($value)) {
            return null;
        }

        $floatVal = (float) $value;
        if (! is_finite($floatVal)) {
            return null;
        }

        return $floatVal;
    }

    /**
     * Validate latitude [-90, 90] and longitude [-180, 180], rejecting null and (0, 0).
     *
     * @param mixed $latitud
     * @param mixed $longitud
     */
    public static function isValidCoordinates($latitud, $longitud): bool
    {
        $lat = self::parseCoordinate($latitud);
        $lng = self::parseCoordinate($longitud);

        if ($lat === null || $lng === null) {
            return false;
        }

        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return false;
        }

        if (abs($lat) < 1e-7 && abs($lng) < 1e-7) {
            return false;
        }

        return true;
    }

    public function findByIdRaw(int $idAgencia): ?array
    {
        if ($idAgencia <= 0) {
            return null;
        }

        $row = $this->find($idAgencia);

        return is_array($row) ? $row : null;
    }

    public function findByIdAndPais(int $idAgencia, int $idPais): ?array
    {
        if ($idAgencia <= 0 || $idPais <= 0) {
            return null;
        }

        $ageTable  = $this->tbl('Agencias');
        $row       = $this->buildJoinedAgencyQuery()
            ->where($ageTable . '.' . $this->col('Idagencia'), $idAgencia)
            ->where($ageTable . '.' . $this->col('Id_pais'), $idPais)
            ->get()
            ->getRowArray();

        if ($row === null) {
            return null;
        }

        $normalized = $this->normalizeDualCaseRow($row, self::MIXED_KEYS, self::INT_KEYS, self::FLOAT_KEYS);
        $hydrated   = $this->attachBrandsToRows([$normalized]);

        return $hydrated[0] ?? null;
    }

    /**
     * Query agencies within a country with optional filters, without duplicating rows on M:N brands.
     *
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function findAllByPaisWithDetails(int $idPais, array $filters = []): array
    {
        if ($idPais <= 0) {
            return [];
        }

        $ageTable = $this->tbl('Agencias');
        $builder  = $this->buildJoinedAgencyQuery()
            ->where($ageTable . '.' . $this->col('Id_pais'), $idPais);

        $estado = strtolower(trim((string) ($filters['estado'] ?? 'todas')));
        if ($estado === 'activas') {
            $builder->where($ageTable . '.activo', 1);
        } elseif ($estado === 'inactivas') {
            $builder->where($ageTable . '.activo', 0);
        }

        $this->applyGeoAndBrandFilters($builder, $filters);

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $likeChar = (string) ($this->db->likeEscapeChar ?? '!');
            if ($likeChar === '') {
                $likeChar = '!';
            }
            $likeTerm = str_replace(
                [$likeChar, '%', '_'],
                [$likeChar . $likeChar, $likeChar . '%', $likeChar . '_'],
                mb_strtolower($q, 'UTF-8')
            );
            $pattern          = $this->db->escape('%' . $likeTerm . '%');
            $escClause        = ' ESCAPE ' . $this->db->escape($likeChar);
            $prefixedAgeTable = $this->db->prefixTable($ageTable);
            $nomCol           = $this->db->escapeIdentifiers($prefixedAgeTable . '.nombre_agencia');
            $dirCol           = $this->db->escapeIdentifiers($prefixedAgeTable . '.direccion');
            $builder->groupStart()
                ->where('LOWER(' . $nomCol . ') LIKE ' . $pattern . $escClause, null, false)
                ->orWhere('LOWER(' . $dirCol . ') LIKE ' . $pattern . $escClause, null, false)
                ->groupEnd();
        }

        $rows = $builder
            ->orderBy($ageTable . '.nombre_agencia', 'ASC')
            ->orderBy($ageTable . '.' . $this->col('Idagencia'), 'ASC')
            ->get()
            ->getResultArray();

        $normalized = array_map(
            fn (array $row): array => $this->normalizeDualCaseRow($row, self::MIXED_KEYS, self::INT_KEYS, self::FLOAT_KEYS),
            $rows
        );

        return $this->attachBrandsToRows($normalized);
    }

    /**
     * Query country-scoped agencies formatted for the Geolocation API & Leaflet map.
     *
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function findForGeolocation(int $idPais, array $filters = []): array
    {
        if ($idPais <= 0) {
            return [];
        }

        $ageTable = $this->tbl('Agencias');
        $builder  = $this->buildJoinedAgencyQuery()
            ->where($ageTable . '.' . $this->col('Id_pais'), $idPais);

        $includeInactive = $this->isTruthyFlag($filters['incluir_inactivas'] ?? false);
        if (! $includeInactive) {
            $builder->where($ageTable . '.activo', 1);
        }

        $prefixedAgeTable = $this->db->prefixTable($ageTable);
        $latIdent         = $this->db->escapeIdentifiers($prefixedAgeTable . '.latitud');
        $lngIdent         = $this->db->escapeIdentifiers($prefixedAgeTable . '.longitud');
        $builder->where($latIdent . ' IS NOT NULL', null, false)
            ->where($lngIdent . ' IS NOT NULL', null, false)
            ->where($latIdent . ' >= -90 AND ' . $latIdent . ' <= 90', null, false)
            ->where($lngIdent . ' >= -180 AND ' . $lngIdent . ' <= 180', null, false)
            ->where('NOT (' . $latIdent . ' = 0 AND ' . $lngIdent . ' = 0)', null, false);

        $this->applyGeoAndBrandFilters($builder, $filters);

        $rows = $builder
            ->orderBy($ageTable . '.nombre_agencia', 'ASC')
            ->orderBy($ageTable . '.' . $this->col('Idagencia'), 'ASC')
            ->get()
            ->getResultArray();

        $normalized = array_map(
            fn (array $row): array => $this->normalizeDualCaseRow($row, self::MIXED_KEYS, self::INT_KEYS, self::FLOAT_KEYS),
            $rows
        );
        $hydrated = $this->attachBrandsToRows($normalized);

        $result = [];
        foreach ($hydrated as $row) {
            if (! self::isValidCoordinates($row['latitud'] ?? null, $row['longitud'] ?? null)) {
                continue;
            }

            $result[] = [
                'id'           => (int) $row['Idagencia'],
                'idagencia'    => (int) $row['Idagencia'],
                'nombre'       => (string) $row['nombre_agencia'],
                'pais'         => (string) ($row['pais_nombre'] ?? ''),
                'direccion'    => (string) $row['direccion'],
                'marcas'       => $row['marca_nombres'],
                'latitud'      => (float) $row['latitud'],
                'longitud'     => (float) $row['longitud'],
                'departamento' => (string) ($row['departamento_nombre'] ?? ''),
                'municipio'    => (string) ($row['municipio_nombre'] ?? ''),
                'distrito'     => isset($row['distrito_nombre']) && $row['distrito_nombre'] !== null
                    ? (string) $row['distrito_nombre']
                    : null,
                'activo'       => (int) $row['activo'],
            ];
        }

        return $result;
    }

    /**
     * Create an agency, sync its M:N brands, and record a CREAR audit entry atomically.
     *
     * @param array<string, mixed> $data
     * @param list<int|string>     $marcaIds
     */
    public function createWithBrandsAndAudit(array $data, array $marcaIds, string $codigoEmpleado): int
    {
        $empleado = trim($codigoEmpleado);
        if ($empleado === '') {
            throw new InvalidArgumentException('El código de empleado es obligatorio.');
        }

        [$writeData, $cleanMarcaIds] = $this->validateAndPrepareWriteData($data, $marcaIds);
        $idPais                      = (int) ($writeData['Id_pais'] ?? $writeData['id_pais']);
        $writeData['activo']         = 1;

        return $this->runInTransaction(function () use ($writeData, $cleanMarcaIds, $idPais, $empleado): int {
            $inserted = $this->insert($writeData);
            if ($inserted === false) {
                throw new RuntimeException('No se pudo insertar la agencia.');
            }

            $idAgencia = (int) $this->getInsertID();
            if ($idAgencia <= 0) {
                throw new RuntimeException('No se pudo obtener el identificador de la agencia creada.');
            }

            $this->syncBrands($idAgencia, $cleanMarcaIds);

            $snapshot = $this->findByIdAndPais($idAgencia, $idPais);
            if ($snapshot === null) {
                throw new RuntimeException('No se pudo cargar el registro recién creado de la agencia.');
            }

            $this->getAuditoriaModel()->recordEntry(
                $idAgencia,
                $empleado,
                AuditoriaAgenciaModel::OP_CREAR,
                null,
                $snapshot,
                null
            );

            return $idAgencia;
        });
    }

    /**
     * Update an existing country-scoped agency, sync its M:N brands, and record an ACTUALIZAR audit entry.
     *
     * @param array<string, mixed> $data
     * @param list<int|string>     $marcaIds
     * @return array<string, mixed>
     */
    public function updateWithBrandsAndAudit(
        int $idAgencia,
        int $idPais,
        array $data,
        array $marcaIds,
        string $codigoEmpleado,
    ): array {
        $empleado = trim($codigoEmpleado);
        if ($empleado === '') {
            throw new InvalidArgumentException('El código de empleado es obligatorio.');
        }

        $before = $this->findByIdAndPais($idAgencia, $idPais);
        if ($before === null) {
            throw new RuntimeException('Agencia no encontrada o fuera del país autorizado.');
        }

        [$writeData, $cleanMarcaIds] = $this->validateAndPrepareWriteData($data, $marcaIds, $idPais);

        return $this->runInTransaction(function () use ($idAgencia, $idPais, $writeData, $cleanMarcaIds, $before, $empleado): array {
            $updated = $this->update($idAgencia, $writeData);
            if ($updated === false) {
                throw new RuntimeException('No se pudo actualizar la agencia.');
            }

            $this->syncBrands($idAgencia, $cleanMarcaIds);

            $after = $this->findByIdAndPais($idAgencia, $idPais);
            if ($after === null) {
                throw new RuntimeException('No se pudo recargar la agencia actualizada.');
            }

            $this->getAuditoriaModel()->recordEntry(
                $idAgencia,
                $empleado,
                AuditoriaAgenciaModel::OP_ACTUALIZAR,
                $before,
                $after,
                null
            );

            return $after;
        });
    }

    /**
     * Logically disable an agency (activo = 0) and record a DESHABILITAR audit entry atomically.
     *
     * @return array<string, mixed>
     */
    public function softDisableWithAudit(
        int $idAgencia,
        int $idPais,
        string $motivo,
        string $codigoEmpleado,
    ): array {
        $empleado      = trim($codigoEmpleado);
        $motivoTrimmed = trim($motivo);

        if ($empleado === '') {
            throw new InvalidArgumentException('El código de empleado es obligatorio.');
        }

        if ($motivoTrimmed === '') {
            throw new InvalidArgumentException('El motivo de deshabilitación es obligatorio.');
        }

        $before = $this->findByIdAndPais($idAgencia, $idPais);
        if ($before === null) {
            throw new RuntimeException('Agencia no encontrada o fuera del país autorizado.');
        }

        if ((int) ($before['activo'] ?? 0) === 0) {
            throw new DomainException('La agencia ya se encuentra deshabilitada.');
        }

        return $this->runInTransaction(function () use ($idAgencia, $idPais, $motivoTrimmed, $empleado, $before): array {
            $updated = $this->update($idAgencia, [
                'activo'                 => 0,
                'motivo_deshabilitacion' => $motivoTrimmed,
                'deshabilitado_en'       => date('Y-m-d H:i:s'),
                'deshabilitado_por'      => $empleado,
            ]);

            if ($updated === false) {
                throw new RuntimeException('No se pudo deshabilitar la agencia.');
            }

            $after = $this->findByIdAndPais($idAgencia, $idPais);
            if ($after === null) {
                throw new RuntimeException('No se pudo recargar la agencia deshabilitada.');
            }

            $this->getAuditoriaModel()->recordEntry(
                $idAgencia,
                $empleado,
                AuditoriaAgenciaModel::OP_DESHABILITAR,
                $before,
                $after,
                $motivoTrimmed
            );

            return $after;
        });
    }

    /**
     * Execute a database operation atomically, enforcing transException(true) and transStatus() verification.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    protected function runInTransaction(callable $callback)
    {
        $prevTransException = (bool) ($this->db->transException ?? false);
        $startedOutermost   = ((int) ($this->db->transDepth ?? 0)) === 0;

        if ($startedOutermost && ! $this->db->transStatus() && method_exists($this->db, 'resetTransStatus')) {
            $this->db->resetTransStatus();
        }

        $this->db->transException(true);
        if ($this->db->transBegin() === false) {
            $this->db->transException($prevTransException);
            throw new RuntimeException('No se pudo iniciar la transacción.');
        }

        try {
            $result = $callback();

            if ($this->db->transStatus() === false) {
                throw new RuntimeException('La transacción falló y fue revertida.');
            }

            if ($this->db->transCommit() === false) {
                throw new RuntimeException('No se pudo confirmar la transacción.');
            }

            return $result;
        } catch (Throwable $e) {
            $this->db->transRollback();
            if ($startedOutermost && method_exists($this->db, 'resetTransStatus')) {
                $this->db->resetTransStatus();
            }
            throw $e;
        } finally {
            $this->db->transException($prevTransException);
        }
    }

    /**
     * Validate agency fields, cascading geo hierarchy, Guatemala optional district, coordinates, and brands.
     *
     * @param array<string, mixed> $data
     * @param list<int|string>     $marcaIds
     * @return array{0: array<string, mixed>, 1: list<int>}
     */
    protected function validateAndPrepareWriteData(array $data, array $marcaIds, ?int $enforcePaisId = null): array
    {
        $nombre    = trim((string) ($data['nombre_agencia'] ?? ''));
        $direccion = trim((string) ($data['direccion'] ?? ''));

        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre de la agencia es obligatorio.');
        }
        if (mb_strlen($nombre, 'UTF-8') > 150) {
            throw new InvalidArgumentException('El nombre de la agencia no debe exceder 150 caracteres.');
        }
        if ($direccion === '') {
            throw new InvalidArgumentException('La dirección de la agencia es obligatoria.');
        }

        $idPais  = (int) ($data['Id_pais'] ?? $data['id_pais'] ?? 0);
        $idDepto = (int) ($data['Id_departamento'] ?? $data['id_departamento'] ?? 0);
        $idMuni  = (int) ($data['Id_municipio'] ?? $data['id_municipio'] ?? 0);
        $rawDist = $data['Id_distrito'] ?? $data['id_distrito'] ?? null;

        if ($idPais <= 0) {
            throw new InvalidArgumentException('El país es obligatorio.');
        }

        if ($enforcePaisId !== null && $idPais !== $enforcePaisId) {
            throw new DomainException('Acceso denegado: el país no corresponde al supervisor.');
        }

        $pais = $this->getPaisModel()->findById($idPais);
        if ($pais === null) {
            throw new InvalidArgumentException('El país seleccionado no existe.');
        }

        if ($idDepto <= 0 || ! $this->getDepartamentoModel()->belongsToPais($idDepto, $idPais)) {
            throw new InvalidArgumentException('El departamento seleccionado no corresponde al país indicado.');
        }

        if ($idMuni <= 0 || ! $this->getMunicipioModel()->belongsToDepartamento($idMuni, $idDepto)) {
            throw new InvalidArgumentException('El municipio seleccionado no corresponde al departamento indicado.');
        }

        $requiresDistrict = ((int) ($pais['requiere_distrito'] ?? 1)) === 1;
        $idDistrito       = null;

        if ($requiresDistrict) {
            if (is_bool($rawDist) || is_array($rawDist) || is_object($rawDist)) {
                throw new InvalidArgumentException('El distrito es obligatorio y debe pertenecer al municipio seleccionado.');
            }
            $parsedDist = filter_var(is_string($rawDist) ? trim($rawDist) : $rawDist, FILTER_VALIDATE_INT);
            if ($parsedDist === false || $parsedDist <= 0 || ! $this->getDistritoModel()->belongsToMunicipio($parsedDist, $idMuni)) {
                throw new InvalidArgumentException('El distrito es obligatorio y debe pertenecer al municipio seleccionado.');
            }
            $idDistrito = $parsedDist;
        } else {
            if (is_string($rawDist)) {
                $rawDist = trim($rawDist);
            }

            if ($rawDist !== null && $rawDist !== '') {
                if (is_bool($rawDist) || is_array($rawDist) || is_object($rawDist)) {
                    throw new InvalidArgumentException('El distrito proporcionado no corresponde al municipio seleccionado.');
                }

                $parsedDist = filter_var($rawDist, FILTER_VALIDATE_INT);
                if ($parsedDist === false || $parsedDist < 0) {
                    throw new InvalidArgumentException('El distrito proporcionado no corresponde al municipio seleccionado.');
                }

                if ($parsedDist > 0) {
                    if (! $this->getDistritoModel()->belongsToMunicipio($parsedDist, $idMuni)) {
                        throw new InvalidArgumentException('El distrito proporcionado no corresponde al municipio seleccionado.');
                    }
                    $idDistrito = $parsedDist;
                }
            }
        }

        $latRaw = $data['latitud'] ?? null;
        $lngRaw = $data['longitud'] ?? null;
        if (! self::isValidCoordinates($latRaw, $lngRaw)) {
            throw new InvalidArgumentException('Las coordenadas geográficas (latitud/longitud) son obligatorias y deben ser válidas.');
        }

        $cleanMarcaIds = [];
        foreach ($marcaIds as $mid) {
            if (is_bool($mid) || is_array($mid) || is_object($mid)) {
                throw new InvalidArgumentException('Todas las marcas seleccionadas deben ser identificadores enteros válidos.');
            }
            $parsedMid = filter_var(is_string($mid) ? trim($mid) : $mid, FILTER_VALIDATE_INT);
            if ($parsedMid === false || $parsedMid <= 0) {
                throw new InvalidArgumentException('Todas las marcas seleccionadas deben ser identificadores enteros válidos.');
            }
            $cleanMarcaIds[$parsedMid] = $parsedMid;
        }
        $cleanMarcaIds = array_values($cleanMarcaIds);

        if ($cleanMarcaIds === []) {
            throw new InvalidArgumentException('Debe seleccionar al menos una marca de motocicleta.');
        }

        $foundBrands = $this->getMarcaModel()->findByIds($cleanMarcaIds);
        if (count($foundBrands) !== count($cleanMarcaIds)) {
            throw new InvalidArgumentException('Una o más marcas seleccionadas no existen.');
        }

        $writeData = [
            'nombre_agencia'  => $nombre,
            'direccion'       => $direccion,
            'Id_pais'         => $idPais,
            'Id_departamento' => $idDepto,
            'Id_municipio'    => $idMuni,
            'Id_distrito'     => $idDistrito,
            'latitud'         => self::parseCoordinate($latRaw),
            'longitud'        => self::parseCoordinate($lngRaw),
            'Id_marca'        => $cleanMarcaIds[0],
        ];

        return [$writeData, $cleanMarcaIds];
    }

    /**
     * @param list<int> $cleanMarcaIds
     */
    protected function syncBrands(int $idAgencia, array $cleanMarcaIds): void
    {
        $pivotTable = $this->tbl('Agencia_Marca');
        $agenciaCol = $this->col('Idagencia');
        $marcaCol   = $this->col('Id_marca');

        $deleted = $this->db->table($pivotTable)->where($agenciaCol, $idAgencia)->delete();
        if ($deleted === false || $this->db->transStatus() === false) {
            throw new RuntimeException('No se pudieron limpiar las marcas previas de la agencia.');
        }

        $rows = [];
        foreach ($cleanMarcaIds as $idMarca) {
            $rows[] = [
                $agenciaCol => $idAgencia,
                $marcaCol   => $idMarca,
            ];
        }

        if ($rows !== []) {
            $inserted = $this->db->table($pivotTable)->insertBatch($rows);
            if ($inserted === false || $this->db->transStatus() === false) {
                throw new RuntimeException('No se pudieron asociar las marcas de la agencia.');
            }
        }
    }

    protected function buildJoinedAgencyQuery(): BaseBuilder
    {
        $ageTable  = $this->tbl('Agencias');
        $paisTable = $this->tbl('Pais');
        $depTable  = $this->tbl('Departamento');
        $munTable  = $this->tbl('Municipio');
        $disTable  = $this->tbl('Distrito');

        return $this->db->table($ageTable)
            ->select(
                $ageTable . '.*, '
                . $paisTable . '.' . $this->col('Pais') . ' as pais_nombre, '
                . $paisTable . '.requiere_distrito as requiere_distrito, '
                . $depTable . '.' . $this->col('Departamento') . ' as departamento_nombre, '
                . $munTable . '.' . $this->col('Municipio') . ' as municipio_nombre, '
                . $disTable . '.' . $this->col('Distrito') . ' as distrito_nombre'
            )
            ->join($paisTable, $paisTable . '.' . $this->col('Id_pais') . ' = ' . $ageTable . '.' . $this->col('Id_pais'))
            ->join($depTable, $depTable . '.' . $this->col('Id_departamento') . ' = ' . $ageTable . '.' . $this->col('Id_departamento'))
            ->join($munTable, $munTable . '.' . $this->col('Id_municipio') . ' = ' . $ageTable . '.' . $this->col('Id_municipio'))
            ->join($disTable, $disTable . '.' . $this->col('Id_distrito') . ' = ' . $ageTable . '.' . $this->col('Id_distrito'), 'left');
    }

    /**
     * @param array<string, mixed> $filters
     */
    protected function applyGeoAndBrandFilters(BaseBuilder $builder, array $filters): void
    {
        $ageTable = $this->tbl('Agencias');

        $idDepto = (int) ($filters['id_departamento'] ?? 0);
        if ($idDepto > 0) {
            $builder->where($ageTable . '.' . $this->col('Id_departamento'), $idDepto);
        }

        $idMuni = (int) ($filters['id_municipio'] ?? 0);
        if ($idMuni > 0) {
            $builder->where($ageTable . '.' . $this->col('Id_municipio'), $idMuni);
        }

        $idDist = (int) ($filters['id_distrito'] ?? 0);
        if ($idDist > 0) {
            $builder->where($ageTable . '.' . $this->col('Id_distrito'), $idDist);
        }

        $idMarca   = (int) ($filters['id_marca'] ?? 0);
        $marcaName = trim((string) ($filters['marca'] ?? ''));

        if ($idMarca > 0 || $marcaName !== '') {
            $amTable    = $this->db->escapeIdentifiers($this->db->prefixTable($this->tbl('Agencia_Marca')));
            $marcaTable = $this->db->escapeIdentifiers($this->db->prefixTable($this->tbl('Marca')));
            $amAgCol    = $this->db->escapeIdentifiers($this->col('Idagencia'));
            $amMarcaCol = $this->db->escapeIdentifiers($this->col('Id_marca'));
            $agPkCol    = $this->db->escapeIdentifiers($this->db->prefixTable($ageTable))
                . '.' . $this->db->escapeIdentifiers($this->col('Idagencia'));

            $conditions = ['am.' . $amAgCol . ' = ' . $agPkCol];
            if ($idMarca > 0) {
                $conditions[] = 'am.' . $amMarcaCol . ' = ' . $idMarca;
            }
            if ($marcaName !== '') {
                $mNameCol     = $this->db->escapeIdentifiers($this->col('Marca'));
                $conditions[] = 'LOWER(m.' . $mNameCol . ') = ' . $this->db->escape(mb_strtolower($marcaName, 'UTF-8'));
            }

            $sql = 'EXISTS (SELECT 1 FROM ' . $amTable . ' am '
                . 'INNER JOIN ' . $marcaTable . ' m ON m.' . $amMarcaCol . ' = am.' . $amMarcaCol . ' '
                . 'WHERE ' . implode(' AND ', $conditions) . ')';

            $builder->where($sql, null, false);
        }
    }

    /**
     * Batch-load brands from Agencia_Marca + Marca for a list of agency rows.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    protected function attachBrandsToRows(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $agencyIds = [];
        foreach ($rows as $row) {
            $id = (int) ($row['Idagencia'] ?? $row['idagencia'] ?? 0);
            if ($id > 0) {
                $agencyIds[$id] = $id;
            }
        }

        $brandsByAgency = [];
        if ($agencyIds !== []) {
            $amTable    = $this->tbl('Agencia_Marca');
            $marcaTable = $this->tbl('Marca');
            $agCol      = $this->col('Idagencia');
            $mrkCol     = $this->col('Id_marca');
            $mrkNameCol = $this->col('Marca');

            $brandRows = $this->db->table($amTable)
                ->select(
                    $amTable . '.' . $agCol . ' as idagencia, '
                    . $marcaTable . '.' . $mrkCol . ' as id_marca, '
                    . $marcaTable . '.' . $mrkNameCol . ' as marca'
                )
                ->join($marcaTable, $marcaTable . '.' . $mrkCol . ' = ' . $amTable . '.' . $mrkCol)
                ->whereIn($amTable . '.' . $agCol, array_values($agencyIds))
                ->orderBy($marcaTable . '.' . $mrkNameCol, 'ASC')
                ->get()
                ->getResultArray();

            foreach ($brandRows as $bRow) {
                $agId     = (int) ($bRow['idagencia'] ?? 0);
                $marcaId  = (int) ($bRow['id_marca'] ?? 0);
                $marcaStr = (string) ($bRow['marca'] ?? '');
                if ($agId > 0 && $marcaId > 0) {
                    $brandsByAgency[$agId][] = [
                        'id_marca' => $marcaId,
                        'marca'    => $marcaStr,
                    ];
                }
            }
        }

        foreach ($rows as $index => $row) {
            $agId         = (int) ($row['Idagencia'] ?? $row['idagencia'] ?? 0);
            $agencyBrands = $brandsByAgency[$agId] ?? [];

            $rows[$index]['marcas']        = $agencyBrands;
            $rows[$index]['marca_ids']     = array_values(array_map(static fn (array $b): int => $b['id_marca'], $agencyBrands));
            $rows[$index]['marca_nombres'] = array_values(array_map(static fn (array $b): string => $b['marca'], $agencyBrands));
        }

        return $rows;
    }

    /**
     * @param mixed $flag
     */
    private function isTruthyFlag($flag): bool
    {
        if (is_bool($flag)) {
            return $flag;
        }
        if (is_int($flag)) {
            return $flag === 1;
        }
        $str = strtolower(trim((string) $flag));

        return in_array($str, ['1', 'true', 'yes', 'si'], true);
    }

    protected function getPaisModel(): PaisModel
    {
        return $this->paisModel ??= new PaisModel($this->db);
    }

    protected function getDepartamentoModel(): DepartamentoModel
    {
        return $this->departamentoModel ??= new DepartamentoModel($this->db);
    }

    protected function getMunicipioModel(): MunicipioModel
    {
        return $this->municipioModel ??= new MunicipioModel($this->db);
    }

    protected function getDistritoModel(): DistritoModel
    {
        return $this->distritoModel ??= new DistritoModel($this->db);
    }

    protected function getMarcaModel(): MarcaModel
    {
        return $this->marcaModel ??= new MarcaModel($this->db);
    }

    protected function getAuditoriaModel(): AuditoriaAgenciaModel
    {
        return $this->auditoriaModel ??= new AuditoriaAgenciaModel($this->db);
    }

    /**
     * @param array<string, mixed> $eventData
     * @return array<string, mixed>
     */
    protected function mapBeforeWrite(array $eventData): array
    {
        if (isset($eventData['data']) && is_array($eventData['data'])) {
            $eventData['data'] = $this->mapWriteColumns($eventData['data'], self::COLUMN_MAP);
        }

        return $eventData;
    }

    /**
     * @param array<string, mixed> $eventData
     * @return array<string, mixed>
     */
    protected function normalizeAfterFind(array $eventData): array
    {
        return $this->handleAfterFind($eventData, self::MIXED_KEYS, self::INT_KEYS, self::FLOAT_KEYS);
    }
}
