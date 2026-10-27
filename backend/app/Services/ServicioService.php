<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Servicio;
use App\Repositories\ServicioRepository;

/**
 * Lógica de los servicios de la barbería. Lo usan el panel y (en la v0.3) la API.
 */
class ServicioService
{
    public function __construct(private readonly ServicioRepository $servicios) {}

    public function crear(array $datos): Servicio
    {
        return $this->servicios->crear($datos);
    }

    /**
     * Cambiar el precio o la duración NO altera los turnos ya reservados: cada turno guarda
     * el valor que tenía el servicio al reservar (tabla pivote turno_servicio).
     */
    public function actualizar(Servicio $servicio, array $datos): Servicio
    {
        return $this->servicios->actualizar($servicio, $datos);
    }

    /** Activa o desactiva el servicio. Un servicio inactivo no se ofrece en el panel ni en el bot. */
    public function alternarActivo(Servicio $servicio): Servicio
    {
        return $this->servicios->actualizar($servicio, ['activo' => ! $servicio->activo]);
    }

    /**
     * Un servicio que ya se usó en algún turno NO se borra (se perdería el historial y la base
     * de datos lo impide): hay que desactivarlo. Solo se elimina si nunca se usó.
     */
    public function eliminar(Servicio $servicio): void
    {
        if ($this->servicios->estaEnUso($servicio)) {
            throw new ReglaNegocioException(
                'Este servicio ya se usó en turnos y no se puede eliminar. Desactívalo para que deje de ofrecerse.'
            );
        }

        $this->servicios->eliminar($servicio);
    }
}
