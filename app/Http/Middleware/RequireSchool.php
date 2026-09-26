<?php

namespace App\Http\Middleware;

use App\Support\CurrentSchool;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSchool
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->currentSchool->has(), 404);

        return $next($request);
    }
}
