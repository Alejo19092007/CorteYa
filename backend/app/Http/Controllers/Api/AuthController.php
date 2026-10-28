<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiLoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

/**
 * Login y logout de la API. El bot inicia sesión aquí y usa el token Sanctum en cada llamada
 * (cabecera "Authorization: Bearer <token>").
 */
class AuthController extends Controller
{
    public function login(ApiLoginRequest $request): JsonResponse
    {
        $usuario = User::where('email', $request->validated('email'))->first();

        // Mismo mensaje si el correo no existe o la contraseña falla: no se revela cuál de los dos
        if (! $usuario || ! Hash::check($request->validated('password'), $usuario->password)) {
            return response()->json(['message' => 'Credenciales incorrectas.'], 401);
        }

        // La API es del sistema de turnos (el bot): un barbero tiene su panel y no necesita token
        if (! $usuario->esAdmin()) {
            return response()->json(['message' => 'Esta cuenta no tiene acceso a la API.'], 403);
        }

        $nombre = $request->validated('device_name') ?? 'api';

        // Un solo token por nombre: iniciar sesión de nuevo reemplaza al anterior en vez de acumular tokens
        $usuario->tokens()->where('name', $nombre)->delete();

        return response()->json([
            'token' => $usuario->createToken($nombre)->plainTextToken,
            'token_type' => 'Bearer',
            'user' => ['id' => $usuario->id, 'name' => $usuario->name, 'email' => $usuario->email, 'rol' => $usuario->rol],
        ]);
    }

    /** Revoca solo el token con el que se hizo la llamada. */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
