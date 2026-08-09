<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditService
{
    public function log(
        string $module,
        string $action,
        ?string $entity = null,
        ?string $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $companyId = null,
        ?string $userId = null,
        ?Request $request = null
    ): void {
        AuditLog::create([
            'company_id' => $companyId ?? (auth()->check() ? auth()->user()->company_id : null),
            'user_id' => $userId ?? (auth()->check() ? auth()->id() : null),
            'module' => $module,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
