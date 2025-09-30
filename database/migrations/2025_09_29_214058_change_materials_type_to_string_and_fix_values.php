<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->string('type_tmp')->nullable();
        });

        DB::statement("UPDATE materials SET type_tmp = type::text");

        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        DB::statement('ALTER TABLE materials RENAME COLUMN type_tmp TO type');

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->enum('type_old', ['Caja Chica, Fondo de Avance, Reposiciones', 'Almacen'])->nullable();
        });

        DB::statement("UPDATE materials SET type_old = type");

        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        DB::statement('ALTER TABLE materials RENAME COLUMN type_old TO type');
    }
};
