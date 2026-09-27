<?php

namespace App\Http\Controllers\Fees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\FeeHeadRequest;
use App\Models\FeeHead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeeHeadController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Fees/Heads', [
            'rows' => FeeHead::query()
                ->when($request->filled('search'), fn ($query) => $query->where(fn ($q) => $q
                    ->whereLike('name_en', '%'.$request->string('search').'%')
                    ->orWhereLike('name_bn', '%'.$request->string('search').'%')))
                ->orderBy('type')->orderBy('name_en')
                ->paginate(25)
                ->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(FeeHeadRequest $request): RedirectResponse
    {
        FeeHead::create($request->validated());

        return back()->with('success', __('messages.saved'));
    }

    public function update(FeeHeadRequest $request, FeeHead $head): RedirectResponse
    {
        $head->update($request->validated());

        return back()->with('success', __('messages.saved'));
    }

    public function destroy(FeeHead $head): RedirectResponse
    {
        return $this->destroyOrRefuse($head);
    }
}
