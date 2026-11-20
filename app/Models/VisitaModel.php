<?php

namespace App\Models;

use App\Models\Traits\DualDriverTrait;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Model;
use CodeIgniter\Validation\ValidationInterface;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

class VisitaModel extends Model
{
    use DualDriverTrait;

    protected $table            = 'Visitas';
    protected $primaryKey       = 'Id_visita';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    // Visitas has creado_en only (no actualizado_en column)
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'creado_en';
    protected $updatedField  = '';

    protected $allowedFields = [
        'Idagencia',
        'idagencia',
        'Codigo_Asesor',
        'codigo_asesor',
        'fecha_visita',
        'hora_visita',
        'latitud',
        'longitud',
        'observaciones',
        'creado_en',
    ];

    protected $beforeInsert = ['mapBeforeWrite'];
    protected $beforeUpdate = ['mapBeforeWrite'];
    protected $afterFind    = ['normalizeAfterFind'];

    private const COLUMN_MAP = [
        'idagencia'     => 'Idagencia',
        'codigo_asesor' => 'Codigo_Asesor',
        'fecha_visita'  => 'fecha_visita',
        'hora_visita'   => 'hora_visita',
        'latitud'       => 'latitud',
        'longitud'      => 'longitud',
        'observaciones' => 'observaciones',
        'creado_en'     => 'creado_en',
    ];

    private const MIXED_KEYS = ['Id_visita', 'Idagencia', 'Codigo_Asesor'];
    private const INT_KEYS   = ['Id_visita', 'Idagencia'];
    private const FLOAT_KEYS = ['latitud', 'longitud'];

    public function __construct(?ConnectionInterface $db = null, ?ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);
        $this->initDualDriverTable('Visitas', 'Id_visita');
    }

    /**
     * Validate and create a commercial advisor visit record.
     *
     * @param array<string, mixed> $data
     */
    public function createVisit(array $data): int
    {
        $idAgencia    = (int) ($data['Idagencia'] ?? $data['idagencia'] ?? 0);
        $codigoAsesor = trim((string) ($data['Codigo_Asesor'] ?? $data['codigo_asesor'] ?? ''));
        $fecha        = trim((string) ($data['fecha_visita'] ?? ''));
        $hora         = trim((string) ($data['hora_visita'] ?? ''));
        $latRaw       = $data['latitud'] ?? null;
        $lngRaw       = $data['longitud'] ?? null;
        $obs          = isset($data['observaciones']) ? trim((string) $data['observaciones']) : null;

        if ($idAgencia <= 0) {
            throw new InvalidArgumentException('El identificador de agencia es obligatorio.');
        }

        $agencyExists = $this->db->table($this->tbl('Agencias'))
            ->where($this->col('Idagencia'), $idAgencia)
            ->countAllResults() > 0;
        if (! $agencyExists) {
            throw new InvalidArgumentException('La agencia indicada no existe.');
        }

        if ($codigoAsesor === '') {
            throw new InvalidArgumentException('El código del asesor es obligatorio.');
        }

        $advisorExists = $this->db->table($this->tbl('Usuario'))
            ->where($this->col('Codigo_Empleado'), $codigoAsesor)
            ->countAllResults() > 0;
        if (! $advisorExists) {
            throw new InvalidArgumentException('El asesor indicado no existe.');
        }

        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $fecha) {
            throw new InvalidArgumentException('La fecha de visita debe tener formato válido YYYY-MM-DD.');
        }

        $normalizedTime = $this->normalizeTime($hora);
        if ($normalizedTime === null) {
            throw new InvalidArgumentException('La hora de visita debe tener formato válido HH:MM o HH:MM:SS.');
        }

        if (! AgenciaModel::isValidCoordinates($latRaw, $lngRaw)) {
            throw new InvalidArgumentException('Las coordenadas geográficas de la visita son inválidas.');
        }

        $payload = [
            'Idagencia'     => $idAgencia,
            'Codigo_Asesor' => $codigoAsesor,
            'fecha_visita'  => $fecha,
            'hora_visita'   => $normalizedTime,
            'latitud'       => AgenciaModel::parseCoordinate($latRaw),
            'longitud'      => AgenciaModel::parseCoordinate($lngRaw),
            'observaciones' => $obs !== '' ? $obs : null,
        ];

        $inserted = $this->insert($payload);
        if ($inserted === false) {
            throw new RuntimeException('No se pudo registrar la visita.');
        }

        $id = (int) $this->getInsertID();
        if ($id <= 0) {
            throw new RuntimeException('No se pudo obtener el identificador de la visita.');
        }

        return $id;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByAgencia(int $idAgencia): array
    {
        if ($idAgencia <= 0) {
            return [];
        }

        $visTable = $this->tbl('Visitas');
        $usrTable = $this->tbl('Usuario');
        $ageTable = $this->tbl('Agencias');

        $rows = $this->db->table($visTable)
            ->select(
                $visTable . '.*, '
                . $usrTable . '.' . $this->col('Nombre') . ' as asesor_nombre, '
                . $usrTable . '.' . $this->col('Usuario') . ' as asesor_usuario, '
                . $ageTable . '.nombre_agencia as nombre_agencia'
            )
            ->join(
                $usrTable,
                $usrTable . '.' . $this->col('Codigo_Empleado') . ' = ' . $visTable . '.' . $this->col('Codigo_Asesor'),
                'left'
            )
            ->join(
                $ageTable,
                $ageTable . '.' . $this->col('Idagencia') . ' = ' . $visTable . '.' . $this->col('Idagencia'),
                'left'
            )
            ->where($visTable . '.' . $this->col('Idagencia'), $idAgencia)
            ->orderBy($visTable . '.fecha_visita', 'DESC')
            ->orderBy($visTable . '.hora_visita', 'DESC')
            ->orderBy($visTable . '.' . $this->col('Id_visita'), 'DESC')
            ->get()
            ->getResultArray();

        return array_map(
            fn (array $row): array => $this->normalizeDualCaseRow($row, self::MIXED_KEYS, self::INT_KEYS, self::FLOAT_KEYS),
            $rows
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByAsesor(string $codigoAsesor): array
    {
        $trimmed = trim($codigoAsesor);
        if ($trimmed === '') {
            return [];
        }

        $visTable = $this->tbl('Visitas');
        $ageTable = $this->tbl('Agencias');

        $rows = $this->db->table($visTable)
            ->select($visTable . '.*, ' . $ageTable . '.nombre_agencia as nombre_agencia')
            ->join(
                $ageTable,
                $ageTable . '.' . $this->col('Idagencia') . ' = ' . $visTable . '.' . $this->col('Idagencia'),
                'left'
            )
            ->where($visTable . '.' . $this->col('Codigo_Asesor'), $trimmed)
            ->orderBy($visTable . '.fecha_visita', 'DESC')
            ->orderBy($visTable . '.hora_visita', 'DESC')
            ->orderBy($visTable . '.' . $this->col('Id_visita'), 'DESC')
            ->get()
            ->getResultArray();

        return array_map(
            fn (array $row): array => $this->normalizeDualCaseRow($row, self::MIXED_KEYS, self::INT_KEYS, self::FLOAT_KEYS),
            $rows
        );
    }

    private function normalizeTime(string $hora): ?string
    {
        $dtFull = DateTimeImmutable::createFromFormat('!H:i:s', $hora);
        if ($dtFull !== false && $dtFull->format('H:i:s') === $hora) {
            return $dtFull->format('H:i:s');
        }

        $dtShort = DateTimeImmutable::createFromFormat('!H:i', $hora);
        if ($dtShort !== false && $dtShort->format('H:i') === $hora) {
            return $dtShort->format('H:i:s');
        }

        return null;
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
