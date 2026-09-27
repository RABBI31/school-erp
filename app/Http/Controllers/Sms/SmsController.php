<?php

namespace App\Http\Controllers\Sms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sms\SendSmsRequest;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Services\Sms\BulkSms;
use App\Services\Sms\SmsAudience;
use App\Support\CurrentAcademicYear;
use App\Support\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Custom SMS to the guardians of the whole school, a class or a section.
 */
class SmsController extends Controller
{
    public function __construct(private SmsAudience $audience, private CurrentAcademicYear $currentAcademicYear) {}

    public function create(Request $request, CurrentSchool $currentSchool): Response
    {
        return Inertia::render('Sms/Compose', [
            'filters' => $request->only('school_class_id', 'section_id'),
            'classes' => SchoolClass::query()->orderBy('numeric_order')->get(['id', 'name_en', 'name_bn']),
            'sections' => Section::options(),
            'recipients' => count($this->phones($request)),
            'balance' => $currentSchool->get()->fresh()->sms_balance,
        ]);
    }

    public function store(SendSmsRequest $request, BulkSms $bulk): RedirectResponse
    {
        $queued = $bulk->send($this->phones($request), $request->validated('message'));

        return back()->with('success', __('messages.sms_queued', ['count' => $queued]));
    }

    /**
     * @return list<string>
     */
    private function phones(Request $request): array
    {
        return $this->audience->phones(
            $this->currentAcademicYear->id(),
            $request->integer('school_class_id') ?: null,
            $request->integer('section_id') ?: null,
        );
    }
}
