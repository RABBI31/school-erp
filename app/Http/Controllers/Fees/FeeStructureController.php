<?php

namespace App\Http\Controllers\Fees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\FeeStructureRequest;
use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Support\CurrentAcademicYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Amount per class per fee head for the viewed academic year, edited as one grid.
 */
class FeeStructureController extends Controller
{
    public function __construct(private CurrentAcademicYear $currentAcademicYear) {}

    public function edit(): Response
    {
        $yearId = $this->currentAcademicYear->id();

        return Inertia::render('Fees/Structure', [
            'classes' => SchoolClass::query()->orderBy('numeric_order')->get(['id', 'name_en', 'name_bn']),
            'heads' => FeeHead::query()->orderBy('type')->orderBy('name_en')->get(['id', 'name_en', 'name_bn', 'type']),
            'amounts' => FeeStructure::query()->where('academic_year_id', $yearId)->get()
                ->groupBy('school_class_id')
                ->map(fn ($rows) => $rows->pluck('amount', 'fee_head_id')),
        ]);
    }

    public function update(FeeStructureRequest $request): RedirectResponse
    {
        $yearId = $this->currentAcademicYear->id();

        if ($yearId === null) {
            return back()->with('error', __('messages.no_academic_year'));
        }

        $classIds = SchoolClass::query()->pluck('id')->flip();
        $headIds = FeeHead::query()->pluck('id')->flip();

        DB::transaction(function () use ($request, $yearId, $classIds, $headIds) {
            foreach ($request->validated('amounts') as $classId => $amounts) {
                foreach ($amounts as $headId => $amount) {
                    if (! $classIds->has($classId) || ! $headIds->has($headId)) {
                        continue;
                    }

                    $key = ['academic_year_id' => $yearId, 'school_class_id' => $classId, 'fee_head_id' => $headId];

                    if ($amount === null || (float) $amount === 0.0) {
                        FeeStructure::query()->where($key)->delete();
                    } else {
                        FeeStructure::updateOrCreate($key, ['amount' => $amount]);
                    }
                }
            }
        });

        return back()->with('success', __('messages.saved'));
    }
}
