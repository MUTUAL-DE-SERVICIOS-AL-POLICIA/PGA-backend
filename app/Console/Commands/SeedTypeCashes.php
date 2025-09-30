<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedTypeCashes extends Command
{
    /**
     * El nombre y la firma del comando.
     */
    protected $signature = 'seed:type-cashes';

    /**
     * La descripción del comando.
     */
    protected $description = 'Inserta registros iniciales en la tabla type_cashes';

    /**
     * Ejecuta el comando.
     */
    public function handle()
    {
        $records = [
            ['description' => 'Caja Chica', 'code' => 'CCH'],
            ['description' => 'Reposición', 'code' => 'REP'],
            ['description' => 'Transporte Personal', 'code' => 'TRP'],
        ];

        foreach ($records as $record) {
            $exists = DB::table('type_cashes')
                ->where('description', $record['description'])
                ->orWhere('code', $record['code'])
                ->exists();

            if (!$exists) {
                DB::table('type_cashes')->insert([
                    'description' => $record['description'],
                    'code' => $record['code'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->line("Insertado: {$record['description']} ({$record['code']})");
            } else {
                $this->warn("Ya existe: {$record['description']} ({$record['code']})");
            }
        }

        $this->info('Registros de type_cashes insertados (o ya existentes).');
        return Command::SUCCESS;
    }
}
