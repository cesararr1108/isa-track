<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Login por token (Sanctum), pensado para el panel SPA y la app del conductor. */
final class AuthController
{
    public function __construct(private readonly UserPresenter $presenter) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:60'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Credenciales incorrectas.']);
        }
        if ($user->active === false) {
            throw ValidationException::withMessages(['email' => 'Tu usuario está desactivado. Habla con el administrador.']);
        }

        return new JsonResponse([
            'token' => $user->createToken($data['device_name'] ?? 'api')->plainTextToken,
            'user' => $this->presenter->toArray($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return new JsonResponse(['user' => $this->presenter->toArray($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return new JsonResponse(status: 204);
    }
}
