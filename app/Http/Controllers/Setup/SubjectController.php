<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\SubjectRequest;
use App\Models\Group;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    public function index(Request $request): Response
    {
        $search = '%'.$request->string('search').'%';

        return Inertia::render('Setup/Subjects', [
            'rows' => Subject::query()
                ->with(['schoolClass:id,name_en,name_bn', 'group:id,name'])
                ->when($request->filled('search'), fn ($query) => $query->where(fn ($q) => $q
                    ->whereLike('name_en', $search)->orWhereLike('name_bn', $search)->orWhereLike('code', $search)))
                ->when($request->filled('school_class_id'), fn ($query) => $query->where('school_class_id', $request->integer('school_class_id')))
                ->when($request->filled('group_id'), fn ($query) => $query->where('group_id', $request->integer('group_id')))
                ->orderBy(SchoolClass::query()->select('numeric_order')->whereColumn('classes.id', 'subjects.school_class_id'))
                ->orderBy('display_order')
                ->orderBy('code')
                ->paginate(25)
                ->withQueryString(),
            'filters' => $request->only('search', 'school_class_id', 'group_id'),
            'classes' => SchoolClass::query()->orderBy('numeric_order')->get(['id', 'name_en', 'name_bn', 'has_groups']),
            'groups' => Group::query()->orderBy('name')->get(['id', 'name']),
            // Existing combined keys per class, suggested when typing one.
            'combinedKeys' => Subject::query()
                ->whereNotNull('combined_key')
                ->distinct()
                ->get(['school_class_id', 'combined_key'])
                ->groupBy('school_class_id')
                ->map(fn ($subjects) => $subjects->pluck('combined_key')->values()),
        ]);
    }

    public function store(SubjectRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['display_order'] ??= (int) Subject::query()->where('school_class_id', $data['school_class_id'])->max('display_order') + 1;

        Subject::create($data);

        return back()->with('success', __('messages.saved'));
    }

    public function update(SubjectRequest $request, Subject $subject): RedirectResponse
    {
        $data = $request->validated();
        $data['display_order'] ??= $subject->display_order;

        $subject->update($data);

        return back()->with('success', __('messages.saved'));
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        return $this->destroyOrRefuse($subject);
    }
}
