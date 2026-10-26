<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete: no se puede borrar un barbero o cliente que tenga turnos (historial).
            $table->foreignId('barbero_id')->constrained()->restrictOnDelete();
            $table->foreignId('cliente_id')->constrained()->restrictOnDelete();
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_fin');          // calculada por el Service (R2)
            $table->decimal('precio_total', 10, 2); // calculado por el Service (R2, R5)
            $table->enum('estado', ['pendiente', 'confirmado', 'atendido', 'cancelado', 'no_asistio'])
                ->default('pendiente');
            $table->enum('origen', ['panel', 'whatsapp']);
            $table->timestamps();

            // R1 busca los turnos de un barbero en una fecha: este índice acelera esa consulta.
            $table->index(['barbero_id', 'fecha']);
            // Los filtros del listado y el dashboard consultan por fecha y estado.
            $table->index(['fecha', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnos');
    }
};
