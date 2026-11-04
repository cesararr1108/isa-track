<?php

declare(strict_types=1);

namespace Isa\Shared\Infrastructure\Audit;

use Illuminate\Support\Facades\DB;

/**
 * Registra acciones sensibles en la bitácora (tabla audit_logs, solo inserción).
 * Nunca guarda contraseñas ni tokens: solo qué cambió.
 */
final class AuditLog
{
    /** @param array<string, mixed> $data */
    public function record(string $action, ?string $subjectType = null, ?string $subjectId = null, array $data = [], ?string $companyId = null, ?string $userId = null): void
    {
        $request = request();
        $user = $request?->user();

        DB::table('audit_logs')->insert([
            'company_id' => $companyId ?? $user?->company_id,
            'user_id' => $userId ?? $user?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'data' => $data === [] ? null : json_encode($data, JSON_UNESCAPED_UNICODE),
            'ip' => $request?->ip(),
            'created_at' => now(),
        ]);
    }

    /** @return list<array<string, mixed>> últimas acciones de la empresa */
    public function latest(string $companyId, int $limit = 30): array
    {
        return DB::table('audit_logs')
            ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->where('audit_logs.company_id', $companyId)
            ->orderByDesc('audit_logs.id')
            ->limit($limit)
            ->get(['audit_logs.action', 'audit_logs.subject_type', 'audit_logs.subject_id', 'audit_logs.data', 'audit_logs.ip', 'audit_logs.created_at', 'users.name as user_name'])
            ->map(fn ($r) => [
                'action' => $r->action,
                'subject_type' => $r->subject_type,
                'subject_id' => $r->subject_id,
                'data' => $r->data !== null ? json_decode($r->data, true) : null,
                'ip' => $r->ip,
                'user_name' => $r->user_name,
                'at' => (string) $r->created_at,
            ])
            ->all();
    }
}
