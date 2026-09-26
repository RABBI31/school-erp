<?php

namespace App\Services\Setup;

use App\Exceptions\BusinessRuleException;
use App\Models\AcademicYear;
use Illuminate\Support\Facades\DB;

/**
 * Exactly one current academic year per school (business-rules §1).
 */
class AcademicYearService
{
    /**
     * @param  array{name: string, starts_on: string, ends_on: string}  $data
     */
    public function create(array $data): AcademicYear
    {
        return DB::transaction(function () use ($data) {
            $hasCurrent = AcademicYear::query()->where('is_current', true)->lockForUpdate()->exists();

            $year = new AcademicYear($data);
            $year->is_current = ! $hasCurrent;
            $year->save();

            return $year;
        });
    }

    public function makeCurrent(AcademicYear $year): void
    {
        DB::transaction(function () use ($year) {
            AcademicYear::query()->lockForUpdate()->get(['id']);

            AcademicYear::query()->whereKeyNot($year->id)->where('is_current', true)->update(['is_current' => false]);

            $year->is_current = true;
            $year->save();
        });
    }

    public function delete(AcademicYear $year): void
    {
        if ($year->is_current) {
            throw new BusinessRuleException(__('messages.current_year_delete'));
        }

        $year->delete();
    }
}
