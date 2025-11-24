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
        $records_cancellations = [
            ['description' => 'Anular por el llenado incorrecto del formulario.'],
            ['description' => 'Anular por tiempo excedido.'],
            ['description' => 'Anular por que supero los recursos entregados.'],
        ];

        $records = [
            ['description' => 'SOLICITUD DE RECURSOS', 'code' => 'CCH'],
            ['description' => 'REEMBOLSO DE GASTO', 'code' => 'REG'],
            ['description' => 'GASTO POR TRANSPORTE', 'code' => 'TRP'],
        ];

        foreach ($records_cancellations as $record) {
            $exists = DB::table('reason_for_cancellations')
                ->where('description', $record['description'])
                ->exists();

            if (!$exists) {
                DB::table('reason_for_cancellations')->insert([
                    'description' => $record['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->line("Insertado: {$record['description']}");
            } else {
                $this->warn("Ya existe: {$record['description']}");
            }
        }

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
