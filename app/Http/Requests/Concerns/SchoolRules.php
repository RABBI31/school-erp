<?php

namespace App\Http\Requests\Concerns;

use App\Support\CurrentSchool;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation rules limited to the current school. Plain `exists:`/`unique:`
 * rules bypass BelongsToSchool, so tenant tables must use these instead.
 */
trait SchoolRules
{
    protected function uniqueInSchool(string $table, string $column = 'NULL'): Unique
    {
        return (new Unique($table, $column))->where('school_id', app(CurrentSchool::class)->id());
    }

    protected function existsInSchool(string $table, string $column = 'id'): Exists
    {
        return (new Exists($table, $column))->where('school_id', app(CurrentSchool::class)->id());
    }
}
