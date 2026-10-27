<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Valida un barbero (cuenta + perfil + horarios) al crearlo o editarlo
 * (UpdateBarberoRequest hereda de esta clase).
 */
class StoreBarberoRequest extends FormRequest
{
    private const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    public function authorize(): bool
    {
        return true; // el acceso ya lo controla el middleware "role:admin" de la ruta
    }

    /**
     * Normaliza antes de validar: el teléfono se guarda con indicativo 57 aunque el administrador
     * escriba solo los 10 dígitos, y el interruptor "activo" se vuelve booleano.
     */
    protected function prepareForValidation(): void
    {
        $digitos = preg_replace('/\D/', '', (string) $this->input('telefono'));

        if (strlen($digitos) === 10) {
            $digitos = '57'.$digitos;
        }

        $this->merge([
            'telefono' => $digitos,
            'activo' => $this->boolean('activo'),
        ]);
    }

    public function rules(): array
    {
        $esEdicion = $this->route('barbero') !== null;
        $usuarioId = $this->route('barbero')?->user_id;

        return [
            'name' => ['required', 'string', 'max:100'],
            // Al editar, el correo puede repetirse consigo mismo (se ignora la cuenta del propio barbero)
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($usuarioId)],
            // Al crear la contraseña es obligatoria; al editar, vacía significa "no cambiarla"
            'password' => [$esEdicion ? 'nullable' : 'required', 'string', 'min:8', 'max:100'],
            'telefono' => ['required', 'regex:/^57[0-9]{10}$/'],
            'activo' => ['boolean'],
            'horarios' => ['required', 'array'],
            'horarios.*.trabaja' => ['nullable', 'boolean'],
            'horarios.*.inicio' => ['nullable', 'date_format:H:i'],
            'horarios.*.fin' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'telefono.regex' => 'El teléfono debe ser un celular colombiano de 10 dígitos (ej. 3001234567).',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'horarios.*.inicio' => 'hora de inicio',
            'horarios.*.fin' => 'hora de fin',
        ];
    }

    /** Reglas que dependen de varios campos a la vez: cada día que trabaja necesita inicio y fin coherentes. */
    public function after(): array
    {
        return [function (Validator $validator) {
            $horarios = $this->input('horarios', []);
            $diasQueTrabaja = 0;

            foreach (self::DIAS as $dia => $nombre) {
                $fila = $horarios[$dia] ?? [];

                if (empty($fila['trabaja'])) {
                    continue;
                }

                $diasQueTrabaja++;
                $inicio = $fila['inicio'] ?? null;
                $fin = $fila['fin'] ?? null;

                if (! $inicio || ! $fin) {
                    $validator->errors()->add("horarios.{$dia}", "{$nombre}: indica la hora de inicio y la de fin.");
                } elseif ($fin <= $inicio) {
                    $validator->errors()->add("horarios.{$dia}", "{$nombre}: la hora de fin debe ser posterior a la de inicio.");
                }
            }

            if ($diasQueTrabaja === 0) {
                $validator->errors()->add('horarios', 'El barbero debe trabajar al menos un día.');
            }
        }];
    }

    /**
     * Datos listos para BarberoService. Los horarios quedan como dia_semana => jornada,
     * solo para los días que trabaja.
     *
     * @return array{name: string, email: string, password: ?string, telefono: string, activo: bool, horarios: array<int, array{hora_inicio: string, hora_fin: string}>}
     */
    public function datos(): array
    {
        $datos = $this->validated();
        $horarios = [];

        foreach ($datos['horarios'] as $dia => $fila) {
            if (array_key_exists((int) $dia, self::DIAS) && ! empty($fila['trabaja'])) {
                $horarios[(int) $dia] = ['hora_inicio' => $fila['inicio'], 'hora_fin' => $fila['fin']];
            }
        }

        return [
            'name' => $datos['name'],
            'email' => $datos['email'],
            'password' => $datos['password'] ?? null,
            'telefono' => $datos['telefono'],
            'activo' => (bool) $datos['activo'],
            'horarios' => $horarios,
        ];
    }
}
