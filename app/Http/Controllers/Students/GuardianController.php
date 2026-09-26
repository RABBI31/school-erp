<?php

namespace App\Http\Controllers\Students;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\GuardianRequest;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\Students\GuardianService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;

/**
 * Guardians of a student, managed from the student's profile.
 */
class GuardianController extends Controller
{
    public function __construct(private GuardianService $guardians) {}

    public function store(GuardianRequest $request, Student $student): RedirectResponse
    {
        if ($request->filled('id') && $student->guardians()->whereKey($request->integer('id'))->exists()) {
            throw new BusinessRuleException(__('messages.guardian_already_linked'));
        }

        $this->guardians->attach($student, $request->validated());

        return back()->with('success', __('messages.saved'));
    }

    public function update(GuardianRequest $request, Student $student, Guardian $guardian): RedirectResponse
    {
        $guardian->update(Arr::except($request->validated(), 'is_primary'));

        return back()->with('success', __('messages.saved'));
    }

    public function makePrimary(Student $student, Guardian $guardian): RedirectResponse
    {
        $this->guardians->makePrimary($student, $guardian);

        return back()->with('success', __('messages.saved'));
    }

    public function destroy(Student $student, Guardian $guardian): RedirectResponse
    {
        $this->guardians->detach($student, $guardian);

        return back()->with('success', __('messages.saved'));
    }
}
