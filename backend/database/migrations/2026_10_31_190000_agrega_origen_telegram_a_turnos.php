<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El bot ahora atiende por Telegram (Meta no entregó el código de verificación para WhatsApp).
     * "origen" pasa a admitir: panel, telegram y whatsapp (el adaptador de WhatsApp sigue disponible).
     */
    public function up(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            $table->enum('origen', ['panel', 'telegram', 'whatsapp'])->change();
        });

        // Los turnos que ya existían entraron por el bot (eran datos de demo): ahora ese canal es Telegram
        DB::table('turnos')->where('origen', 'whatsapp')->update(['origen' => 'telegram']);
    }

    public function down(): void
    {
        DB::table('turnos')->where('origen', 'telegram')->update(['origen' => 'whatsapp']);

        Schema::table('turnos', function (Blueprint $table) {
            $table->enum('origen', ['panel', 'whatsapp'])->change();
        });
    }
};
