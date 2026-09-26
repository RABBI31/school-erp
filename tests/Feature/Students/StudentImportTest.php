<?php

use App\Models\AcademicYear;
use App\Models\Group;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Rap2hpoutre\FastExcel\FastExcel;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = $this->schoolUser();
    $admin->assignRole('school_admin');
    $this->actingAs($admin);

    $this->year = AcademicYear::factory()->current()->create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    $this->section = Section::factory()->create();
});

function row(array $overrides = []): array
{
    return [
        'roll' => 1, 'name_en' => 'Rahim Uddin', 'name_bn' => 'রহিম উদ্দিন', 'gender' => 'male',
        'date_of_birth' => '15/03/2013', 'religion' => 'Islam', 'blood_group' => 'b+',
        'father_name' => 'Karim Uddin', 'mother_name' => 'Rahima Begum', 'present_address' => 'Tongi',
        'permanent_address' => '', 'birth_reg_no' => '', 'admission_date' => '',
        'group' => '', 'optional_subject_code' => '',
        'guardian_name' => 'Karim Uddin', 'guardian_relation' => 'father', 'guardian_phone' => '01711000000',
        ...$overrides,
    ];
}

function xlsx(array $rows): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
    (new FastExcel(collect($rows)))->export($path);

    return new UploadedFile($path, 'students.xlsx', null, null, true);
}

function importFile(UploadedFile $file): array
{
    test()->post('/students/import', [
        'academic_year_id' => test()->year->id, 'section_id' => test()->section->id, 'file' => $file,
    ])->assertSessionHasNoErrors()->assertRedirect('/students/import');

    return session('import_report');
}

test('valid rows are imported and invalid rows are reported with their row number', function () {
    $report = importFile(xlsx([
        row(['roll' => 1]),
        row(['roll' => 2, 'name_en' => '', 'guardian_phone' => '01711000002']),
        row(['roll' => 3, 'gender' => 'x', 'guardian_phone' => '01711000003']),
        row(['roll' => 1, 'name_en' => 'Duplicate roll', 'guardian_phone' => '01711000004']),
    ]));

    expect($report['imported'])->toBe(1)
        ->and(collect($report['failed'])->pluck('row')->all())->toBe([3, 4, 5])
        ->and(Student::count())->toBe(1);

    $student = Student::with('enrollments')->firstOrFail();
    expect($student->blood_group->value)->toBe('B+')
        ->and($student->religion->value)->toBe('islam')
        ->and($student->date_of_birth->toDateString())->toBe('2013-03-15')
        ->and($student->enrollments[0]->section_id)->toBe($this->section->id);
});

test('siblings in the file share one guardian, and existing guardians are reused', function () {
    $existing = Guardian::factory()->create(['phone' => '01911000000']);

    importFile(xlsx([
        row(['roll' => 1, 'guardian_phone' => '01711000000']),
        row(['roll' => 2, 'name_en' => 'Fatema', 'gender' => 'female', 'guardian_phone' => '1711000000']), // Excel dropped the 0
        row(['roll' => 3, 'name_en' => 'Cousin', 'guardian_phone' => '+8801911000000']),
    ]));

    expect(Guardian::count())->toBe(2)
        ->and(Guardian::where('phone', '01711000000')->firstOrFail()->students()->count())->toBe(2)
        ->and($existing->students()->count())->toBe(1);
});

test('csv files saved from Excel (with a BOM) work, and groups are matched by name', function () {
    Group::factory()->create(['name' => 'Science']);
    $this->section = Section::factory()->create(['school_class_id' => SchoolClass::factory()->withGroups()->create()->id]);

    $rows = [row(['group' => 'science']), row(['roll' => 2, 'group' => 'Unknown', 'guardian_phone' => '01711000009'])];
    $csv = "\u{FEFF}".implode(',', array_keys($rows[0]))."\n".collect($rows)->map(fn ($r) => implode(',', $r))->implode("\n");
    $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
    file_put_contents($path, $csv);

    $report = importFile(new UploadedFile($path, 'students.csv', 'text/csv', null, true));

    expect($report['imported'])->toBe(1)
        ->and($report['failed'][0]['row'])->toBe(3)
        ->and($report['failed'][0]['errors'][0])->toContain('group');
});

test('the template downloads', function () {
    $this->get('/students/import/template')->assertOk()->assertDownload('students-import-template.xlsx');
});
