<?php

namespace App\Http\Middleware;

use App\Models\AcademicYear;
use App\Support\CurrentAcademicYear;
use App\Support\CurrentSchool;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'school' => fn () => app(CurrentSchool::class)->get()?->only(['id', 'name_en', 'name_bn', 'slug', 'logo_path']),
            'locale' => fn () => app()->getLocale(),
            'academicYears' => fn () => $request->user() && app(CurrentSchool::class)->has()
                ? AcademicYear::query()->orderByDesc('starts_on')->get(['id', 'name', 'is_current'])
                : [],
            'academicYear' => fn () => $request->user()
                ? app(CurrentAcademicYear::class)->get()?->only(['id', 'name', 'is_current'])
                : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'auth' => [
                'user' => fn () => $request->user()?->only(['id', 'name', 'email', 'phone']),
                'permissions' => fn () => $request->user()?->getAllPermissions()->pluck('name')->values()->all() ?? [],
            ],
        ]);
    }
}
