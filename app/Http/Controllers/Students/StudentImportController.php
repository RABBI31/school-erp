<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\ImportStudentsRequest;
use App\Models\AcademicYear;
use App\Models\Section;
use App\Services\Students\StudentImporter;
use App\Support\CurrentAcademicYear;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentImportController extends Controller
{
    public function create(CurrentAcademicYear $currentAcademicYear): Response
    {
        return Inertia::render('Students/Import', [
            'academicYearOptions' => AcademicYear::query()->orderByDesc('starts_on')->get(['id', 'name']),
            'defaultYearId' => $currentAcademicYear->id(),
            'sections' => Section::query()
                ->with('schoolClass:id,name_en,name_bn,numeric_order')
                ->get()
                ->sortBy([['schoolClass.numeric_order', 'asc'], ['name', 'asc']])
                ->values()
                ->map(fn (Section $section) => [
                    'id' => $section->id,
                    'name' => $section->name,
                    'class_name_en' => $section->schoolClass->name_en,
                    'class_name_bn' => $section->schoolClass->name_bn,
                ]),
            'columns' => StudentImporter::COLUMNS,
            'report' => session('import_report'),
        ]);
    }

    public function store(ImportStudentsRequest $request, StudentImporter $importer): RedirectResponse
    {
        $report = $importer->import(
            $request->file('file')->getRealPath(),
            AcademicYear::query()->findOrFail($request->integer('academic_year_id')),
            Section::query()->findOrFail($request->integer('section_id')),
        );

        return to_route('students.import.create')->with('import_report', $report);
    }

    public function template(): StreamedResponse
    {
        $example = [
            'roll' => 1, 'name_en' => 'Rahim Uddin', 'name_bn' => 'রহিম উদ্দিন', 'gender' => 'male',
            'date_of_birth' => '15/03/2013', 'religion' => 'islam', 'blood_group' => 'B+',
            'father_name' => 'Karim Uddin', 'mother_name' => 'Rahima Begum', 'present_address' => 'Tongi, Gazipur',
            'permanent_address' => '', 'birth_reg_no' => '', 'admission_date' => '05/01/2026',
            'group' => '', 'optional_subject_code' => '',
            'guardian_name' => 'Karim Uddin', 'guardian_relation' => 'father', 'guardian_phone' => '01711000000',
        ];

        return (new FastExcel(collect([$example])))->download('students-import-template.xlsx');
    }
}
