<?php

namespace App\Http\Controllers;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Admin\Services\AdminLogsService;
use App\Presentation\Navigation\AdminNavigationPresenter;
use App\Support\DateFormatter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminLogsController extends Controller
{
    public function index(Request $request, AdminLogsService $logsService)
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 20);

        $modelLabels = $this->getModelLabels($logsService);
        $logs = AuditLog::query()
            ->with('actor:id,login')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString()
            ->through(function (AuditLog $log) use ($modelLabels) {
                $changes = $log->changes ?? [];
                $after = is_array($changes['after'] ?? null) ? $changes['after'] : [];
                $before = is_array($changes['before'] ?? null) ? $changes['before'] : [];
                $fields = array_keys($after ?: $before);
                $entityLabel = $modelLabels[$log->entity_type] ?? $log->entity_type;

                return [
                    'id' => $log->id,
                    'entity_id' => $log->entity_id,
                    'entity_label' => $entityLabel,
                    'action' => $log->action,
                    'created_at' => DateFormatter::dateTime($log->created_at),
                    'actor' => $log->actor
                        ? [
                            'id' => $log->actor->id,
                            'login' => $log->actor->login,
                        ]
                        : null,
                    'fields' => $fields,
                    'ip' => $log->ip,
                    'changes' => $changes,
                ];
            });

        $navigation = app(AdminNavigationPresenter::class)->present([
            'user' => $request->user(),
        ]);
        $entities = $this->getEntitiesWithAll($logsService);

        return Inertia::render('Admin/Logs', [
            'appName' => config('app.name'),
            'navigation' => $navigation,
            'activeHref' => '/admin/logs',
            'entities' => $entities,
            'activeEntity' => [
                'key' => 'all',
                'label' => 'Все логи',
                'href' => '/admin/logs',
            ],
            'logs' => $logs,
        ]);
    }

    public function show(Request $request, string $entity, AdminLogsService $logsService)
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 20);

        $entityData = $logsService->getEntity($entity);
        if (!$entityData) {
            abort(404);
        }

        $modelLabels = $this->getModelLabels($logsService);
        $logs = AuditLog::query()
            ->where('entity_type', $entityData['model'])
            ->with('actor:id,login')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString()
            ->through(function (AuditLog $log) use ($modelLabels) {
                $changes = $log->changes ?? [];
                $after = is_array($changes['after'] ?? null) ? $changes['after'] : [];
                $before = is_array($changes['before'] ?? null) ? $changes['before'] : [];
                $fields = array_keys($after ?: $before);
                $entityLabel = $modelLabels[$log->entity_type] ?? $log->entity_type;

                return [
                    'id' => $log->id,
                    'entity_id' => $log->entity_id,
                    'entity_label' => $entityLabel,
                    'action' => $log->action,
                    'created_at' => DateFormatter::dateTime($log->created_at),
                    'actor' => $log->actor
                        ? [
                            'id' => $log->actor->id,
                            'login' => $log->actor->login,
                        ]
                        : null,
                    'fields' => $fields,
                    'ip' => $log->ip,
                    'changes' => $changes,
                ];
            });

        $navigation = app(AdminNavigationPresenter::class)->present([
            'user' => $request->user(),
        ]);
        $entities = $this->getEntitiesWithAll($logsService);

        return Inertia::render('Admin/Logs', [
            'appName' => config('app.name'),
            'navigation' => $navigation,
            'activeHref' => $entityData['href'],
            'entities' => $entities,
            'activeEntity' => $entityData,
            'logs' => $logs,
        ]);
    }

    private function getRoleLevel(Request $request): int
    {
        $user = $request->user();

        if (!$user) {
            abort(403);
        }

        return (int) $user->roles()->max('level');
    }

    private function ensureAccess(int $roleLevel, int $minLevel): void
    {
        if ($roleLevel <= $minLevel) {
            abort(403);
        }
    }

    private function getEntitiesWithAll(AdminLogsService $logsService): array
    {
        return array_merge(
            [[
                'key' => 'all',
                'label' => 'Все логи',
                'href' => '/admin/logs',
            ]],
            $logsService->getEntities()
        );
    }

    private function getModelLabels(AdminLogsService $logsService): array
    {
        $labels = [];
        foreach ($logsService->getEntities() as $entity) {
            $labels[$entity['model']] = $entity['label'];
        }

        return $labels;
    }
}
