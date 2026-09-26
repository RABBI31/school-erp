<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\ShiftRequest;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Setup/NamedList', [
            'resource' => 'shifts',
            'rows' => Shift::query()
                ->when($request->filled('search'), fn ($query) => $query->whereLike('name', '%'.$request->string('search').'%'))
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(ShiftRequest $request): RedirectResponse
    {
        Shift::create($request->validated());

        return back()->with('success', __('messages.saved'));
    }

    public function update(ShiftRequest $request, Shift $shift): RedirectResponse
    {
        $shift->update($request->validated());

        return back()->with('success', __('messages.saved'));
    }

    public function destroy(Shift $shift): RedirectResponse
    {
        return $this->destroyOrRefuse($shift);
    }
}
