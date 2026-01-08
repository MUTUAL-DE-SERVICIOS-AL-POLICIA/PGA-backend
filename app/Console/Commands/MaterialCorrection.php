<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Entrie_Material;
use App\Models\Material;
use Illuminate\Support\Facades\DB;

class MaterialCorrection extends Command
{
    protected $signature = 'reset:material-correction 
                            {materialId : ID del Material} 
                            {entryId : ID de la entrada de material} 
                            {stock : Nuevo stock para el Material} 
                            {request : Nuevo request para Entrie_Material}';

    /**
     * La descripción del comando.
     */
    protected $description = 'Resetea y corrige los registros relacionados con materiales de forma segura';

    /**
     * Ejecuta el comando.
     */
    public function handle()
    {
        $materialId = $this->argument('materialId');
        $entryId = $this->argument('entryId');
        $stock = $this->argument('stock');
        $request = $this->argument('request');

        try {
            DB::transaction(function () use ($materialId, $entryId, $stock, $request) {
                $material = Material::find($materialId);
                if (!$material) {
                    $this->error("Material con ID {$materialId} no encontrado.");
                    return;
                }
                $material->update(['stock' => $stock]);

                $entry = Entrie_Material::find($entryId);
                if (!$entry) {
                    $this->error("Entrada de material con ID {$entryId} no encontrada.");
                    return;
                }
                $entry->update(['request' => $request]);
            });

            $this->info("Registros modificados correctamente ✅");

        } catch (\Exception $e) {
            $this->error("Ocurrió un error: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
