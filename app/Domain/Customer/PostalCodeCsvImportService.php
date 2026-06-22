<?php

namespace App\Domain\Customer;

use App\Models\PostalCodeSettlement;
use Illuminate\Support\Arr;
use RuntimeException;
use SplFileObject;

class PostalCodeCsvImportService
{
    /**
     * @return array{summary: array{total_rows: int, valid_rows: int, skipped_rows: int, imported_rows: int, postal_codes: int, settlements: int}}
     */
    public function import(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("No se encontró el archivo: {$path}");
        }

        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

        $headerLine = $file->fgets();
        $delimiter = $this->detectDelimiter($headerLine);
        $headers = $this->normalizeHeaders(str_getcsv($headerLine, $delimiter));
        $missing = array_diff(['d_codigo', 'd_asenta', 'D_mnpio', 'd_estado'], $headers);

        if ($missing !== []) {
            throw new RuntimeException('Faltan columnas requeridas: '.implode(', ', $missing));
        }

        $rows = [];
        $validRows = 0;
        $totalRows = 0;
        $importedRows = 0;
        $postalCodes = [];
        $settlements = [];

        while (! $file->eof()) {
            $line = $file->fgets();

            if (trim($line) === '') {
                continue;
            }

            $totalRows++;
            $data = $this->row($headers, str_getcsv($line, $delimiter));

            if (! $data) {
                continue;
            }

            $validRows++;
            $postalCodes[$data['postal_code']] = true;
            $settlements[$data['postal_code'].'|'.$data['settlement']] = true;
            $rows[] = $data;

            if (count($rows) >= 500) {
                $importedRows += $this->upsert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            $importedRows += $this->upsert($rows);
        }

        return [
            'summary' => [
                'total_rows' => $totalRows,
                'valid_rows' => $validRows,
                'skipped_rows' => $totalRows - $validRows,
                'imported_rows' => $importedRows,
                'postal_codes' => count($postalCodes),
                'settlements' => count($settlements),
            ],
        ];
    }

    private function detectDelimiter(string $line): string
    {
        return collect([',', '|', ';', "\t"])
            ->sortByDesc(fn (string $delimiter): int => substr_count($line, $delimiter))
            ->first() ?? ',';
    }

    /**
     * @param  list<string>  $headers
     * @return list<string>
     */
    private function normalizeHeaders(array $headers): array
    {
        return array_map(
            fn (string $header): string => ltrim(trim($header, " \t\n\r\0\x0B\"'"), "\xef\xbb\xbf"),
            $headers,
        );
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string|null>  $values
     * @return array<string, string|null>|null
     */
    private function row(array $headers, array $values): ?array
    {
        $source = array_combine($headers, array_pad($values, count($headers), null));

        if (! is_array($source)) {
            return null;
        }

        $postalCode = str_pad($this->value($source, 'd_codigo'), 5, '0', STR_PAD_LEFT);
        $settlement = $this->value($source, 'd_asenta');

        if (! preg_match('/^\d{5}$/', $postalCode) || $settlement === '') {
            return null;
        }

        return [
            'postal_code' => $postalCode,
            'settlement' => $settlement,
            'settlement_type' => $this->nullableValue($source, 'd_tipo_asenta'),
            'municipality' => $this->value($source, 'D_mnpio'),
            'state' => $this->value($source, 'd_estado'),
            'city' => $this->nullableValue($source, 'd_ciudad'),
            'zone' => $this->nullableValue($source, 'd_zona'),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * @param  array<string, string|null>  $source
     */
    private function value(array $source, string $key): string
    {
        return trim((string) Arr::get($source, $key, ''));
    }

    /**
     * @param  array<string, string|null>  $source
     */
    private function nullableValue(array $source, string $key): ?string
    {
        $value = $this->value($source, $key);

        return $value === '' ? null : $value;
    }

    /**
     * @param  list<array<string, string|null>>  $rows
     */
    private function upsert(array $rows): int
    {
        PostalCodeSettlement::query()->upsert(
            $rows,
            ['postal_code', 'settlement'],
            ['settlement_type', 'municipality', 'state', 'city', 'zone', 'updated_at'],
        );

        return count($rows);
    }
}
