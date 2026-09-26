<?php

namespace App\Services\Students;

use App\Models\Enrollment;
use App\Models\Student;
use App\Support\CurrentSchool;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StudentService
{
    public function __construct(private CurrentSchool $currentSchool) {}

    /**
     * @param  array<string, mixed>  $data  validated (see UpdateStudentRequest)
     */
    public function update(Student $student, array $data, ?Enrollment $enrollment, ?UploadedFile $photo = null): void
    {
        $oldPhoto = $student->photo_path;

        if ($photo !== null) {
            $student->photo_path = $photo->store("schools/{$this->currentSchool->id()}/students", 'local');
        }

        DB::transaction(function () use ($student, $data, $enrollment) {
            $student->fill(Arr::only($data, $student->getFillable()))->save();
            $enrollment?->update(Arr::only($data, ['section_id', 'group_id', 'roll', 'optional_subject_id']));
        });

        if ($photo !== null && $oldPhoto !== null) {
            Storage::disk('local')->delete($oldPhoto);
        }
    }
}
