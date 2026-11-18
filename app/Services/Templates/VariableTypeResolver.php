<?php

namespace App\Services\Templates;

use Illuminate\Support\Str;

class VariableTypeResolver
{
    /**
     * Converts raw variable names extracted from a .docx template into a structured schema.
     *
     * Rules (configurable in config/suratinaja.php, marked TBD in FEATURES.md §4.2):
     * - System variables (nomor_surat, tanggal_surat, perihal) are tagged as `system: true`
     *   and excluded from dynamic user inputs because they are supplied by the letter header.
     * - Names containing `.` (e.g. `lampiran.nama_barang`) are grouped into a single `repeater`
     *   entry with `columns: [{name, label}, ...]`.
     * - Names starting with `tgl_` or `tanggal_` become `date`.
     * - Everything else becomes `text`.
     *
     * @param  array<int, string>  $rawVariables
     * @return array<int, array<string, mixed>>
     */
    public function resolve(array $rawVariables): array
    {
        $systemVars = config('suratinaja.templates.system_variables', ['nomor_surat', 'tanggal_surat', 'perihal']);
        $datePrefixes = config('suratinaja.templates.date_prefixes', ['tgl_', 'tanggal_']);
        $sep = config('suratinaja.templates.repeater_separator', '.');

        $fields = [];
        $repeaters = [];
        $seen = [];

        foreach ($rawVariables as $raw) {
            $name = trim((string) $raw);
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;

            // Ignore closing block tags like /BULK or #1 suffixes
            if (str_starts_with($name, '/') || str_contains($name, '#')) {
                continue;
            }

            if (in_array($name, $systemVars, true)) {
                $fields[] = [
                    'name' => $name,
                    'label' => $this->humanize($name),
                    'type' => 'system',
                    'required' => false,
                ];
                continue;
            }

            if (str_contains($name, $sep)) {
                [$group, $col] = explode($sep, $name, 2);
                $group = trim($group);
                $col = trim($col);
                if ($group !== '' && $col !== '') {
                    $repeaters[$group][$col] = [
                        'name' => $col,
                        'token' => "{$group}{$sep}{$col}",
                        'label' => $this->humanize($col),
                    ];
                }
                continue;
            }

            $type = 'text';
            foreach ($datePrefixes as $prefix) {
                if (str_starts_with(strtolower($name), $prefix)) {
                    $type = 'date';
                    break;
                }
            }

            $fields[] = [
                'name' => $name,
                'label' => $this->humanize($name),
                'type' => $type,
                'required' => true,
            ];
        }

        foreach ($repeaters as $group => $columns) {
            $fields[] = [
                'name' => $group,
                'label' => $this->humanize($group),
                'type' => 'repeater',
                'required' => false,
                'columns' => array_values($columns),
            ];
        }

        return $fields;
    }

    /**
     * Returns only user-editable fields (excludes `system` type).
     *
     * @param  array<int, array<string, mixed>>  $schema
     * @return array<int, array<string, mixed>>
     */
    public function editableFields(array $schema): array
    {
        return array_values(array_filter($schema, fn (array $f) => ($f['type'] ?? 'text') !== 'system'));
    }

    public function humanize(string $identifier): string
    {
        return Str::headline(str_replace(['.', '_', '-'], ' ', $identifier));
    }
}
