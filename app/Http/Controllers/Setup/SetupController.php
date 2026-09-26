<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Group;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Shift;
use App\Models\Subject;
use App\Models\Version;
use Inertia\Inertia;
use Inertia\Response;

class SetupController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Setup/Index', [
            'counts' => [
                'academicYears' => AcademicYear::query()->count(),
                'shifts' => Shift::query()->count(),
                'versions' => Version::query()->count(),
                'classes' => SchoolClass::query()->count(),
                'groups' => Group::query()->count(),
                'sections' => Section::query()->count(),
                'subjects' => Subject::query()->count(),
            ],
        ]);
    }
}
