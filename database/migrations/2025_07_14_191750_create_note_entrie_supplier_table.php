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
        Schema::create('note_entrie_supplier', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('note_entrie_id');
            $table->unsignedBigInteger('supplier_id');
            $table->string('invoice_number');

            $table->foreign('note_entrie_id')->references('id')->on('note_entries')->onDelete('cascade');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('note_entrie_supplier');
    }
};
