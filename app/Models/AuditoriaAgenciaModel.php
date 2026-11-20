<?php

namespace App\Models;

use App\Models\Traits\DualDriverTrait;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Model;
use CodeIgniter\Validation\ValidationInterface;
use InvalidArgumentException;
use RuntimeException;

class AuditoriaAgenciaModel extends Model
{
    use DualDriverTrait;

    public const OP_CREAR        = 'CREAR';
    public const OP_ACTUALIZAR   = 'ACTUALIZAR';
    public const OP_DESHABILITAR = 'DESHABILITAR';

    protected $table            = 'Auditoria_Agencias';
    protected $primaryKey       = 'Id_auditoria';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'Idagencia',
        'idagencia',
        'Codigo_Empleado',
        'codigo_empleado',
        'fecha_hora',
        'tipo_operacion',
        'datos_anteriores',
        'datos_nuevos',
        'motivo_deshabilitacion',
    ];

    protected $beforeInsert = ['mapBeforeWrite'];
    protected $beforeUpdate = ['mapBeforeWrite'];
    protected $afterFind    = ['normalizeAfterFind'];

    private const COLUMN_MAP = [
        'idagencia'              => 'Idagencia',
        'codigo_empleado'        => 'Codigo_Empleado',
        'fecha_hora'             => 'fecha_hora',
        'tipo_operacion'         => 'tipo_operacion',
        'datos_anteriores'       => 'datos_anteriores',
        'datos_nuevos'           => 'datos_nuevos',
        'motivo_deshabilitacion' => 'motivo_deshabilitacion',
    ];

    private const MIXED_KEYS = ['Id_auditoria', 'Idagencia', 'Codigo_Empleado'];
    private const INT_KEYS   = ['Id_auditoria', 'Idagencia'];

    public function __construct(?ConnectionInterface $db = null, ?ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);
        $this->initDualDriverTable('Auditoria_Agencias', 'Id_auditoria');
    }

    /**
     * Record an audit log entry for an agency operation.
     *
     * @param array<string, mixed>|null $datosAnteriores
     * @param array<string, mixed>|null $datosNuevos
     */
    public function recordEntry(
        int $idAgencia,
        string $codigoEmpleado,
        string $tipoOperacion,
        ?array $datosAnteriores,
        ?array $datosNuevos,
        ?string $motivo = null,
    ): int {
        if ($idAgencia <= 0) {
            throw new InvalidArgumentException('El identificador de agencia es obligatorio para auditoría.');
        }

        $codigoTrimmed = trim($codigoEmpleado);
        if ($codigoTrimmed === '') {
            throw new InvalidArgumentException('El código de empleado es obligatorio para auditoría.');
        }

        $validOps = [self::OP_CREAR, self::OP_ACTUALIZAR, self::OP_DESHABILITAR];
        if (! in_array($tipoOperacion, $validOps, true)) {
            throw new InvalidArgumentException('Tipo de operación de auditoría inválido: ' . $tipoOperacion);
        }

        $motivoTrimmed = $motivo !== null ? trim($motivo) : null;
        if ($tipoOperacion === self::OP_DESHABILITAR && ($motivoTrimmed === null || $motivoTrimmed === '')) {
            throw new InvalidArgumentException('El motivo de deshabilitación es obligatorio en auditoría.');
        }

        $payload = [
            'Idagencia'              => $idAgencia,
            'Codigo_Empleado'        => $codigoTrimmed,
            'fecha_hora'             => date('Y-m-d H:i:s'),
            'tipo_operacion'         => $tipoOperacion,
            'datos_anteriores'       => $datosAnteriores !== null
                ? json_encode($datosAnteriores, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null,
            'datos_nuevos'           => $datosNuevos !== null
                ? json_encode($datosNuevos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null,
            'motivo_deshabilitacion' => $motivoTrimmed !== '' ? $motivoTrimmed : null,
        ];

        $inserted = $this->insert($payload);
        if ($inserted === false) {
            throw new RuntimeException('No se pudo registrar la entrada de auditoría de agencia.');
        }

        $id = (int) $this->getInsertID();
        if ($id <= 0) {
            throw new RuntimeException('Error al obtener el identificador de auditoría insertado.');
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

        $audTable = $this->tbl('Auditoria_Agencias');
        $usrTable = $this->tbl('Usuario');

        $rows = $this->db->table($audTable)
            ->select(
                $audTable . '.*, '
                . $usrTable . '.' . $this->col('Nombre') . ' as usuario_nombre, '
                . $usrTable . '.' . $this->col('Usuario') . ' as usuario_login'
            )
            ->join(
                $usrTable,
                $usrTable . '.' . $this->col('Codigo_Empleado') . ' = ' . $audTable . '.' . $this->col('Codigo_Empleado'),
                'left'
            )
            ->where($audTable . '.' . $this->col('Idagencia'), $idAgencia)
            ->orderBy($audTable . '.fecha_hora', 'DESC')
            ->orderBy($audTable . '.' . $this->col('Id_auditoria'), 'DESC')
            ->get()
            ->getResultArray();

        $result = [];
        foreach ($rows as $row) {
            $normalized = $this->normalizeDualCaseRow($row, self::MIXED_KEYS, self::INT_KEYS);

            $prevRaw = $normalized['datos_anteriores'] ?? null;
            $newRaw  = $normalized['datos_nuevos'] ?? null;

            $prevDecoded = is_string($prevRaw) && $prevRaw !== '' ? json_decode($prevRaw, true) : null;
            $newDecoded  = is_string($newRaw) && $newRaw !== '' ? json_decode($newRaw, true) : null;

            $normalized['datos_anteriores_decoded'] = is_array($prevDecoded) ? $prevDecoded : null;
            $normalized['datos_nuevos_decoded']     = is_array($newDecoded) ? $newDecoded : null;

            $result[] = $normalized;
        }

        return $result;
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
        return $this->handleAfterFind($eventData, self::MIXED_KEYS, self::INT_KEYS);
    }
}
