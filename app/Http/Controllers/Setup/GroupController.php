<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\GroupRequest;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Setup/NamedList', [
            'resource' => 'groups',
            'rows' => Group::query()
                ->when($request->filled('search'), fn ($query) => $query->whereLike('name', '%'.$request->string('search').'%'))
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(GroupRequest $request): RedirectResponse
    {
        Group::create($request->validated());

        return back()->with('success', __('messages.saved'));
    }

    public function update(GroupRequest $request, Group $group): RedirectResponse
    {
        $group->update($request->validated());

        return back()->with('success', __('messages.saved'));
    }

    public function destroy(Group $group): RedirectResponse
    {
        return $this->destroyOrRefuse($group);
    }
}
