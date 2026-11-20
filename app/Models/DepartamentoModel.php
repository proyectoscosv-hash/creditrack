<?php

namespace App\Models;

use App\Models\Traits\DualDriverTrait;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Model;
use CodeIgniter\Validation\ValidationInterface;

class DepartamentoModel extends Model
{
    use DualDriverTrait;

    protected $table            = 'Departamento';
    protected $primaryKey       = 'Id_departamento';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'Departamento',
        'departamento',
        'Id_pais',
        'id_pais',
    ];

    protected $beforeInsert = ['mapBeforeWrite'];
    protected $beforeUpdate = ['mapBeforeWrite'];
    protected $afterFind    = ['normalizeAfterFind'];

    private const COLUMN_MAP = [
        'departamento' => 'Departamento',
        'id_pais'      => 'Id_pais',
    ];

    private const MIXED_KEYS = ['Id_departamento', 'Departamento', 'Id_pais'];
    private const INT_KEYS   = ['Id_departamento', 'Id_pais'];

    public function __construct(?ConnectionInterface $db = null, ?ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);
        $this->initDualDriverTable('Departamento', 'Id_departamento');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByPais(int $idPais): array
    {
        if ($idPais <= 0) {
            return [];
        }

        return $this->where($this->col('Id_pais'), $idPais)
            ->orderBy($this->col('Departamento'), 'ASC')
            ->findAll();
    }

    public function belongsToPais(int $idDepto, int $idPais): bool
    {
        if ($idDepto <= 0 || $idPais <= 0) {
            return false;
        }

        return $this->where($this->col('Id_departamento'), $idDepto)
            ->where($this->col('Id_pais'), $idPais)
            ->countAllResults() > 0;
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
