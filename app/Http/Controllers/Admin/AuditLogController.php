<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Audit log (spec 8.15): owners filter the activity_logs table. */
class AuditLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $data = $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $organizationId = $request->user()->organization_id;

        $logs = ActivityLog::query()->with('user')
            ->where('organization_id', $organizationId)
            ->when($data['action'] ?? null, fn (Builder $q, string $action) => $q->where('action', 'like', $action.'%'))
            ->when($data['user_id'] ?? null, fn (Builder $q, int|string $id) => $q->where('user_id', (int) $id))
            ->when($data['from'] ?? null, fn (Builder $q, string $from) => $q->where('created_at', '>=', $from.' 00:00:00'))
            ->when($data['to'] ?? null, fn (Builder $q, string $to) => $q->where('created_at', '<=', $to.' 23:59:59'))
            ->latest('id')
            ->paginate((int) config('rentora.per_page'))
            ->withQueryString();

        return view('admin.audit.index', [
            'logs' => $logs,
            'filters' => $data,
            'actions' => ActivityLog::query()->where('organization_id', $organizationId)->distinct()->orderBy('action')->pluck('action', 'action'),
            'users' => User::query()->staff()->orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
