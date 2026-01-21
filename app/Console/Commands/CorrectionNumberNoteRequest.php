<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Note_Entrie;
use App\Models\NoteRequest;

class CorrectionNumberNoteRequest extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'notesreq:correct-number';

    /**
     * The console command description.
     */
    protected $description = 'Corrige la numeración de las notas de solicitud por gestión.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando corrección de numeración de notas...');

        DB::beginTransaction();

        try {

            $managements = NoteRequest::select('management_id')
                ->distinct()
                ->orderBy('management_id')
                ->pluck('management_id');

            foreach ($managements as $managementId) {

                $acceptedNotes = NoteRequest::where('management_id', $managementId)
                    ->where('state', 'Aceptado')
                    ->orderBy('received_on_date')
                    ->orderBy('id')
                    ->get();

                $counter = 1;

                foreach ($acceptedNotes as $note) {
                    $note->number_note = $counter++;
                    $note->save();
                }

                $this->line("✔ Gestión {$managementId} corregida");
            }

            DB::commit();

            $this->info('Corrección finalizada correctamente ✅');
        } catch (\Exception $e) {

            DB::rollBack();

            logger('Error en corrección de numeración', [
                'error' => $e->getMessage()
            ]);

            $this->error('❌ Error durante la corrección');
        }

        return Command::SUCCESS;
    }
}
