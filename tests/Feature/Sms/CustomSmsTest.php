<?php

use App\Enums\SmsStatus;
use App\Enums\SmsType;
use App\Jobs\SendCustomSms;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\SmsLog;
use App\Models\Student;
use App\Services\Sms\SmsService;
use App\Support\CurrentSchool;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    feeWorld();
    $this->actingAs($this->admin);
});

it('previews recipients, parts and cost, texting a shared guardian once', function () {
    // A sibling of student 1 in another section shares their guardian.
    $sibling = Student::factory()->create();
    $sibling->guardians()->attach($this->enrollment->student->guardians->first()->id, ['is_primary' => true]);
    $other = Section::factory()->create();
    Enrollment::factory()->create(['student_id' => $sibling->id, 'academic_year_id' => $this->year->id, 'section_id' => $other->id]);

    $this->get('/sms')->assertOk()
        ->assertInertia(fn ($page) => $page->component('Sms/Compose')->where('recipients', 2)->where('balance', 100));

    $this->get('/sms?section_id='.$other->id)->assertInertia(fn ($page) => $page->where('recipients', 1));
});

it('queues one SMS per phone', function () {
    Queue::fake();

    $this->post('/sms', ['message' => 'আগামীকাল স্কুল বন্ধ থাকবে।'])->assertSessionHas('success');

    Queue::assertPushed(SendCustomSms::class, 2);
    Queue::assertPushed(SendCustomSms::class, fn (SendCustomSms $job) => $job->phone === '01711000001');
});

it('sends through the SMS service and logs it', function () {
    (new SendCustomSms('01711000001', 'Hello', $this->school->id))->handle(app(SmsService::class), app(CurrentSchool::class));

    expect(SmsLog::sole()->only('type', 'status', 'parts'))->toBe(['type' => SmsType::Custom, 'status' => SmsStatus::Sent, 'parts' => 1])
        ->and($this->school->fresh()->sms_balance)->toBe(99);
});

it('refuses up front when the balance cannot cover everyone', function () {
    Queue::fake();
    $this->school->forceFill(['sms_balance' => 3])->save();

    // 2 recipients × 2 parts (80 Bangla characters) = 4 > 3
    $this->post('/sms', ['message' => str_repeat('ক', 80)])->assertSessionHas('error');
    Queue::assertNothingPushed();

    $this->post('/sms', ['message' => ''])->assertSessionHasErrors('message');
});

it('keeps custom SMS to sms.send and to the school’s own guardians', function () {
    Queue::fake();

    $this->actingAs($this->accountant)->post('/sms', ['message' => 'Hi'])->assertForbidden();
    $this->get('/sms/log')->assertForbidden();

    $outsider = $this->schoolUser();
    $outsider->assignRole('school_admin');
    $outsider->school->forceFill(['sms_balance' => 100])->save();
    $this->actingAs($outsider)->post('/sms', ['message' => 'Hi'])->assertSessionHas('error'); // no guardians in that school
    $this->post('/sms', ['message' => 'Hi', 'section_id' => $this->section->id])->assertSessionHasErrors('section_id');
    Queue::assertNothingPushed();
});

it('lists the SMS log with filters and the balance', function () {
    SmsLog::create(['to_phone' => '01711000001', 'message' => 'A', 'type' => SmsType::Absent, 'status' => SmsStatus::Sent, 'parts' => 2]);
    SmsLog::create(['to_phone' => '01711000002', 'message' => 'B', 'type' => SmsType::Due, 'status' => SmsStatus::Failed, 'parts' => 1, 'error' => 'No balance']);

    $this->get('/sms/log')->assertOk()
        ->assertInertia(fn ($page) => $page->component('Sms/Log')->has('logs.data', 2)->where('balance', 100)->where('month.parts', 2)->where('month.sent', 1));

    $this->get('/sms/log?type=due')->assertInertia(fn ($page) => $page->has('logs.data', 1)->where('logs.data.0.to_phone', '01711000002'));
    $this->get('/sms/log?status=sent')->assertInertia(fn ($page) => $page->has('logs.data', 1));
    $this->get('/sms/log?search=000002')->assertInertia(fn ($page) => $page->has('logs.data', 1));

    $outsider = $this->schoolUser();
    $outsider->assignRole('school_admin');
    $this->actingAs($outsider)->get('/sms/log')->assertInertia(fn ($page) => $page->has('logs.data', 0));
});
