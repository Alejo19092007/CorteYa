<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horarios', function (Blueprint $table) {
            $table->id();
            // Relación 1:N: un barbero tiene varios horarios (uno por día).
            // cascadeOnDelete: los horarios no tienen sentido sin su barbero.
            $table->foreignId('barbero_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana'); // 1 = lunes ... 7 = domingo
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->timestamps();

            // Un solo bloque de trabajo por barbero y día: R1 solo compara contra una ventana.
            $table->unique(['barbero_id', 'dia_semana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horarios');
    }
};
