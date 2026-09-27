<?php

// business-rules §4 (absent SMS) and §5 (SMS)

use App\Enums\SmsStatus;
use App\Jobs\SendAbsentSms;
use App\Models\SmsLog;
use App\Models\StudentAttendance;
use App\Services\Sms\SmsService;
use App\Support\CurrentSchool;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => attendanceWorld());

test('saving queues one SMS per absent student', function () {
    Queue::fake();
    $this->actingAs($this->teacher);

    takeAttendance([1 => 'absent', 3 => 'absent', 2 => 'late']);

    Queue::assertPushed(SendAbsentSms::class, 2);
});

test('the SMS goes to the primary guardian with the Bangla template, and the balance is charged', function () {
    $this->actingAs($this->teacher);

    takeAttendance([2 => 'absent']); // the queue runs synchronously in tests

    $log = SmsLog::sole();
    expect($log->to_phone)->toBe('01711000002')
        ->and($log->status)->toBe(SmsStatus::Sent)
        ->and($log->message)->toBe('প্রিয় অভিভাবক, আপনার সন্তান ছাত্র 2 (রোল ২) আজ ১০/০৩/২০২৬ তারিখে বিদ্যালয়ে অনুপস্থিত। — টঙ্গী মডেল হাই স্কুল')
        ->and($this->school->refresh()->sms_balance)->toBe(100 - $log->parts)
        ->and(StudentAttendance::where('status', 'absent')->sole()->sms_sent_at)->not->toBeNull();
});

test('the SMS is sent only once per day, even if attendance is saved again', function () {
    $this->actingAs($this->teacher);

    takeAttendance([1 => 'absent']);
    takeAttendance([1 => 'absent']);
    takeAttendance([1 => 'present']);
    takeAttendance([1 => 'absent']);

    expect(SmsLog::count())->toBe(1);
});

test('no SMS when the school has turned absent SMS off', function () {
    $this->school->forceFill(['absent_sms_enabled' => false])->save();
    $this->actingAs($this->teacher);

    takeAttendance([1 => 'absent']);

    expect(SmsLog::count())->toBe(0);
});

test('with too little balance the SMS is logged as failed and nothing is charged', function () {
    $this->school->forceFill(['sms_balance' => 1])->save();
    $this->actingAs($this->teacher);

    takeAttendance([1 => 'absent']); // the Bangla message needs 2 parts

    $log = SmsLog::sole();
    expect($log->status)->toBe(SmsStatus::Failed)
        ->and($log->error)->not->toBeNull()
        ->and($this->school->refresh()->sms_balance)->toBe(1)
        ->and(StudentAttendance::where('status', 'absent')->sole()->sms_sent_at)->toBeNull();
});

test('the job works on its own, outside a request (queue worker)', function () {
    Queue::fake();
    $this->actingAs($this->teacher);
    takeAttendance([3 => 'absent']);
    $attendanceId = StudentAttendance::where('status', 'absent')->sole()->id;

    app(CurrentSchool::class)->set(null); // a queue worker has no current school
    (new SendAbsentSms($attendanceId, $this->school->id))->handle(app(SmsService::class), app(CurrentSchool::class));

    expect($this->forSchool($this->school, fn () => SmsLog::count()))->toBe(1);
});

test('no SMS if the student was marked present before the job ran', function () {
    Queue::fake();
    $this->actingAs($this->teacher);
    takeAttendance([1 => 'absent']);
    $attendance = StudentAttendance::where('status', 'absent')->sole();
    $attendance->update(['status' => 'present']);

    (new SendAbsentSms($attendance->id, $this->school->id))->handle(app(SmsService::class), app(CurrentSchool::class));

    expect(SmsLog::count())->toBe(0);
});
