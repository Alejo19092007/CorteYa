<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla pivote de la relación N:M turnos–servicios.
        Schema::create('turno_servicio', function (Blueprint $table) {
            // cascadeOnDelete: si se borra el turno, se borran sus líneas de servicio.
            $table->foreignId('turno_id')->constrained()->cascadeOnDelete();
            // restrictOnDelete: no se borra un servicio que ya se usó en algún turno.
            $table->foreignId('servicio_id')->constrained()->restrictOnDelete();
            // Se guardan el precio y la duración AL MOMENTO de reservar:
            // un cambio de precio posterior no altera los turnos pasados.
            $table->decimal('precio', 10, 2);
            $table->unsignedSmallInteger('duracion_min');

            // Un servicio no se repite dentro del mismo turno.
            $table->primary(['turno_id', 'servicio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turno_servicio');
    }
};
