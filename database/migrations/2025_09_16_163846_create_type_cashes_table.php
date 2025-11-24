<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('type_cashes', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->string('code')->unique();
            $table->timestamps();
        });

        // Agregar la relación a petty_cashes
        Schema::table('petty_cashes', function (Blueprint $table) {
            $table->foreignId('type_cash_id')
                ->constrained('type_cashes')
                ->onDelete('restrict')
                ->onUpdate('cascade')
                ->after('fund_id'); // opcional: posicionar después de fund_id
        });
    }

    public function down(): void
    {
        Schema::table('petty_cashes', function (Blueprint $table) {
            $table->dropForeign(['type_cash_id']);
            $table->dropColumn('type_cash_id');
        });

        Schema::dropIfExists('type_cashes');
    }
};
