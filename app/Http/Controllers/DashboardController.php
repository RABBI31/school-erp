<?php

namespace App\Http\Controllers;

use App\Models\Notice;
use App\Services\Dashboard\DashboardStats;
use App\Services\Staff\AssignedSections;
use App\Support\CurrentAcademicYear;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Each card is sent only to users with the permission behind it: admins see
 * everything, accountants the fees, teachers their own sections' attendance.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardStats $stats, CurrentAcademicYear $currentAcademicYear, AssignedSections $assigned): Response
    {
        $user = $request->user();
        $yearId = $currentAcademicYear->id();
        $today = CarbonImmutable::today();

        $props = [
            'today' => $today->toDateString(),
            'notices' => Notice::query()->forStaff()->latest('published_at')->limit(5)->get()
                ->map(fn (Notice $n) => [
                    ...$n->only('id', 'title_en', 'title_bn', 'body_en', 'body_bn'),
                    'published_at' => $n->published_at->toDateString(),
                    'has_attachment' => $n->attachment_path !== null,
                ]),
        ];

        if ($user->can('attendance.view')) {
            $props['attendance'] = $stats->attendance($yearId, $today, $assigned->for($user, $yearId));
        }

        if ($user->can('staff.view')) {
            $props['staff'] = $stats->staff($today);
        }

        if ($user->can('fees.reports')) {
            $props['fees'] = $stats->fees($yearId, $today);
        }

        if ($user->can('results.view')) {
            $props['exam'] = $stats->exam($yearId);
        }

        return Inertia::render('Dashboard', $props);
    }
}
