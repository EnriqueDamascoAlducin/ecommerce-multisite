<?php

namespace App\Console\Commands;

use App\Models\PostalCodeSettlement;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use SplFileObject;

#[Signature('postal-codes:import {file : Ruta del CSV SEPOMEX normalizado en UTF-8}')]
#[Description('Importa colonias por código postal desde un CSV SEPOMEX')]
class ImportPostalCodesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path)) {
            $this->error("No se encontró el archivo: {$path}");

            return self::FAILURE;
        }

        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

        $headerLine = $file->fgets();
        $delimiter = $this->detectDelimiter($headerLine);
        $headers = $this->normalizeHeaders(str_getcsv($headerLine, $delimiter));

        $required = ['d_codigo', 'd_asenta', 'D_mnpio', 'd_estado'];
        $missing = array_diff($required, $headers);

        if ($missing !== []) {
            $this->error('Faltan columnas requeridas: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $rows = [];
        $imported = 0;

        while (! $file->eof()) {
            $line = $file->fgets();

            if (trim($line) === '') {
                continue;
            }

            $data = $this->row($headers, str_getcsv($line, $delimiter));

            if (! $data) {
                continue;
            }

            $rows[] = $data;

            if (count($rows) >= 500) {
                $imported += $this->upsert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            $imported += $this->upsert($rows);
        }

        $this->info("Códigos postales importados: {$imported}");

        return self::SUCCESS;
    }

    private function detectDelimiter(string $line): string
    {
        return collect([',', '|', ';', "\t"])
            ->sortByDesc(fn (string $delimiter) => substr_count($line, $delimiter))
            ->first() ?? ',';
    }

    /**
     * @param  list<string>  $headers
     * @return list<string>
     */
    private function normalizeHeaders(array $headers): array
    {
        return array_map(fn (string $header) => trim($header, " \t\n\r\0\x0B\"'"), $headers);
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
