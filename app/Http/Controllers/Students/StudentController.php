<?php

namespace App\Http\Controllers\Students;

use App\Enums\BloodGroup;
use App\Enums\Gender;
use App\Enums\GuardianRelation;
use App\Enums\Religion;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\StoreStudentRequest;
use App\Http\Requests\Students\UpdateStudentRequest;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\FeeHead;
use App\Models\FeeWaiver;
use App\Models\Group;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Services\Attendance\AttendanceStats;
use App\Services\Staff\AssignedSections;
use App\Services\Students\AdmissionService;
use App\Services\Students\StudentService;
use App\Support\CurrentAcademicYear;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    public function __construct(private CurrentAcademicYear $currentAcademicYear) {}

    /**
     * Students enrolled in the academic year being viewed.
     */
    public function index(Request $request, AssignedSections $sections): Response
    {
        $search = $request->string('search')->trim()->toString();
        $assigned = $sections->for($request->user(), $this->currentAcademicYear->id());

        $rows = Enrollment::query()
            ->where('academic_year_id', $this->currentAcademicYear->id())
            ->when($assigned !== null, fn (Builder $query) => $query->whereIn('section_id', $assigned))
            ->whereHas('student')
            ->with(['student:id,student_uid,name_en,name_bn,gender,status', 'section:id,name,school_class_id', 'section.schoolClass:id,name_en,name_bn', 'group:id,name'])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q
                ->when(ctype_digit($search), fn (Builder $q) => $q->orWhere('roll', (int) $search))
                ->orWhereHas('student', fn (Builder $s) => $s
                    ->whereLike('name_en', "%{$search}%")
                    ->orWhereLike('name_bn', "%{$search}%")
                    ->orWhereLike('student_uid', "%{$search}%"))))
            ->when($request->filled('school_class_id'), fn (Builder $query) => $query->whereHas('section', fn (Builder $s) => $s->where('school_class_id', $request->integer('school_class_id'))))
            ->when($request->filled('section_id'), fn (Builder $query) => $query->where('section_id', $request->integer('section_id')))
            ->when($request->filled('status'), fn (Builder $query) => $query->whereHas('student', fn (Builder $s) => $s->where('status', $request->string('status'))))
            ->orderBy(Section::query()->select('classes.numeric_order')->join('classes', 'classes.id', '=', 'sections.school_class_id')->whereColumn('sections.id', 'enrollments.section_id'))
            ->orderBy(Section::query()->select('name')->whereColumn('sections.id', 'enrollments.section_id'))
            ->orderBy('roll')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Students/Index', [
            'rows' => $rows,
            'filters' => $request->only('search', 'school_class_id', 'section_id', 'status'),
            'classes' => SchoolClass::query()->orderBy('numeric_order')->get(['id', 'name_en', 'name_bn']),
            'sections' => $assigned === null ? $this->sectionOptions() : $this->sectionOptions()->whereIn('id', $assigned)->values(),
            'statuses' => StudentStatus::values(),
            'hasYear' => $this->currentAcademicYear->id() !== null,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Students/Create', [
            ...$this->formOptions(),
            'defaultYearId' => $this->currentAcademicYear->id(),
        ]);
    }

    public function store(StoreStudentRequest $request, AdmissionService $admissions): RedirectResponse
    {
        $student = $admissions->admit($request->safe()->except('photo'), $request->file('photo'));

        return to_route('students.show', $student)->with('success', __('messages.admitted', ['uid' => $student->student_uid]));
    }

    public function show(Student $student, AttendanceStats $stats): Response
    {
        $student->load([
            'guardians' => fn ($query) => $query->orderByDesc('guardian_student.is_primary'),
            'guardians.students:id,name_en,name_bn,student_uid',
            'enrollments' => fn ($query) => $query
                ->with(['academicYear:id,name,starts_on', 'section:id,name,school_class_id', 'section.schoolClass:id,name_en,name_bn', 'group:id,name', 'optionalSubject:id,name_en,name_bn'])
                ->orderByDesc(AcademicYear::query()->select('starts_on')->whereColumn('academic_years.id', 'enrollments.academic_year_id')),
        ]);

        $enrollment = $student->enrollments->firstWhere('academic_year_id', $this->currentAcademicYear->id());

        return Inertia::render('Students/Show', [
            'student' => $student,
            'attendance' => $enrollment ? $stats->forEnrollment($enrollment) : null,
            ...$this->feeProps($enrollment),
            'hasPhoto' => $student->photo_path !== null,
            'relations' => GuardianRelation::values(),
        ]);
    }

    /**
     * Waivers of the viewed year's enrollment, for users who see fees.
     *
     * @return array<string, mixed>
     */
    private function feeProps(?Enrollment $enrollment): array
    {
        if (! request()->user()->can('fees.view')) {
            return [];
        }

        return [
            'enrollmentId' => $enrollment?->id,
            'waivers' => $enrollment
                ? FeeWaiver::query()->where('enrollment_id', $enrollment->id)->with('feeHead:id,name_en,name_bn')->orderBy('id')->get()
                : [],
            'feeHeads' => FeeHead::query()->orderBy('type')->orderBy('name_en')->get(['id', 'name_en', 'name_bn', 'type']),
        ];
    }

    public function edit(Student $student): Response
    {
        return Inertia::render('Students/Edit', [
            ...$this->formOptions(),
            'student' => $student,
            'enrollment' => $student->enrollments()->where('academic_year_id', $this->currentAcademicYear->id())->first(),
            'hasPhoto' => $student->photo_path !== null,
            'statuses' => StudentStatus::values(),
        ]);
    }

    public function update(UpdateStudentRequest $request, Student $student, StudentService $students): RedirectResponse
    {
        $students->update($student, $request->safe()->except('photo'), $request->enrollment(), $request->file('photo'));

        return to_route('students.show', $student)->with('success', __('messages.saved'));
    }

    public function destroy(Student $student): RedirectResponse
    {
        $student->delete();

        return to_route('students.index')->with('success', __('messages.deleted'));
    }

    public function photo(Student $student): StreamedResponse
    {
        abort_if($student->photo_path === null || ! Storage::disk('local')->exists($student->photo_path), 404);

        return Storage::disk('local')->response($student->photo_path);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'academicYearOptions' => AcademicYear::query()->orderByDesc('starts_on')->get(['id', 'name']),
            'sections' => $this->sectionOptions(),
            'groups' => Group::query()->orderBy('name')->get(['id', 'name']),
            'optionalSubjects' => Subject::query()->where('is_optional_allowed', true)
                ->orderBy('display_order')->get(['id', 'school_class_id', 'group_id', 'name_en', 'name_bn']),
            'genders' => Gender::values(),
            'religions' => Religion::values(),
            'bloodGroups' => BloodGroup::values(),
            'relations' => GuardianRelation::values(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function sectionOptions()
    {
        return Section::query()
            ->with(['schoolClass:id,name_en,name_bn,numeric_order,has_groups', 'shift:id,name', 'version:id,name'])
            ->get()
            ->sortBy([['schoolClass.numeric_order', 'asc'], ['name', 'asc']])
            ->values()
            ->map(fn (Section $section) => [
                'id' => $section->id,
                'name' => $section->name,
                'school_class_id' => $section->school_class_id,
                'class_name_en' => $section->schoolClass->name_en,
                'class_name_bn' => $section->schoolClass->name_bn,
                'has_groups' => $section->schoolClass->has_groups,
                'shift' => $section->shift->name,
                'version' => $section->version->name,
            ]);
    }
}
