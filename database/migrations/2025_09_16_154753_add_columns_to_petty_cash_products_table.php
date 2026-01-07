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
        Schema::table('petty_cash_products', function (Blueprint $table) {
            $table->integer('quantity_delivered')->nullable()->after('amount_request');
            $table->string('costDetailsFinal')->nullable()->after('costDetails');
            $table->string('costTotal')->nullable()->after('costFinal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('petty_cash_products', function (Blueprint $table) {
            $table->dropColumn(['quantity_delivered', 'costDetailsFinal', 'costTotal']);
        });
    }
};
