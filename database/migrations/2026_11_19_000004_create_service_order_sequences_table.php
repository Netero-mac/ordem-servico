<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contador por ano para gerar números de OS sem colisão (OS-2026-0001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_order_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_sequences');
    }
};
