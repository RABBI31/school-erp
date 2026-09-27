<?php

namespace App\Http\Controllers\Fees;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\GenerateInvoicesRequest;
use App\Models\Invoice;
use App\Models\Section;
use App\Services\Fees\InvoiceGenerator;
use App\Support\CurrentAcademicYear;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    /**
     * Invoices of the viewed academic year.
     */
    public function index(Request $request, CurrentAcademicYear $currentAcademicYear): Response
    {
        $search = $request->string('search')->trim()->toString();

        $invoices = Invoice::query()
            ->whereHas('enrollment', fn (Builder $query) => $query->where('academic_year_id', $currentAcademicYear->id()))
            ->with(['enrollment:id,student_id,section_id,roll', 'enrollment.student:id,student_uid,name_en,name_bn', 'enrollment.section:id,name,school_class_id', 'enrollment.section.schoolClass:id,name_en,name_bn'])
            ->when($request->filled('month'), fn (Builder $query) => $query->where('month', $request->string('month')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('section_id'), fn (Builder $query) => $query->whereHas('enrollment', fn (Builder $e) => $e->where('section_id', $request->integer('section_id'))))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q
                ->whereLike('invoice_no', "%{$search}%")
                ->orWhereHas('enrollment.student', fn (Builder $s) => $s
                    ->whereLike('name_en', "%{$search}%")
                    ->orWhereLike('name_bn', "%{$search}%")
                    ->orWhereLike('student_uid', "%{$search}%"))))
            ->orderByDesc('month')->orderBy('invoice_no')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Fees/Invoices', [
            'invoices' => $invoices,
            'filters' => $request->only('search', 'month', 'status', 'section_id'),
            'sections' => Section::options(),
            'statuses' => InvoiceStatus::values(),
            'thisMonth' => now()->format('Y-m'),
        ]);
    }

    public function generate(GenerateInvoicesRequest $request, InvoiceGenerator $generator): RedirectResponse
    {
        $result = $generator->generate($request->validated('month'));

        return back()->with('success', __('messages.invoices_generated', $result));
    }
}
