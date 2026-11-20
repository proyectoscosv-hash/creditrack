<?php

namespace App\Models;

use App\Models\Traits\DualDriverTrait;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Model;
use CodeIgniter\Validation\ValidationInterface;

class DistritoModel extends Model
{
    use DualDriverTrait;

    protected $table            = 'Distrito';
    protected $primaryKey       = 'Id_distrito';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'Distrito',
        'distrito',
        'Id_municipio',
        'id_municipio',
    ];

    protected $beforeInsert = ['mapBeforeWrite'];
    protected $beforeUpdate = ['mapBeforeWrite'];
    protected $afterFind    = ['normalizeAfterFind'];

    private const COLUMN_MAP = [
        'distrito'     => 'Distrito',
        'id_municipio' => 'Id_municipio',
    ];

    private const MIXED_KEYS = ['Id_distrito', 'Distrito', 'Id_municipio'];
    private const INT_KEYS   = ['Id_distrito', 'Id_municipio'];

    public function __construct(?ConnectionInterface $db = null, ?ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);
        $this->initDualDriverTable('Distrito', 'Id_distrito');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByMunicipio(int $idMunicipio): array
    {
        if ($idMunicipio <= 0) {
            return [];
        }

        return $this->where($this->col('Id_municipio'), $idMunicipio)
            ->orderBy($this->col('Distrito'), 'ASC')
            ->findAll();
    }

    public function belongsToMunicipio(int $idDistrito, int $idMunicipio): bool
    {
        if ($idDistrito <= 0 || $idMunicipio <= 0) {
            return false;
        }

        return $this->where($this->col('Id_distrito'), $idDistrito)
            ->where($this->col('Id_municipio'), $idMunicipio)
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
