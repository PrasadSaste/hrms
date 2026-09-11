<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('activity.view'), 403);

        $logs = ActivityLog::query()
            ->with('user')
            ->when($request->integer('user_id'), fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->string('action')->toString(), fn ($q, $v) => $q->where('action', $v))
            ->when($request->date('from'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->date('to'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('activity.index', [
            'logs' => $logs,
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
