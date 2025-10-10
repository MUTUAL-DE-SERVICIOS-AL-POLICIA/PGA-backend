<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Note_Entrie;

class MigrateNoteEntrieSuppliers extends Command
{
    protected $signature = 'migrate:note-suppliers';
    protected $description = 'Migrar suppliers_id y invoice_number desde note_entries a la tabla pivot';

    public function handle()
    {
        $notes = Note_Entrie::whereNotNull('suppliers_id')->get();

        if ($notes->isEmpty()) {
            $this->warn("No hay registros con suppliers_id para migrar.");
            return 0;
        }

        $this->info("Migrando {$notes->count()} notas de entrada...");

        foreach ($notes as $note) {
            DB::table('note_entrie_supplier')->insert([
                'note_entrie_id' => $note->id,
                'supplier_id' => $note->suppliers_id,
                'invoice_number' => $note->invoice_number,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->line("✓ Nota ID {$note->id} → Proveedor ID {$note->suppliers_id} → Factura: {$note->invoice_number}");
        }

        $this->info(" Migración completada con éxito.");
        return 0;
    }
}
