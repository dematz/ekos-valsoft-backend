<?php

namespace App\Traits;

use App\Services\AuditLogService;

trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function (self $model) {
            app(AuditLogService::class)->logActivity($model, 'create');
        });

        static::updated(function (self $model) {
            $changes = [];
            foreach ($model->getDirty() as $key => $value) {
                $changes[$key] = [
                    'old' => $model->getOriginal($key),
                    'new' => $value,
                ];
            }

            app(AuditLogService::class)->logActivity(
                $model,
                'update',
                $changes
            );
        });

        static::deleted(function (self $model) {
            app(AuditLogService::class)->logActivity($model, 'delete');
        });
    }
}
