<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'telefono',
        'turnos_atendidos',
    ];

    protected function casts(): array
    {
        return [
            'turnos_atendidos' => 'integer',
        ];
    }

    /**
     * Deja el teléfono como se guarda: solo dígitos y con indicativo 57 ("300 123 4567" -> "573001234567").
     * Devuelve null si no tiene la forma de un celular colombiano.
     */
    public static function normalizarTelefono(string $telefono): ?string
    {
        $digitos = preg_replace('/\D/', '', $telefono);

        if (strlen($digitos) === 10) {
            $digitos = '57'.$digitos;
        }

        return preg_match('/^57[0-9]{10}$/', $digitos) ? $digitos : null;
    }

    /** 1:N: historial de turnos del cliente. */
    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class);
    }
}
