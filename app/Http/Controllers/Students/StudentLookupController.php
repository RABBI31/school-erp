<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Services\Students\RollSuggester;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Small JSON lookups used while filling in the admission form.
 */
class StudentLookupController extends Controller
{
    public function nextRoll(Request $request, RollSuggester $rolls): JsonResponse
    {
        $request->validate([
            'section_id' => ['required', 'integer'],
            'academic_year_id' => ['required', 'integer'],
        ]);

        // Enrollment is scoped to the school, so other schools' ids just give 1.
        return response()->json(['roll' => $rolls->next($request->integer('section_id'), $request->integer('academic_year_id'))]);
    }

    /**
     * Existing guardians with this phone number, with their children, so siblings share a guardian.
     */
    public function guardians(Request $request): JsonResponse
    {
        $phone = Phone::normalize($request->string('phone')->toString());

        if ($phone === null || strlen($phone) < 11) {
            return response()->json([]);
        }

        return response()->json(
            Guardian::query()
                ->where('phone', $phone)
                ->with('students:id,name_en,name_bn,student_uid')
                ->limit(5)
                ->get(['id', 'name_en', 'name_bn', 'relation', 'phone'])
                ->each(fn (Guardian $guardian) => $guardian->students->each->makeHidden('pivot'))
        );
    }
}
