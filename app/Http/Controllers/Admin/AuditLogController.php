<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'action' => $request->string('action')->toString(),
            'user_id' => $request->integer('user_id') ?: null,
            'search' => $request->string('search')->toString(),
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
        ];

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($filters['action'], fn ($q, $action) => $q->where('action', $action))
            ->when($filters['user_id'], fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($filters['search'], fn ($q, $search) => $q->where('description', 'like', "%{$search}%"))
            ->when($filters['from'], fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'], fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(30)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'user' => $log->user?->name ?? 'Sistema',
                'subject' => $log->subject_type
                    ? class_basename($log->subject_type)." #{$log->subject_id}"
                    : null,
                'ip_address' => $log->ip_address,
                'properties' => $log->properties,
                'created_at' => $log->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('admin/audit/index', [
            'logs' => $logs,
            'filters' => $filters,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'users' => User::query()
                ->whereIn('id', AuditLog::query()->whereNotNull('user_id')->distinct()->pluck('user_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
