<?php

namespace Tests\Fixtures;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

/**
 * Test-only tenant model: proves BelongsToSchool before real tenant tables exist.
 */
class Note extends Model
{
    use BelongsToSchool;

    protected $fillable = ['title'];
}
