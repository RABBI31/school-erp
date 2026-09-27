<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StaffLoginRequest;
use App\Models\Staff;
use App\Services\Staff\StaffLoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StaffLoginController extends Controller
{
    public function __construct(private StaffLoginService $logins) {}

    public function store(StaffLoginRequest $request, Staff $staff): RedirectResponse
    {
        $this->logins->create($staff, $request->string('role'), $request->string('password'));

        return back()->with('success', __('messages.login_created'));
    }

    public function update(StaffLoginRequest $request, Staff $staff): RedirectResponse
    {
        $this->logins->update($staff, $request->string('role'), $request->input('password'), $request->user());

        return back()->with('success', __('messages.saved'));
    }

    public function destroy(Request $request, Staff $staff): RedirectResponse
    {
        $this->logins->remove($staff, $request->user());

        return back()->with('success', __('messages.login_removed'));
    }
}
