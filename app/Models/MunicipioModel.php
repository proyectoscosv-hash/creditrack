<?php

namespace App\Models;

use App\Models\Traits\DualDriverTrait;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Model;
use CodeIgniter\Validation\ValidationInterface;

class MunicipioModel extends Model
{
    use DualDriverTrait;

    protected $table            = 'Municipio';
    protected $primaryKey       = 'Id_municipio';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'Municipio',
        'municipio',
        'Id_departamento',
        'id_departamento',
    ];

    protected $beforeInsert = ['mapBeforeWrite'];
    protected $beforeUpdate = ['mapBeforeWrite'];
    protected $afterFind    = ['normalizeAfterFind'];

    private const COLUMN_MAP = [
        'municipio'       => 'Municipio',
        'id_departamento' => 'Id_departamento',
    ];

    private const MIXED_KEYS = ['Id_municipio', 'Municipio', 'Id_departamento'];
    private const INT_KEYS   = ['Id_municipio', 'Id_departamento'];

    public function __construct(?ConnectionInterface $db = null, ?ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);
        $this->initDualDriverTable('Municipio', 'Id_municipio');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByDepartamento(int $idDepto): array
    {
        if ($idDepto <= 0) {
            return [];
        }

        return $this->where($this->col('Id_departamento'), $idDepto)
            ->orderBy($this->col('Municipio'), 'ASC')
            ->findAll();
    }

    public function belongsToDepartamento(int $idMunicipio, int $idDepto): bool
    {
        if ($idMunicipio <= 0 || $idDepto <= 0) {
            return false;
        }

        return $this->where($this->col('Id_municipio'), $idMunicipio)
            ->where($this->col('Id_departamento'), $idDepto)
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
