<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\SchoolClassRequest;
use App\Models\SchoolClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SchoolClassController extends Controller
{
    public function index(Request $request): Response
    {
        $search = '%'.$request->string('search').'%';

        return Inertia::render('Setup/Classes', [
            'rows' => SchoolClass::query()
                ->when($request->filled('search'), fn ($query) => $query->where(fn ($q) => $q->whereLike('name_en', $search)->orWhereLike('name_bn', $search)))
                ->orderBy('numeric_order')
                ->paginate(25)
                ->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(SchoolClassRequest $request): RedirectResponse
    {
        SchoolClass::create($request->validated());

        return back()->with('success', __('messages.saved'));
    }

    public function update(SchoolClassRequest $request, SchoolClass $schoolClass): RedirectResponse
    {
        $schoolClass->update($request->validated());

        return back()->with('success', __('messages.saved'));
    }

    public function destroy(SchoolClass $schoolClass): RedirectResponse
    {
        return $this->destroyOrRefuse($schoolClass);
    }
}
