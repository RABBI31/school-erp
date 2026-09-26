<?php

namespace App\Services\Students;

use App\Exceptions\BusinessRuleException;
use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * A student always has at least one guardian, and exactly one primary guardian
 * (the one who receives SMS).
 */
class GuardianService
{
    /**
     * Link an existing guardian (by id) or create a new one.
     *
     * @param  array<string, mixed>  $data
     */
    public function attach(Student $student, array $data): Guardian
    {
        $guardian = empty($data['id'])
            ? Guardian::create(Arr::only($data, (new Guardian)->getFillable()))
            : Guardian::query()->findOrFail($data['id']);

        $student->guardians()->attach($guardian->id, ['is_primary' => false]);

        if (filter_var($data['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN) || $student->guardians()->count() === 1) {
            $this->makePrimary($student, $guardian);
        }

        return $guardian;
    }

    public function makePrimary(Student $student, Guardian $guardian): void
    {
        DB::transaction(function () use ($student, $guardian) {
            $student->guardians()->newPivotQuery()->update(['is_primary' => false]);
            $student->guardians()->updateExistingPivot($guardian->id, ['is_primary' => true]);
        });
    }

    public function detach(Student $student, Guardian $guardian): void
    {
        DB::transaction(function () use ($student, $guardian) {
            $others = $student->guardians()->whereKeyNot($guardian->id)->lockForUpdate()->get();

            if ($others->isEmpty()) {
                throw new BusinessRuleException(__('messages.last_guardian'));
            }

            $wasPrimary = (bool) $student->guardians()->whereKey($guardian->id)->first()?->pivot->is_primary;
            $student->guardians()->detach($guardian->id);

            if ($wasPrimary) {
                $this->makePrimary($student, $others->first());
            }
        });
    }
}
