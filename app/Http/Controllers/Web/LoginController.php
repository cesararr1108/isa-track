<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Isa\Shared\Infrastructure\Audit\AuditLog;

/**
 * Inicio de sesión del panel con sesión de servidor (cookie httpOnly + CSRF).
 * Máximo 5 intentos por minuto por correo e IP; usuarios inactivos no entran.
 */
final class LoginController
{
    private const MAX_ATTEMPTS = 5;

    public function show(): Response
    {
        return Inertia::render('auth/pages/Login');
    }

    public function store(Request $request, AuditLog $audit): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string', 'max:200'],
            'remember' => ['nullable', 'boolean'],
        ], [], ['email' => 'correo', 'password' => 'contraseña']);

        $key = Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => sprintf('Demasiados intentos. Vuelve a intentar en %d segundos.', RateLimiter::availableIn($key)),
            ]);
        }

        $ok = Auth::attemptWhen(
            ['email' => $data['email'], 'password' => $data['password']],
            fn ($user) => $user->active !== false,
            $request->boolean('remember'),
        );

        if (! $ok) {
            RateLimiter::hit($key, 60);
            $known = User::query()->where('email', $data['email'])->first(['id', 'company_id']);
            $audit->record('login.failed', 'user', $known?->id, ['email' => Str::lower($data['email'])], $known?->company_id, $known?->id);
            // Mismo mensaje para correo inexistente, clave errada o usuario inactivo: no revela cuál es.
            throw ValidationException::withMessages(['email' => 'Correo o contraseña incorrectos, o el usuario está desactivado.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $audit->record('login.success', 'user', (string) $request->user()->id);

        return redirect()->intended(route('panel.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        // Rota la llave del historial cifrado: el botón "atrás" ya no muestra datos.
        Inertia::clearHistory();

        return redirect()->route('login');
    }
}
