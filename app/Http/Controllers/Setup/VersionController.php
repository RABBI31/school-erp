<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\VersionRequest;
use App\Models\Version;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VersionController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Setup/NamedList', [
            'resource' => 'versions',
            'rows' => Version::query()
                ->when($request->filled('search'), fn ($query) => $query->whereLike('name', '%'.$request->string('search').'%'))
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(VersionRequest $request): RedirectResponse
    {
        Version::create($request->validated());

        return back()->with('success', __('messages.saved'));
    }

    public function update(VersionRequest $request, Version $version): RedirectResponse
    {
        $version->update($request->validated());

        return back()->with('success', __('messages.saved'));
    }

    public function destroy(Version $version): RedirectResponse
    {
        return $this->destroyOrRefuse($version);
    }
}
