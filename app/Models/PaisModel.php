<?php

namespace App\Models;

use App\Models\Traits\DualDriverTrait;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Model;
use CodeIgniter\Validation\ValidationInterface;

class PaisModel extends Model
{
    use DualDriverTrait;

    protected $table            = 'Pais';
    protected $primaryKey       = 'Id_pais';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'Pais',
        'pais',
        'requiere_distrito',
    ];

    protected $beforeInsert = ['mapBeforeWrite'];
    protected $beforeUpdate = ['mapBeforeWrite'];
    protected $afterFind    = ['normalizeAfterFind'];

    private const COLUMN_MAP = [
        'pais'              => 'Pais',
        'requiere_distrito' => 'requiere_distrito',
    ];

    private const MIXED_KEYS = ['Id_pais', 'Pais', 'requiere_distrito'];
    private const INT_KEYS   = ['Id_pais', 'requiere_distrito'];

    public function __construct(?ConnectionInterface $db = null, ?ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);
        $this->initDualDriverTable('Pais', 'Id_pais');
    }

    public function findById(int $idPais): ?array
    {
        if ($idPais <= 0) {
            return null;
        }

        $row = $this->find($idPais);

        return is_array($row) ? $row : null;
    }

    public function requiresDistrict(int $idPais): bool
    {
        $row = $this->findById($idPais);
        if ($row === null) {
            return false;
        }

        return ((int) ($row['requiere_distrito'] ?? 1)) === 1;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllOrdered(): array
    {
        return $this->orderBy($this->col('Pais'), 'ASC')->findAll();
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
