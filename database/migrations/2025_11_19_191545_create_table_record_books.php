<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('record_books', function (Blueprint $table) {
            $table->id();
            $table->string('action');
            $table->decimal('cost', 10, 2)->default(0.00);
            $table->date('date')->nullable();
            $table->foreignId('fund_id')->nullable()->constrained('funds')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('pettycash_id')->nullable()->constrained('petty_cashes')->onUpdate('cascade')->onDelete('restrict');
            $table->decimal('incomes', 10, 2)->default(0.00)->nullable();
            $table->decimal('expenses', 10, 2)->default(0.00)->nullable();
            $table->decimal('total', 10, 2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('record_books');
    }
};
