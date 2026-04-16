<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    public function logActivity(Model $model, string $action, ?array $changes = null): void
    {
        $userId = auth()->id();
        if (! $userId) {
            return;
        }

        AuditLog::create([
            'user_id'    => $userId,
            'action'     => $action,
            'model_type' => class_basename($model),
            'model_id'   => $model->id,
            'changes'    => $changes,
        ]);
    }
}
