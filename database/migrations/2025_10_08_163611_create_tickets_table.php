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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('from');
            $table->string('to');
            $table->decimal('cost', 10, 2)->default(0.00);
            $table->foreignId('id_permission')->constrained('public.departures')->onDelete('restrict')->onUpdate('cascade');
            $table->string('ticket_invoice');
            $table->date('permission_day');
            $table->foreignId('pettycash_id')->constrained('petty_cashes')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('group_id')->nullable()->constrained('groups')->onUpdate('cascade')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('funds', function (Blueprint $table){
            $table->string('type')->nullable();
        });
        
        DB::statement('ALTER TABLE funds ALTER COLUMN reception_date DROP NOT NULL;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');

         Schema::table('funds', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
