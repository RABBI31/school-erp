<?php

namespace App\Http\Controllers\Notices;

use App\Enums\NoticeAudience;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notices\NoticeRequest;
use App\Models\Notice;
use App\Models\SchoolClass;
use App\Services\Sms\NoticeSms;
use App\Services\Sms\SmsAudience;
use App\Support\CurrentAcademicYear;
use App\Support\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class NoticeController extends Controller
{
    public function index(Request $request, SmsAudience $audience, CurrentAcademicYear $currentAcademicYear, CurrentSchool $currentSchool): Response
    {
        $search = $request->string('search')->trim()->toString();
        $classes = SchoolClass::query()->orderBy('numeric_order')->get(['id', 'name_en', 'name_bn']);
        $yearId = $currentAcademicYear->id();

        return Inertia::render('Notices/Index', [
            'notices' => Notice::query()
                ->with('schoolClass:id,name_en,name_bn')
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->whereLike('title_en', "%{$search}%")->orWhereLike('title_bn', "%{$search}%")))
                ->latest('published_at')->latest('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Notice $notice) => [
                    ...$notice->only('id', 'title_en', 'title_bn', 'body_en', 'body_bn', 'audience', 'school_class_id'),
                    'published_at' => $notice->published_at->format('Y-m-d\TH:i'),
                    'is_published' => $notice->published_at->isPast(),
                    'has_attachment' => $notice->attachment_path !== null,
                    'school_class' => $notice->schoolClass,
                ]),
            'filters' => $request->only('search'),
            'classes' => $classes,
            'audiences' => NoticeAudience::values(),
            // For the "also send as SMS" cost preview.
            'recipients' => [
                'all' => count($audience->phones($yearId)),
                'classes' => $classes->mapWithKeys(fn (SchoolClass $c) => [$c->id => count($audience->phones($yearId, $c->id))]),
            ],
            'smsBalance' => $currentSchool->get()->sms_balance,
            'schoolLocale' => $currentSchool->get()->default_locale,
            'schoolName' => $currentSchool->get()->only('name_en', 'name_bn'),
        ]);
    }

    public function store(NoticeRequest $request, NoticeSms $sms): RedirectResponse
    {
        $path = $request->file('attachment')?->store('notices/'.$request->user()->school_id, 'local');

        try {
            $sent = DB::transaction(function () use ($request, $sms, $path) {
                $notice = new Notice($request->noticeData());
                $notice->created_by = $request->user()->id;
                $notice->attachment_path = $path;
                $notice->save();

                // Throws (and rolls the notice back) when the balance is short.
                return $request->boolean('send_sms') ? $sms->send($notice) : 0;
            });
        } catch (Throwable $e) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }

            throw $e;
        }

        return back()->with('success', $sent > 0 ? __('messages.notice_sent', ['count' => $sent]) : __('messages.saved'));
    }

    public function update(NoticeRequest $request, Notice $notice): RedirectResponse
    {
        $notice->fill($request->noticeData());

        if ($request->hasFile('attachment')) {
            $old = $notice->attachment_path;
            $notice->attachment_path = $request->file('attachment')->store('notices/'.$notice->school_id, 'local');
            if ($old !== null) {
                Storage::disk('local')->delete($old);
            }
        }

        $notice->save();

        return back()->with('success', __('messages.saved'));
    }

    public function destroy(Notice $notice): RedirectResponse
    {
        $path = $notice->attachment_path;
        $notice->delete();

        if ($path !== null) {
            Storage::disk('local')->delete($path);
        }

        return back()->with('success', __('messages.deleted'));
    }

    /**
     * Attachments are private: any signed-in user of the school may open them.
     */
    public function attachment(Notice $notice): StreamedResponse
    {
        abort_if($notice->attachment_path === null || ! Storage::disk('local')->exists($notice->attachment_path), 404);

        return Storage::disk('local')->response($notice->attachment_path);
    }
}
