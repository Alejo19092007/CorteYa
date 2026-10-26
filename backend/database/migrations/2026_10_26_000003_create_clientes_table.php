<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            // Número de WhatsApp con indicativo 57. Es la identidad del cliente en el bot.
            $table->string('telefono', 15)->unique();
            // Contador para la regla R5 (fidelidad)
            $table->unsignedInteger('turnos_atendidos')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
