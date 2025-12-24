<?php

namespace App\Domain\Audit\Traits;

use App\Domain\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            $model->writeAudit('created');
        });

        static::updated(function (Model $model): void {
            $model->writeAudit('updated');
        });

        static::deleted(function (Model $model): void {
            $model->writeAudit('deleted');
        });
    }

    protected function writeAudit(string $action): void
    {
        if (!config('entity-logging.enabled')) {
            return;
        }

        if (config('entity-logging.ignore_console') && app()->runningInConsole()) {
            return;
        }

        $loggable = config('entity-logging.loggable', []);
        if (!in_array(static::class, $loggable, true)) {
            return;
        }

        $ignored = config('entity-logging.ignored_attributes', []);

        $changes = [];
        if ($action === 'created') {
            $changes = [
                'before' => [],
                'after' => array_diff_key($this->getAttributes(), array_flip($ignored)),
            ];
        } elseif ($action === 'updated') {
            $dirty = $this->getChanges();
            $filtered = array_diff_key($dirty, array_flip($ignored));
            if (!$filtered) {
                return;
            }
            $before = [];
            foreach (array_keys($filtered) as $key) {
                $before[$key] = $this->getOriginal($key);
            }
            $changes = [
                'before' => $before,
                'after' => $filtered,
            ];
        } elseif ($action === 'deleted') {
            $changes = [
                'before' => array_diff_key($this->getOriginal(), array_flip($ignored)),
                'after' => [],
            ];
        }

        AuditLog::query()->create([
            'entity_type' => $this->getMorphClass(),
            'entity_id' => $this->getKey(),
            'action' => $action,
            'changes' => $changes,
            'actor_id' => Auth::id(),
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
