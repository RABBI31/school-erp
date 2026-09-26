<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Support\CurrentSchool;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the school from the subdomain ({slug}.localhost in dev).
 * The bare domain has no school. Runs before authentication and route
 * model binding, so both already see the current school.
 */
class ResolveSchool
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $this->subdomain($request->getHost());

        if ($slug === null) {
            $this->currentSchool->set(null);

            return $next($request);
        }

        $school = School::query()->where('slug', $slug)->where('is_active', true)->first();

        abort_if($school === null, 404);

        $this->currentSchool->set($school);

        $user = $request->user();

        if ($user !== null && $user->school_id !== $school->id) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }

    private function subdomain(string $host): ?string
    {
        $domain = config('app.domain');

        if ($host === $domain || ! str_ends_with($host, '.'.$domain)) {
            return null;
        }

        return substr($host, 0, -strlen('.'.$domain));
    }
}
