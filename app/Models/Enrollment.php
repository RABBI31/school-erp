<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's place in one academic year: section, group, roll, optional subject.
 * Attendance, invoices and marks reference this, not the student.
 */
class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use BelongsToSchool, HasFactory;

    protected $fillable = ['student_id', 'academic_year_id', 'section_id', 'group_id', 'roll', 'optional_subject_id', 'status'];

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'roll' => 'integer',
            'status' => EnrollmentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function optionalSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'optional_subject_id');
    }
}
