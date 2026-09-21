<?php

namespace App\Console\Commands;

use App\Services\Order\TestOrderImportGeneratorService;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Development helper: build a reseller order-import XLSX from real local catalog data.
 */
class GenerateTestOrderImportCommand extends Command
{
    protected $signature = 'orders:generate-test-import
                            {--rows=20 : Number of import data rows to generate}
                            {--output=storage/app/test-import-orders.xlsx : Output path (relative to app base or absolute)}
                            {--reseller-phone=0799000001 : Existing reseller owner phone used for market/supplier access}';

    protected $description = 'Generate a development-only Excel file for testing reseller bulk order import (real DB variants only)';

    public function handle(TestOrderImportGeneratorService $generator): int
    {
        $rows = (int) $this->option('rows');
        $output = (string) $this->option('output');
        $resellerPhone = trim((string) $this->option('reseller-phone'));

        try {
            $result = $generator->generate($rows, $output, $resellerPhone);
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Failed to generate test import file: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Test order import spreadsheet generated.');
        $this->line('Output path: '.$result['absolute_path']);
        $this->line('Generated rows: '.$result['row_count']);
        $this->line('Unique variants: '.$result['unique_variants']);
        $this->line('Locked-price rows: '.$result['locked_rows']);
        $this->line('Unlocked-price rows: '.$result['unlocked_rows']);
        $this->line('Markets used: '.($result['markets'] !== [] ? implode(', ', $result['markets']) : '(none)'));
        $this->line('Suppliers used: '.($result['suppliers'] !== [] ? implode(', ', $result['suppliers']) : '(none)'));
        $this->line('Reseller phone: '.$result['reseller_phone']);

        return self::SUCCESS;
    }
}
