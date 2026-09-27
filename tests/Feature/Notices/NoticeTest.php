<?php

use App\Enums\SmsType;
use App\Jobs\SendCustomSms;
use App\Models\Notice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    feeWorld(); // a school with a year, a section of two students with guardians, an admin and an accountant
    $this->actingAs($this->admin);
});

function noticeData(array $overrides = []): array
{
    return [
        'title_en' => 'Sports day', 'title_bn' => 'ক্রীড়া দিবস',
        'body_en' => 'Sports day is on 20 March.', 'body_bn' => '২০ মার্চ ক্রীড়া দিবস।',
        'audience' => 'all', 'school_class_id' => null, 'published_at' => '2026-03-05 09:00',
        ...$overrides,
    ];
}

it('creates, edits and deletes a notice with a private attachment', function () {
    Storage::fake('local');

    $this->post('/notices', noticeData(['attachment' => UploadedFile::fake()->create('routine.pdf', 100, 'application/pdf')]))
        ->assertSessionHas('success');

    $notice = Notice::sole();
    expect($notice->created_by)->toBe($this->admin->id);
    Storage::disk('local')->assertExists($notice->attachment_path);

    $this->get("/notices/{$notice->id}/attachment")->assertOk();

    $this->put("/notices/{$notice->id}", noticeData(['title_en' => 'Annual sports day']))->assertSessionHas('success');
    expect($notice->fresh()->title_en)->toBe('Annual sports day')
        ->and($notice->fresh()->attachment_path)->not->toBeNull(); // kept when no new file

    $this->get('/notices')->assertOk()->assertInertia(fn ($page) => $page->component('Notices/Index')->has('notices.data', 1));

    $path = $notice->attachment_path;
    $this->delete("/notices/{$notice->id}")->assertSessionHas('success');
    expect(Notice::count())->toBe(0);
    Storage::disk('local')->assertMissing($path);
});

it('needs a class for a class notice and refuses other file types', function () {
    $this->post('/notices', noticeData(['audience' => 'class']))->assertSessionHasErrors('school_class_id');
    $this->post('/notices', noticeData(['attachment' => UploadedFile::fake()->create('virus.exe', 10)]))->assertSessionHasErrors('attachment');

    expect(Notice::count())->toBe(0);
});

it('can also send the notice to guardians by SMS', function () {
    Queue::fake();

    $this->post('/notices', noticeData(['send_sms' => true]))->assertSessionHas('success');

    Queue::assertPushed(SendCustomSms::class, 2);
    Queue::assertPushed(SendCustomSms::class, fn (SendCustomSms $job) => $job->type === SmsType::Notice->value
        && $job->message === 'ক্রীড়া দিবস: ২০ মার্চ ক্রীড়া দিবস। — টঙ্গী মডেল হাই স্কুল');
});

it('does not send staff notices by SMS, and refuses when the balance is short', function () {
    Queue::fake();

    $this->post('/notices', noticeData(['audience' => 'staff', 'send_sms' => true]))->assertSessionHas('success');
    Queue::assertNothingPushed();

    $this->school->forceFill(['sms_balance' => 1])->save();
    $this->post('/notices', noticeData(['send_sms' => true]))->assertSessionHas('error');
    Queue::assertNothingPushed();
    expect(Notice::count())->toBe(1); // the refused one was not saved either
});

it('shows only published staff notices on the dashboard', function () {
    Notice::create(noticeData(['title_en' => 'Staff meeting', 'audience' => 'staff']));
    Notice::create(noticeData(['title_en' => 'For guardians', 'audience' => 'guardians']));
    Notice::create(noticeData(['title_en' => 'Next week', 'published_at' => '2026-03-12 09:00']));

    $this->get('/dashboard')->assertOk()
        ->assertInertia(fn ($page) => $page->component('Dashboard')->has('notices', 1)->where('notices.0.title_en', 'Staff meeting'));
});

it('keeps notices to notices.manage and to the school', function () {
    $notice = Notice::create(noticeData());

    $this->actingAs($this->accountant)->post('/notices', noticeData())->assertForbidden();
    $this->get('/notices')->assertForbidden();

    $outsider = $this->schoolUser();
    $outsider->assignRole('school_admin');
    $this->actingAs($outsider);
    $this->put("/notices/{$notice->id}", noticeData())->assertNotFound();
    $this->get("/notices/{$notice->id}/attachment")->assertNotFound();
});
