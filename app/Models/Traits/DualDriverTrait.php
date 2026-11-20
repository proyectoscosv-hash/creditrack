<?php

namespace App\Models\Traits;

trait DualDriverTrait
{
    protected function isPostgre(): bool
    {
        return strcasecmp((string) ($this->db->DBDriver ?? ''), 'Postgre') === 0;
    }

    protected function initDualDriverTable(string $canonicalTable, string $canonicalPk): void
    {
        if ($this->isPostgre()) {
            $this->table      = strtolower($canonicalTable);
            $this->primaryKey = strtolower($canonicalPk);
        } else {
            $this->table      = $canonicalTable;
            $this->primaryKey = $canonicalPk;
        }
    }

    protected function tbl(string $canonicalTable): string
    {
        return $this->isPostgre() ? strtolower($canonicalTable) : $canonicalTable;
    }

    protected function col(string $canonicalColumn): string
    {
        return $this->isPostgre() ? strtolower($canonicalColumn) : $canonicalColumn;
    }

    /**
     * Map input payload keys (either mixed-case or lowercase) to the active driver's canonical column casing.
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $columnMap Map of lowercase_col => MixedCaseCol
     *
     * @return array<string, mixed>
     */
    protected function mapWriteColumns(array $data, array $columnMap): array
    {
        $isPg   = $this->isPostgre();
        $mapped = [];

        foreach ($columnMap as $lower => $mixed) {
            $targetKey = $isPg ? $lower : $mixed;
            if (array_key_exists($mixed, $data)) {
                $mapped[$targetKey] = $data[$mixed];
            } elseif (array_key_exists($lower, $data)) {
                $mapped[$targetKey] = $data[$lower];
            }
        }

        // Preserve any extra fields (e.g. timestamps already keyed accurately) not in $columnMap
        foreach ($data as $key => $val) {
            $lowerKey = strtolower((string) $key);
            if (! array_key_exists($lowerKey, $columnMap)) {
                $mapped[$key] = $val;
            }
        }

        return $mapped;
    }

    /**
     * Populate both mixed-case and lowercase keys on returned rows, casting ints and floats consistently.
     *
     * @param array<string, mixed> $row
     * @param list<string>         $mixedCaseKeys
     * @param list<string>         $intKeys
     * @param list<string>         $floatKeys
     *
     * @return array<string, mixed>
     */
    protected function normalizeDualCaseRow(
        array $row,
        array $mixedCaseKeys,
        array $intKeys = [],
        array $floatKeys = [],
    ): array {
        $intLookup   = array_flip(array_map('strtolower', $intKeys));
        $floatLookup = array_flip(array_map('strtolower', $floatKeys));

        foreach ($mixedCaseKeys as $mixedKey) {
            $lowerKey = strtolower($mixedKey);
            $hasMixed = array_key_exists($mixedKey, $row);
            $hasLower = array_key_exists($lowerKey, $row);

            if (! $hasMixed && ! $hasLower) {
                continue;
            }

            $value = $hasMixed ? $row[$mixedKey] : $row[$lowerKey];

            if ($value !== null) {
                if (isset($intLookup[$lowerKey])) {
                    $value = (int) $value;
                } elseif (isset($floatLookup[$lowerKey])) {
                    $value = (float) $value;
                }
            }

            $row[$mixedKey] = $value;
            $row[$lowerKey] = $value;
        }

        return $row;
    }

    /**
     * Helper for CI4 afterFind hook to normalize single or multiple rows.
     *
     * @param array<string, mixed> $eventData
     * @param list<string>         $mixedCaseKeys
     * @param list<string>         $intKeys
     * @param list<string>         $floatKeys
     *
     * @return array<string, mixed>
     */
    protected function handleAfterFind(
        array $eventData,
        array $mixedCaseKeys,
        array $intKeys = [],
        array $floatKeys = [],
    ): array {
        if (! isset($eventData['data']) || $eventData['data'] === null || $eventData['data'] === []) {
            return $eventData;
        }

        if (! empty($eventData['singleton'])) {
            if (is_array($eventData['data'])) {
                $eventData['data'] = $this->normalizeDualCaseRow($eventData['data'], $mixedCaseKeys, $intKeys, $floatKeys);
            }

            return $eventData;
        }

        if (is_array($eventData['data'])) {
            $normalized = [];
            foreach ($eventData['data'] as $key => $row) {
                $normalized[$key] = is_array($row)
                    ? $this->normalizeDualCaseRow($row, $mixedCaseKeys, $intKeys, $floatKeys)
                    : $row;
            }
            $eventData['data'] = $normalized;
        }

        return $eventData;
    }
}
