<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('materials')
            ->where('type', 'Caja Chica, Fondo de Avance, Reposiciones')
            ->update([
                'type' => 'Caja Chica, Fondo en Avance y Reposiciones',
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir el cambio si se hace rollback
        DB::table('materials')
            ->where('type', 'Caja Chica, Fondo en Avance y Reposiciones')
            ->update([
                'type' => 'Caja Chica, Fondo de Avance, Reposiciones',
                'updated_at' => now(),
            ]);
    }
};
