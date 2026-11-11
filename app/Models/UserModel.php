<?php

namespace App\Models;

use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Model;
use CodeIgniter\Validation\ValidationInterface;

class UserModel extends Model
{
    protected $table            = 'Usuario';
    protected $primaryKey       = 'Codigo_Empleado';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'Codigo_Empleado',
        'Nombre',
        'Usuario',
        'Contrasena',
        'Id_Pais',
        'codigo_empleado',
        'nombre',
        'usuario',
        'contrasena',
        'id_pais',
        'telefono',
        'rol',
        'intentos_fallidos',
        'bloqueado',
        'ultimo_fallo',
        'ultimo_login',
        'creado_en',
        'actualizado_en',
    ];

    // Dates and Timestamps
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'creado_en';
    protected $updatedField  = 'actualizado_en';

    // Validation
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    protected bool $hasDbLockoutColumns = true;

    public function __construct(?ConnectionInterface $db = null, ?ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);

        if ($this->isPostgre()) {
            $this->table      = 'usuario';
            $this->primaryKey = 'codigo_empleado';

            try {
                $fields = array_map('strtolower', $this->db->getFieldNames($this->table));
                $this->hasDbLockoutColumns = in_array('intentos_fallidos', $fields, true)
                    && in_array('bloqueado', $fields, true);

                if (! in_array('creado_en', $fields, true) || ! in_array('actualizado_en', $fields, true)) {
                    $this->useTimestamps = false;
                }
            } catch (\Throwable $e) {
                $this->hasDbLockoutColumns = false;
                $this->useTimestamps       = false;
            }
        }
    }

    protected function isPostgre(): bool
    {
        return strcasecmp((string) ($this->db->DBDriver ?? ''), 'Postgre') === 0;
    }

    /**
     * Find user by username with joined country name.
     *
     * @param string $username Unique username
     * @return array<string, mixed>|null User record with 'pais_nombre', or null if not found
     */
    public function findByUsernameWithCountry(string $username): ?array
    {
        if ($this->isPostgre()) {
            $row = $this->db->table('usuario')
                ->select('usuario.*, pais.pais as pais_nombre')
                ->join('pais', 'pais.id_pais = usuario.id_pais')
                ->where('usuario.usuario', $username)
                ->get()
                ->getRowArray();

            if ($row === null) {
                $row = $this->db->table('usuario')
                    ->select('usuario.*, pais.pais as pais_nombre')
                    ->join('pais', 'pais.id_pais = usuario.id_pais')
                    ->where('LOWER(usuario.usuario)', strtolower($username))
                    ->get()
                    ->getRowArray();
            }

            return $row !== null ? $this->normalizeUserRow($row) : null;
        }

        $row = $this->select('Usuario.*, Pais.Pais as pais_nombre')
            ->join('Pais', 'Pais.Id_pais = Usuario.Id_Pais')
            ->where('Usuario.Usuario', $username)
            ->first();

        return $row !== null ? $this->normalizeUserRow($row) : null;
    }

    /**
     * Increment failed attempt counter and lock account when consecutive attempts reach threshold (>= 3).
     *
     * @param string $codigoEmpleado Employee primary key
     * @param int    $currentAttempts Current failed attempt count
     * @return int   New attempt count
     */
    public function recordFailedAttempt(string $codigoEmpleado, int $currentAttempts): int
    {
        $newAttempts = max(0, $currentAttempts) + 1;
        $isLocked    = ($newAttempts >= 3) ? 1 : 0;
        $now         = date('Y-m-d H:i:s');

        if ($this->hasDbLockoutColumns) {
            $this->update($codigoEmpleado, [
                'intentos_fallidos' => $newAttempts,
                'bloqueado'         => $isLocked,
                'ultimo_fallo'      => $now,
            ]);
        } else {
            $state = $this->getFileLockoutState($codigoEmpleado);
            $state['intentos_fallidos'] = $newAttempts;
            $state['bloqueado']         = $isLocked;
            $state['ultimo_fallo']      = $now;
            $this->saveFileLockoutState($codigoEmpleado, $state);
        }

        return $newAttempts;
    }

    /**
     * Reset failed attempts counter and unlock account upon valid authentication, updating last login.
     *
     * @param string $codigoEmpleado Employee primary key
     * @return bool True if updated successfully
     */
    public function resetFailedAttempts(string $codigoEmpleado): bool
    {
        $now = date('Y-m-d H:i:s');

        if ($this->hasDbLockoutColumns) {
            return (bool) $this->update($codigoEmpleado, [
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
                'ultimo_login'      => $now,
            ]);
        }

        $state = $this->getFileLockoutState($codigoEmpleado);
        $state['intentos_fallidos'] = 0;
        $state['bloqueado']         = 0;
        $state['ultimo_login']      = $now;
        return $this->saveFileLockoutState($codigoEmpleado, $state);
    }

    /**
     * Manually unlock account (administrator intervention), clearing failed attempts and locked status.
     *
     * @param string $codigoEmpleado Employee primary key
     * @return bool True if updated successfully
     */
    public function unlockAccount(string $codigoEmpleado): bool
    {
        if ($this->hasDbLockoutColumns) {
            return (bool) $this->update($codigoEmpleado, [
                'intentos_fallidos' => 0,
                'bloqueado'         => 0,
            ]);
        }

        $state = $this->getFileLockoutState($codigoEmpleado);
        $state['intentos_fallidos'] = 0;
        $state['bloqueado']         = 0;
        return $this->saveFileLockoutState($codigoEmpleado, $state);
    }

    /**
     * Normalize column keys across PostgreSQL (lowercase) and SQLite/MySQL (mixed case)
     * and attach lockout counters when not stored directly as table columns.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected function normalizeUserRow(array $row): array
    {
        $codigoEmpleado = (string) ($row['Codigo_Empleado'] ?? $row['codigo_empleado'] ?? '');
        $nombre         = (string) ($row['Nombre'] ?? $row['nombre'] ?? '');
        $usuario        = (string) ($row['Usuario'] ?? $row['usuario'] ?? '');
        $contrasena     = (string) ($row['Contrasena'] ?? $row['contrasena'] ?? '');
        $idPais         = (int) ($row['Id_Pais'] ?? $row['id_pais'] ?? 0);

        $row['Codigo_Empleado'] = $codigoEmpleado;
        $row['codigo_empleado'] = $codigoEmpleado;
        $row['Nombre']          = $nombre;
        $row['nombre']          = $nombre;
        $row['Usuario']         = $usuario;
        $row['usuario']         = $usuario;
        $row['Contrasena']      = $contrasena;
        $row['contrasena']      = $contrasena;
        $row['Id_Pais']         = $idPais;
        $row['id_pais']         = $idPais;

        if (! array_key_exists('intentos_fallidos', $row) || ! array_key_exists('bloqueado', $row)) {
            $state = $this->getFileLockoutState($codigoEmpleado);
            $row['intentos_fallidos'] = (int) ($state['intentos_fallidos'] ?? 0);
            $row['bloqueado']         = (int) ($state['bloqueado'] ?? 0);
            $row['ultimo_fallo']      = $state['ultimo_fallo'] ?? null;
            $row['ultimo_login']      = $state['ultimo_login'] ?? null;
        }

        return $row;
    }

    protected function getLockoutFilePath(): string
    {
        return WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'login_lockout_state.json';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getFileLockoutState(string $codigoEmpleado): array
    {
        $file = $this->getLockoutFilePath();
        $default = [
            'intentos_fallidos' => 0,
            'bloqueado'         => 0,
            'ultimo_fallo'      => null,
            'ultimo_login'      => null,
        ];

        if (! is_file($file)) {
            return $default;
        }

        $raw  = @file_get_contents($file);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($data) || ! isset($data[$codigoEmpleado]) || ! is_array($data[$codigoEmpleado])) {
            return $default;
        }

        return array_merge($default, $data[$codigoEmpleado]);
    }

    /**
     * @param array<string, mixed> $state
     */
    protected function saveFileLockoutState(string $codigoEmpleado, array $state): bool
    {
        $file = $this->getLockoutFilePath();
        $dir  = dirname($file);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $all = [];
        if (is_file($file)) {
            $raw     = @file_get_contents($file);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                $all = $decoded;
            }
        }

        $all[$codigoEmpleado] = $state;
        return @file_put_contents($file, json_encode($all, JSON_PRETTY_PRINT), LOCK_EX) !== false;
    }
}
