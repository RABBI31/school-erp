<?php

namespace App\Http\Controllers\Fees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\FeeWaiverRequest;
use App\Models\FeeWaiver;
use Illuminate\Http\RedirectResponse;

/**
 * Waivers are edited from the student's profile. They apply to invoices
 * generated afterwards; invoices already issued keep their amounts.
 */
class FeeWaiverController extends Controller
{
    public function store(FeeWaiverRequest $request): RedirectResponse
    {
        FeeWaiver::create($request->validated());

        return back()->with('success', __('messages.waiver_saved'));
    }

    public function update(FeeWaiverRequest $request, FeeWaiver $waiver): RedirectResponse
    {
        $waiver->update($request->safe()->except('enrollment_id'));

        return back()->with('success', __('messages.waiver_saved'));
    }

    public function destroy(FeeWaiver $waiver): RedirectResponse
    {
        return $this->destroyOrRefuse($waiver);
    }
}
