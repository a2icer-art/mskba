<?php

namespace App\Http\Controllers;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Admin\Services\AdminLogsService;
use App\Presentation\Breadcrumbs\AdminBreadcrumbsPresenter;
use App\Presentation\Navigation\AdminNavigationPresenter;
use App\Support\DateFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $breadcrumbs = app(AdminBreadcrumbsPresenter::class)->present([
            'user' => $request->user(),
            'currentHref' => '/admin/logs',
        ])['data'];

        return Inertia::render('Admin/Logs', [
            'appName' => config('app.name'),
            'navigation' => $navigation,
            'activeHref' => '/admin/logs',
            'breadcrumbs' => $breadcrumbs,
            'entities' => $entities,
            'activeEntity' => [
                'key' => 'all',
                'label' => 'Все логи',
                'href' => '/admin/logs',
            ],
            'logs' => $logs,
        ]);
    }

    public function export(Request $request, AdminLogsService $logsService): StreamedResponse
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 20);

        $format = $this->normalizeFormat($request->string('format')->value());
        $entityKey = $request->string('entity', 'all')->value();
        $query = $this->buildLogsQuery($logsService, $entityKey);

        if ($format === 'mysql') {
            return $this->streamMysqlDump($query);
        }

        return $this->streamJsonDump($query);
    }

    public function exportAndDelete(Request $request, AdminLogsService $logsService): StreamedResponse
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 20);

        $format = $this->normalizeFormat($request->string('format')->value());
        $entityKey = $request->string('entity', 'all')->value();
        $query = $this->buildLogsQuery($logsService, $entityKey);

        if ($format === 'mysql') {
            return $this->streamMysqlDump($query, true);
        }

        return $this->streamJsonDump($query, true);
    }

    public function destroy(Request $request, AdminLogsService $logsService)
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 20);

        $entityKey = $request->string('entity', 'all')->value();
        $query = $this->buildLogsQuery($logsService, $entityKey);
        $query->delete();

        return redirect()->back()->with('notice', 'Логи удалены.');
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
        $breadcrumbs = app(AdminBreadcrumbsPresenter::class)->present([
            'user' => $request->user(),
            'currentHref' => '/admin/logs',
            'childLabel' => $entityData['label'] ?? null,
        ])['data'];

        return Inertia::render('Admin/Logs', [
            'appName' => config('app.name'),
            'navigation' => $navigation,
            'activeHref' => $entityData['href'],
            'breadcrumbs' => $breadcrumbs,
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

    private function buildLogsQuery(AdminLogsService $logsService, string $entityKey): Builder
    {
        $query = AuditLog::query()->orderBy('id');

        if ($entityKey === '' || $entityKey === 'all') {
            return $query;
        }

        $entityData = $logsService->getEntity($entityKey);
        if (!$entityData) {
            abort(404);
        }

        return $query->where('entity_type', $entityData['model']);
    }

    private function normalizeFormat(?string $format): string
    {
        return $format === 'mysql' ? 'mysql' : 'json';
    }

    private function streamJsonDump(Builder $query, bool $deleteAfter = false): StreamedResponse
    {
        $timestamp = now()->format('Ymd_His');
        $filename = "audit_logs_{$timestamp}.json";

        return response()->streamDownload(function () use ($query, $deleteAfter) {
            $first = true;
            echo '[';
            $query->chunkById(500, function ($logs) use (&$first) {
                foreach ($logs as $log) {
                    $row = [
                        'id' => $log->id,
                        'entity_type' => $log->entity_type,
                        'entity_id' => $log->entity_id,
                        'action' => $log->action,
                        'changes' => $log->changes,
                        'actor_id' => $log->actor_id,
                        'ip' => $log->ip,
                        'user_agent' => $log->user_agent,
                        'created_at' => $log->created_at?->toDateTimeString(),
                        'updated_at' => $log->updated_at?->toDateTimeString(),
                    ];
                    echo ($first ? '' : ',') . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $first = false;
                }
            });
            echo ']';

            if ($deleteAfter) {
                $query->delete();
            }
        }, $filename, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    private function streamMysqlDump(Builder $query, bool $deleteAfter = false): StreamedResponse
    {
        $timestamp = now()->format('Ymd_His');
        $filename = "audit_logs_{$timestamp}.sql";
        $table = (new AuditLog())->getTable();
        $columns = DB::getSchemaBuilder()->getColumnListing($table);
        $pdo = DB::getPdo();

        return response()->streamDownload(function () use ($query, $deleteAfter, $columns, $pdo, $table) {
            $columnList = implode(', ', array_map(fn ($column) => "`{$column}`", $columns));
            $query->chunkById(300, function ($logs) use ($columns, $pdo, $table, $columnList) {
                foreach ($logs as $log) {
                    $values = [];
                    foreach ($columns as $column) {
                        $value = $log->{$column} ?? null;
                        if (is_array($value)) {
                            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        }
                        if ($value === null) {
                            $values[] = 'NULL';
                            continue;
                        }
                        if ($value instanceof \DateTimeInterface) {
                            $value = $value->format('Y-m-d H:i:s');
                        }
                        $values[] = $pdo->quote((string) $value);
                    }

                    echo "INSERT INTO `{$table}` ({$columnList}) VALUES (" . implode(', ', $values) . ");\n";
                }
            });

            if ($deleteAfter) {
                $query->delete();
            }
        }, $filename, [
            'Content-Type' => 'application/sql; charset=UTF-8',
        ]);
    }
}
