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
        Schema::create('reason_for_cancellations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('description');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE petty_cashes ALTER COLUMN request_date DROP NOT NULL;');

        Schema::table('petty_cashes', function (Blueprint $table) {
            $table->foreignId('reason_for_cancellation_id')
                ->nullable()
                ->constrained('reason_for_cancellations')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('petty_cashes', function (Blueprint $table) {
            $table->dropForeign(['reason_for_cancellation_id']);
            $table->dropColumn('reason_for_cancellation_id');
        });

        DB::statement('ALTER TABLE petty_cashes ALTER COLUMN request_date SET NOT NULL;');

        Schema::dropIfExists('reason_for_cancellations');
    }
};
