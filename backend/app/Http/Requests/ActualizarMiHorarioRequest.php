<?php

namespace App\Http\Requests;

/**
 * El barbero cambia SOLO su horario. Reutiliza las reglas de los horarios del formulario del dueño
 * (días que trabaja, inicio y fin coherentes, al menos un día) pero NO acepta nombre, correo, contraseña,
 * teléfono ni "activo": aunque alguien los mande en la petición, se descartan.
 */
class ActualizarMiHorarioRequest extends StoreBarberoRequest
{
    protected function prepareForValidation(): void {}

    public function rules(): array
    {
        return array_intersect_key(parent::rules(), array_flip(['horarios', 'horarios.*.trabaja', 'horarios.*.inicio', 'horarios.*.fin']));
    }

    /** @return array<int, array{hora_inicio: string, hora_fin: string}> */
    public function horarios(): array
    {
        return $this->horariosValidados();
    }
}
