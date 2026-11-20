<?php

namespace App\Models;

use App\Models\Traits\DualDriverTrait;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Model;
use CodeIgniter\Validation\ValidationInterface;

class MarcaModel extends Model
{
    use DualDriverTrait;

    protected $table            = 'Marca';
    protected $primaryKey       = 'Id_marca';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'Marca',
        'marca',
    ];

    protected $beforeInsert = ['mapBeforeWrite'];
    protected $beforeUpdate = ['mapBeforeWrite'];
    protected $afterFind    = ['normalizeAfterFind'];

    private const COLUMN_MAP = [
        'marca' => 'Marca',
    ];

    private const MIXED_KEYS = ['Id_marca', 'Marca'];
    private const INT_KEYS   = ['Id_marca'];

    public function __construct(?ConnectionInterface $db = null, ?ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);
        $this->initDualDriverTable('Marca', 'Id_marca');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllOrdered(): array
    {
        return $this->orderBy($this->col('Marca'), 'ASC')->findAll();
    }

    /**
     * @param list<int|string> $ids
     * @return list<array<string, mixed>>
     */
    public function findByIds(array $ids): array
    {
        $cleanIds = [];
        foreach ($ids as $id) {
            $intId = (int) $id;
            if ($intId > 0) {
                $cleanIds[$intId] = $intId;
            }
        }

        if ($cleanIds === []) {
            return [];
        }

        return $this->whereIn($this->col('Id_marca'), array_values($cleanIds))
            ->orderBy($this->col('Marca'), 'ASC')
            ->findAll();
    }

    public function findByName(string $name): ?array
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return null;
        }

        $colIdent = $this->db->escapeIdentifiers($this->col('Marca'));
        $row      = $this->where('LOWER(' . $colIdent . ')', mb_strtolower($trimmed, 'UTF-8'))->first();

        return is_array($row) ? $row : null;
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
