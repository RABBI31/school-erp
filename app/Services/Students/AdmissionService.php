<?php

namespace App\Services\Students;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Support\CurrentSchool;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Admission = student + guardian(s) + enrollment, all or nothing.
 */
class AdmissionService
{
    public const ENROLLMENT_FIELDS = ['academic_year_id', 'section_id', 'group_id', 'roll', 'optional_subject_id'];

    public function __construct(
        private StudentUidGenerator $uids,
        private GuardianService $guardians,
        private CurrentSchool $currentSchool,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated admission data (see StudentRules)
     */
    public function admit(array $data, ?UploadedFile $photo = null): Student
    {
        // Store the file outside the transaction; remove it again if the transaction fails.
        $photoPath = $photo?->store("schools/{$this->currentSchool->id()}/students", 'local');

        try {
            return DB::transaction(function () use ($data, $photoPath) {
                $year = AcademicYear::query()->findOrFail($data['academic_year_id']);

                $student = new Student(Arr::only($data, (new Student)->getFillable()));
                $student->student_uid = $this->uids->next($year);
                $student->photo_path = $photoPath;
                $student->save();

                foreach ($data['guardians'] as $guardian) {
                    $this->guardians->attach($student, $guardian);
                }

                $student->enrollments()->create(Arr::only($data, self::ENROLLMENT_FIELDS));

                return $student;
            });
        } catch (Throwable $e) {
            if ($photoPath !== null) {
                Storage::disk('local')->delete($photoPath);
            }

            throw $e;
        }
    }
}
