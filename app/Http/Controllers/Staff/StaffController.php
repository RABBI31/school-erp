<?php

namespace App\Http\Controllers\Staff;

use App\Enums\StaffStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StaffRequest;
use App\Models\Staff;
use App\Services\Staff\StaffLoginService;
use App\Services\Staff\StaffService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffController extends Controller
{
    public function __construct(private StaffService $staff) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        return Inertia::render('Staff/Index', [
            'rows' => Staff::query()
                ->with('user:id', 'user.roles:id,name')
                ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q
                    ->whereLike('name_en', "%{$search}%")
                    ->orWhereLike('name_bn', "%{$search}%")
                    ->orWhereLike('employee_code', "%{$search}%")
                    ->orWhereLike('phone', "%{$search}%")))
                ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
                ->orderBy('name_en')
                ->paginate(25)
                ->withQueryString(),
            'filters' => $request->only('search', 'status'),
            'statuses' => StaffStatus::values(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Staff/Create', [
            'statuses' => StaffStatus::values(),
        ]);
    }

    public function store(StaffRequest $request): RedirectResponse
    {
        $this->staff->create($request->safe()->except('photo'), $request->file('photo'));

        return to_route('staff.index')->with('success', __('messages.saved'));
    }

    public function edit(Staff $staff): Response
    {
        $staff->load('user:id', 'user.roles:id,name');

        return Inertia::render('Staff/Edit', [
            'staff' => $staff,
            'hasPhoto' => $staff->photo_path !== null,
            'login' => $staff->user ? ['role' => $staff->user->roles->first()?->name] : null,
            'isSelf' => $staff->user_id === auth()->id(),
            'roles' => StaffLoginService::ROLES,
            'statuses' => StaffStatus::values(),
        ]);
    }

    public function update(StaffRequest $request, Staff $staff): RedirectResponse
    {
        $this->staff->update($staff, $request->safe()->except('photo'), $request->file('photo'), $request->user());

        return back()->with('success', __('messages.saved'));
    }

    public function destroy(Request $request, Staff $staff): RedirectResponse
    {
        $this->staff->delete($staff, $request->user());

        return to_route('staff.index')->with('success', __('messages.deleted'));
    }

    public function photo(Staff $staff): StreamedResponse
    {
        abort_if($staff->photo_path === null || ! Storage::disk('local')->exists($staff->photo_path), 404);

        return Storage::disk('local')->response($staff->photo_path);
    }
}
