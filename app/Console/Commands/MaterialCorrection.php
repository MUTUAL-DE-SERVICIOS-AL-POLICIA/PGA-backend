<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Entrie_Material;
use App\Models\Material;
use Illuminate\Support\Facades\DB;

class MaterialCorrection extends Command
{
    protected $signature = 'reset:material-correction';

    protected $description = 'Resetea y corrige los registros relacionados con materiales de forma segura';

    public function handle()
    {
        DB::transaction(function () {

            // BOLÍGRAFO AZUL
            $this->fixMaterial(
                entryRequestId: 934,
                entryMainId: 1359,
                materialId: 156,
                amount: 1130,
                costTotal: 2203.5
            );

            // LÁPIZ COLOR NEGRO
            $this->fixMaterial(
                entryRequestId: 365,
                entryMainId: 1269,
                materialId: 172,
                amount: 94,
                costTotal: 94
            );

            // MICROPUNTA COLOR AZUL
            $this->fixMaterial(
                entryRequestId: 367,
                entryMainId: 1270,
                materialId: 175,
                amount: 132,
                costTotal: 1056
            );
        });

        $this->info('✅ Corrección de materiales finalizada correctamente.');
        return Command::SUCCESS;
    }

    /**
     * Corrige entradas y stock de un material específico
     */
    private function fixMaterial(
        int $entryRequestId,
        int $entryMainId,
        int $materialId,
        int $amount,
        float $costTotal
    ): void {

        $entryRequest = Entrie_Material::find($entryRequestId);
        if (!$entryRequest) {
            $this->error(" Entrada request ID {$entryRequestId} no encontrada.");
            return;
        }

        $entryRequest->update([
            'request' => $amount
        ]);

        $entryMain = Entrie_Material::find($entryMainId);
        if (!$entryMain) {
            $this->error(" Entrada principal ID {$entryMainId} no encontrada.");
            return;
        }

        $entryMain->update([
            'amount_entries' => $amount,
            'request' => $amount,
            'cost_total' => $costTotal
        ]);

        $material = Material::find($materialId);
        if (!$material) {
            $this->error("Material ID {$materialId} no encontrado.");
            return;
        }

        $material->update([
            'stock' => $amount
        ]);

        $this->info("✔ Material ID {$materialId} corregido correctamente.");
    }
}
