<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un cliente de mostrador puede registrarse solo con el nombre. El teléfono sigue siendo ÚNICO
     * cuando existe (la llave única se conserva: MySQL y SQLite permiten varios NULL en una columna única).
     * El bot sigue identificando a sus clientes por teléfono: ellos siempre lo tienen.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('telefono', 15)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Para volver a exigir teléfono, a los clientes sin él se les pone uno de relleno que no choca con ninguno real
        DB::table('clientes')->whereNull('telefono')->orderBy('id')->each(function ($cliente) {
            DB::table('clientes')->where('id', $cliente->id)->update(['telefono' => '570000'.str_pad((string) $cliente->id, 6, '0', STR_PAD_LEFT)]);
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->string('telefono', 15)->nullable(false)->change();
        });
    }
};
