<?php

namespace App\Http\Controllers\Sms;

use App\Enums\SmsStatus;
use App\Enums\SmsType;
use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use App\Support\CurrentSchool;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SmsLogController extends Controller
{
    public function __invoke(Request $request, CurrentSchool $currentSchool): Response
    {
        $search = $request->string('search')->trim()->toString();
        $month = SmsLog::query()->where('created_at', '>=', now()->startOfMonth());

        return Inertia::render('Sms/Log', [
            'logs' => SmsLog::query()
                ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
                ->when($request->filled('date'), fn ($q) => $q->whereDate('created_at', $request->string('date')))
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->whereLike('to_phone', "%{$search}%")->orWhereLike('message', "%{$search}%")))
                ->latest('id')
                ->paginate(50)
                ->withQueryString()
                ->through(fn (SmsLog $log) => [
                    ...$log->only('id', 'to_phone', 'message', 'type', 'status', 'parts', 'error'),
                    'created_at' => $log->created_at->format('Y-m-d H:i'),
                ]),
            'filters' => $request->only('search', 'type', 'status', 'date'),
            'types' => SmsType::values(),
            'statuses' => SmsStatus::values(),
            'balance' => $currentSchool->get()->fresh()->sms_balance,
            'month' => [
                'sent' => (clone $month)->where('status', SmsStatus::Sent)->count(),
                'failed' => (clone $month)->where('status', SmsStatus::Failed)->count(),
                'parts' => (int) (clone $month)->where('status', SmsStatus::Sent)->sum('parts'),
            ],
        ]);
    }
}
