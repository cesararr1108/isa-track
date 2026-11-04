<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Controllers\UserPresenter;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Isa\Companies\Infrastructure\Persistence\CompanyModel;
use Isa\Shared\Infrastructure\Auth\PermissionChecker;

/**
 * Datos que reciben todas las páginas del panel. Solo se envía lo necesario:
 * nunca contraseñas, tokens ni datos de otras empresas. El menú lo arma el
 * servidor según los permisos (la interfaz no decide qué se puede ver).
 */
final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(
        private readonly UserPresenter $users,
        private readonly PermissionChecker $permissions,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        // El historial cifrado usa la criptografía del navegador, que solo existe en un
        // "contexto seguro" (HTTPS o localhost). En http://IP-del-servidor fallaría y la
        // página se quedaría cargando, así que ahí se desactiva.
        Inertia::encryptHistory(config('inertia.history.encrypt') && self::isSecureContext($request));

        return parent::handle($request, $next);
    }

    public static function isSecureContext(Request $request): bool
    {
        return $request->isSecure() || in_array($request->getHost(), ['localhost', '127.0.0.1', '::1'], true);
    }

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return array_merge(parent::share($request), [
            'app' => ['name' => config('app.name')],
            'auth' => ['user' => $user === null ? null : $this->presentUser($user)],
            'company' => fn () => $user?->company_id === null ? null
                : CompanyModel::query()->whereKey($user->company_id)->first(['id', 'name'])?->only(['id', 'name']),
            'navigation' => fn () => $user === null ? [] : $this->navigation($user),
            'flash' => fn () => ['success' => $request->session()->get('success')],
            'map' => [
                'style' => config('isa.maps.style'),
                // Las llaves de MapTiler son públicas por diseño: se restringen por dominio en maptiler.com.
                'maptilerKey' => config('isa.maps.style') === 'maptiler' ? config('isa.maps.maptiler_key') : null,
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function presentUser(User $user): array
    {
        $u = $this->users->toArray($user);

        return [
            'id' => $u['id'],
            'name' => $u['name'],
            'email' => $u['email'],
            'role' => $u['role'],
            'role_label' => config("isa.role_labels.{$u['role']}", $u['role']),
            'permissions' => $u['permissions'],
            'carrier' => $u['carrier'] === null ? null : [
                'name' => $u['carrier']['name'],
                'photo_url' => $u['carrier']['photo_url'] !== null ? route('panel.carriers.photo', $u['carrier']['id']) : null,
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function navigation(User $user): array
    {
        $groups = [];
        foreach (config('isa.navigation') as $item) {
            if ($item['permission'] !== null && ! $this->permissions->allows($user->role, $item['permission'], $user->permissions ?? [], $user->revoked_permissions ?? [])) {
                continue;
            }
            $groups[$item['group']][] = [
                'key' => $item['key'],
                'label' => $item['label'],
                'icon' => $item['icon'],
                'href' => $item['route'] !== null ? route($item['route']) : null,
                'active' => $item['route'] !== null && request()->routeIs($item['route'].'*'),
            ];
        }

        return array_map(fn (string $g, array $items) => ['group' => $g, 'items' => $items], array_keys($groups), $groups);
    }
}
