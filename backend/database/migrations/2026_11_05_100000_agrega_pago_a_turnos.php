<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registro MANUAL del pago de un turno (no es un cobro en línea: el sistema solo anota que el cliente ya pagó).
     *   - pagado_en: cuándo se registró el pago. NULL = todavía no ha pagado ("por cobrar").
     *   - metodo_pago: cómo pagó.
     * Es independiente del estado: un turno puede estar atendido y por cobrar.
     */
    public function up(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            $table->dateTime('pagado_en')->nullable()->after('origen');
            $table->enum('metodo_pago', ['efectivo', 'transferencia', 'nequi', 'daviplata', 'tarjeta'])->nullable()->after('pagado_en');

            // El dashboard suma los pagos de un rango de fechas
            $table->index('pagado_en');
        });

        // Hasta ahora los ingresos eran "los turnos atendidos". Para que los números que ya existen no
        // desaparezcan del dashboard, los turnos atendidos anteriores quedan como pagados en efectivo,
        // en el momento en que terminaron.
        DB::table('turnos')->where('estado', 'atendido')->orderBy('id')->chunkById(500, function ($turnos) {
            foreach ($turnos as $turno) {
                DB::table('turnos')->where('id', $turno->id)->update([
                    'pagado_en' => Carbon::parse(Carbon::parse($turno->fecha)->toDateString().' '.$turno->hora_fin),
                    'metodo_pago' => 'efectivo',
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            $table->dropIndex(['pagado_en']);
            $table->dropColumn(['pagado_en', 'metodo_pago']);
        });
    }
};
