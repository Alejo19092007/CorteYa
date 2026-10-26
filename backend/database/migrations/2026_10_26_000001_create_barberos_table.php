<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barberos', function (Blueprint $table) {
            $table->id();
            // Relación 1:1 con users: el unique garantiza que un usuario sea un solo barbero.
            // restrictOnDelete: no se borra un usuario que ya es barbero (se desactiva con "activo").
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('telefono', 15);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barberos');
    }
};
