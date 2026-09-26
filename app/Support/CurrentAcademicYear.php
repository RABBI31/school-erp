<?php

namespace App\Support;

use App\Models\AcademicYear;

/**
 * The academic year the user is viewing: their choice in the top-bar switcher
 * (session), otherwise the school's current year. Screens default to this.
 */
class CurrentAcademicYear
{
    public const SESSION_KEY = 'academic_year_id';

    private ?AcademicYear $year = null;

    /** Remembered per request object, so a long-lived container never serves a stale year. */
    private ?int $resolvedForRequest = null;

    public function get(): ?AcademicYear
    {
        if ($this->resolvedForRequest !== spl_object_id(request())) {
            $this->year = $this->resolve();
            $this->resolvedForRequest = spl_object_id(request());
        }

        return $this->year;
    }

    public function id(): ?int
    {
        return $this->get()?->id;
    }

    public function select(AcademicYear $year): void
    {
        session()->put(self::SESSION_KEY, $year->id);
        $this->year = $year;
        $this->resolvedForRequest = spl_object_id(request());
    }

    private function resolve(): ?AcademicYear
    {
        $selectedId = request()->hasSession() ? request()->session()->get(self::SESSION_KEY) : null;

        // Both queries are scoped to the current school by BelongsToSchool.
        return ($selectedId ? AcademicYear::query()->find($selectedId) : null)
            ?? AcademicYear::query()->where('is_current', true)->first();
    }
}
