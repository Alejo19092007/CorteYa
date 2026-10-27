<?php

namespace App\Http\Requests;

/**
 * Al editar se piden los mismos datos que al crear (la contraseña pasa a ser opcional).
 */
class UpdateBarberoRequest extends StoreBarberoRequest {}
