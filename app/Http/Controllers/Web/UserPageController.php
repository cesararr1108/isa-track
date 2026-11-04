<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\UserPresenter;
use App\Models\User;
use App\Services\UserManagement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Isa\Fleet\Application\ListCarriers\ListCarriersHandler;
use Isa\Shared\Application\TenantContext;
use Isa\Shared\Infrastructure\Audit\AuditLog;
use Isa\Shared\Infrastructure\Auth\PermissionChecker;
use Isa\Shared\Infrastructure\Auth\Role;

/** Usuarios y permisos de la empresa (panel), con la bitácora de actividad reciente. */
final class UserPageController
{
    public function index(TenantContext $tenant, UserPresenter $presenter, PermissionChecker $permissions, ListCarriersHandler $carriers, AuditLog $audit): Response
    {
        $companyId = $tenant->companyId()->value();
        $catalog = config('isa.permissions');

        return Inertia::render('users/pages/UsersIndex', [
            'users' => User::query()->where('company_id', $companyId)->orderBy('name')->get()
                ->map(fn (User $u) => array_merge($presenter->toArray($u), [
                    'carrier' => $u->carrier_id === null ? null : [
                        'id' => $u->carrier_id,
                        'photo_url' => $presenter->toArray($u)['carrier']['photo_url'] !== null ? route('panel.carriers.photo', $u->carrier_id) : null,
                    ],
                ]))->values(),
            'permissions' => collect($catalog)->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])->values(),
            'roles' => collect(Role::cases())->reject(fn (Role $r) => $r === Role::SuperAdmin)->map(fn (Role $r) => [
                'key' => $r->value,
                'label' => config("isa.role_labels.{$r->value}", $r->value),
                'permissions' => array_values(array_filter(array_keys($catalog), fn ($p) => $permissions->roleAllows($r, $p))),
            ])->values(),
            'carriers' => array_map(fn (array $c) => ['id' => $c['id'], 'name' => $c['name']], $carriers()),
            'activity' => $audit->latest($companyId),
        ]);
    }

    public function store(Request $request, UserManagement $users): RedirectResponse
    {
        $users->create($request->validate($users->rules(null), [], $users->attributes()));

        return back()->with('success', 'Usuario creado.');
    }

    public function update(Request $request, string $user, UserManagement $users): RedirectResponse
    {
        $model = $users->find($user);
        $users->update($model, $request->validate($users->rules($model), [], $users->attributes()), (string) $request->user()->id);

        return back()->with('success', 'Usuario guardado. Los permisos aplican de inmediato.');
    }
}
