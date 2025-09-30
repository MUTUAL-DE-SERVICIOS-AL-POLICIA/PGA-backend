<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetPettyCash extends Command
{
    protected $signature = 'reset:pettycash';
    protected $description = 'Vacía petty_cash_products y petty_cashes en PostgreSQL y reinicia sus IDs';

    public function handle()
    {
        $this->info("Reseteando tablas petty_cash_products y petty_cashes...");

        try {
            DB::statement('TRUNCATE TABLE petty_cash_products, petty_cashes RESTART IDENTITY CASCADE;');

            $this->line("✓ Tablas limpiadas y secuencias reiniciadas a 1.");
            $this->info("Proceso completado con éxito.");
        } catch (\Throwable $e) {
            $this->error(" Error: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
