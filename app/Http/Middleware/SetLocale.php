<?php

namespace App\Http\Middleware;

use App\Support\CurrentSchool;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale')
            ?? $this->currentSchool->get()?->default_locale
            ?? config('app.locale');

        if (in_array($locale, config('app.supported_locales'), true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
