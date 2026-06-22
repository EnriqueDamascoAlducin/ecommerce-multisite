<?php

namespace App\Console\Commands;

use App\Domain\Customer\PostalCodeCsvImportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('postal-codes:import {file : Ruta del CSV SEPOMEX normalizado en UTF-8}')]
#[Description('Importa colonias por código postal desde un CSV SEPOMEX')]
class ImportPostalCodesCommand extends Command
{
    public function __construct(private readonly PostalCodeCsvImportService $imports)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = (string) $this->argument('file');

        try {
            $result = $this->imports->import($path);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Códigos postales importados: {$result['summary']['imported_rows']}");

        return self::SUCCESS;
    }
}
