<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\GradeRulesRequest;
use App\Models\GradingScheme;
use App\Services\Exams\DefaultGradingScheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Grading schemes and their rules. Published results keep their grades until
 * the exam is published again.
 */
class GradingController extends Controller
{
    public function index(DefaultGradingScheme $default): Response
    {
        $default->ensure();

        return Inertia::render('Exams/Grading', [
            'schemes' => GradingScheme::query()->with('rules:id,grading_scheme_id,min_percent,max_percent,letter,point')->orderByDesc('is_default')->get(['id', 'name', 'is_default']),
        ]);
    }

    public function update(GradeRulesRequest $request, GradingScheme $scheme): RedirectResponse
    {
        DB::transaction(function () use ($request, $scheme) {
            $scheme->rules()->delete();
            $scheme->rules()->createMany($request->validated('rules'));
        });

        return back()->with('success', __('messages.saved'));
    }
}
