<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const MAX_INTENTOS = 5;

    public function authorize(): bool
    {
        return true; // cualquiera puede intentar iniciar sesión
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'correo',
            'password' => 'contraseña',
        ];
    }

    /**
     * Intenta iniciar sesión con límite de intentos (protege contra adivinar contraseñas).
     *
     * El mensaje de error es el mismo si el correo no existe o si la clave es incorrecta:
     * así nadie puede averiguar qué correos están registrados.
     */
    public function authenticate(): void
    {
        $clave = $this->claveDeIntentos();

        if (RateLimiter::tooManyAttempts($clave, self::MAX_INTENTOS)) {
            $segundos = RateLimiter::availableIn($clave);

            throw ValidationException::withMessages([
                'email' => "Demasiados intentos. Intenta de nuevo en {$segundos} segundos.",
            ]);
        }

        if (! Auth::attempt($this->only('email', 'password'))) {
            RateLimiter::hit($clave); // cuenta el intento fallido (se olvida a los 60 s)

            throw ValidationException::withMessages([
                'email' => 'Correo o contraseña incorrectos.',
            ]);
        }

        RateLimiter::clear($clave);
    }

    /** Un contador por combinación correo + IP. */
    private function claveDeIntentos(): string
    {
        return Str::lower($this->string('email')->toString()).'|'.$this->ip();
    }
}
