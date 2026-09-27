<?php

use App\Enums\SmsType;
use App\Jobs\GenerateSectionMarkSheets;
use App\Jobs\SendResultSms;
use App\Models\ExamResult;
use App\Models\Section;
use App\Models\SmsLog;
use App\Services\Pdf\FakePdfRenderer;
use App\Services\Pdf\PdfRenderer;
use App\Services\Sms\SmsService;
use App\Support\CurrentSchool;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    examWorld();
    $this->app->instance(PdfRenderer::class, new FakePdfRenderer);
    $this->actingAs($this->admin);
    enterAllMarks(85, [2 => ['Mathematics' => 75], 3 => ['Mathematics' => 20]]);
    $this->post("/exams/{$this->exam->id}/publish");
});

it('lists a section’s results in merit order', function () {
    $this->get("/exams/{$this->exam->id}/results?section_id={$this->section->id}")->assertOk()
        ->assertInertia(fn ($page) => $page->component('Exams/Results')
            ->has('results', 3)
            ->where('results.0.roll', 1)
            ->where('results.0.gpa', '5.00')
            ->where('results.2.letter', 'F'));
});

it('summarizes pass rate, GPA distribution and failures per subject', function () {
    $this->get("/exams/{$this->exam->id}/summary")->assertOk()
        ->assertInertia(fn ($page) => $page->component('Exams/Summary')
            ->where('summary.students', 3)
            ->where('summary.passed', 2)
            ->where('summary.pass_rate', 66.7)
            ->where('summary.grades', ['A+' => 1, 'A' => 1, 'F' => 1])
            ->where('summary.subject_fails.0.name_en', 'Mathematics')
            ->where('summary.subject_fails.0.fails', 1));
});

it('renders a mark sheet with Bangla digits and the tabulation sheet', function () {
    $result = ExamResult::where('enrollment_id', $this->enrollments[1]->id)->sole();
    $this->withSession(['locale' => 'bn']);

    expect($this->get("/exams/{$this->exam->id}/results/{$result->id}/marksheet")->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent())
        ->toContain('ছাত্র 2')
        ->toContain('অর্ধবার্ষিক পরীক্ষা')
        ->toContain('৪.৬৭')
        ->toContain('বাংলা ১ম পত্র')
        ->toContain('বাংলা ২য় পত্র');

    expect($this->get("/exams/{$this->exam->id}/sections/{$this->section->id}/tabulation")->assertOk()->getContent())
        ->toContain('ছাত্র 1')->toContain('ছাত্র 2')->toContain('ছাত্র 3')->toContain('গণিত');
});

it('prepares all of a section’s mark sheets in the background', function () {
    Storage::fake('local');
    Queue::fake();
    $this->withSession(['locale' => 'en']);

    $this->post("/exams/{$this->exam->id}/sections/{$this->section->id}/marksheets")->assertSessionHas('success');
    Queue::assertPushed(GenerateSectionMarkSheets::class, 1);

    $this->get("/exams/{$this->exam->id}/sections/{$this->section->id}/marksheets")->assertNotFound(); // not ready yet

    (new GenerateSectionMarkSheets($this->exam->id, $this->section->id, $this->school->id, 'en'))->handle(app(PdfRenderer::class), app(CurrentSchool::class));

    expect($this->get("/exams/{$this->exam->id}/sections/{$this->section->id}/marksheets")->assertOk()->streamedContent())
        ->toContain('Student 1')->toContain('Student 3');
});

it('sends the result SMS once per student', function () {
    Queue::fake();

    $this->post("/exams/{$this->exam->id}/result-sms")->assertSessionHas('success');
    Queue::assertPushed(SendResultSms::class, 3);

    $result = ExamResult::where('enrollment_id', $this->enrollments[1]->id)->sole();
    $job = new SendResultSms($result->id, $this->school->id);
    $job->handle(app(SmsService::class), app(CurrentSchool::class));
    $job->handle(app(SmsService::class), app(CurrentSchool::class));

    $log = SmsLog::sole();
    expect($log->type)->toBe(SmsType::Result)
        ->and($log->to_phone)->toBe('01711000002')
        ->and($log->message)->toBe('ছাত্র 2 অর্ধবার্ষিক পরীক্ষা পরীক্ষায় GPA ৪.৬৭ পেয়েছে। — টঙ্গী মডেল হাই স্কুল');

    // Already sent: a second click queues only the other two.
    Queue::fake();
    $this->post("/exams/{$this->exam->id}/result-sms");
    Queue::assertPushed(SendResultSms::class, 2);
});

it('shows the mark audit log to the admin', function () {
    $this->get("/exams/{$this->exam->id}/audits")->assertOk()
        ->assertInertia(fn ($page) => $page->component('Exams/Audits')->has('audits.data', 12)->where('audits.data.0.changed_by', 'Admin Karim'));

    $this->actingAs($this->teacher)->get("/exams/{$this->exam->id}/audits")->assertForbidden();
});

it('limits a teacher to their own sections’ results', function () {
    $other = Section::factory()->create(['school_class_id' => $this->section->school_class_id]);
    $result = ExamResult::first();
    $this->actingAs($this->teacher);

    $this->get("/exams/{$this->exam->id}/results?section_id={$this->section->id}")->assertOk();
    $this->get("/exams/{$this->exam->id}/results?section_id={$other->id}")->assertForbidden();
    $this->get("/exams/{$this->exam->id}/sections/{$other->id}/tabulation")->assertForbidden();
    $this->get("/exams/{$this->exam->id}/results/{$result->id}/marksheet")->assertOk();
    $this->post("/exams/{$this->exam->id}/result-sms")->assertForbidden();
});

it('keeps results to the school', function () {
    $result = ExamResult::first();
    $outsider = $this->schoolUser();
    $outsider->assignRole('school_admin');
    $this->actingAs($outsider);

    $this->get("/exams/{$this->exam->id}/results")->assertNotFound();
    $this->get("/exams/{$this->exam->id}/results/{$result->id}/marksheet")->assertNotFound();
});
