<?php

namespace App\Support;

use App\Models\School;

/**
 * The school the current request (or console task) is working in.
 * Set by ResolveSchool from the subdomain; read by BelongsToSchool.
 */
class CurrentSchool
{
    private ?School $school = null;

    public function set(?School $school): void
    {
        $this->school = $school;

        // spatie/laravel-permission teams: roles and permissions are per school.
        setPermissionsTeamId($school?->id);
    }

    public function get(): ?School
    {
        return $this->school;
    }

    public function id(): ?int
    {
        return $this->school?->id;
    }

    public function has(): bool
    {
        return $this->school !== null;
    }
}
