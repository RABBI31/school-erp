<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireSchool;
use App\Http\Middleware\ResolveSchool;
use App\Http\Middleware\SetLocale;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            ResolveSchool::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // The school must be known before auth runs and before route model
        // binding, so bound tenant models are already scoped (404 across schools).
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: ResolveSchool::class,
        );
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: RequireSchool::class,
        );

        $middleware->alias([
            'school' => RequireSchool::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(fn (BusinessRuleException $e) => back()->with('error', $e->getMessage()));
    })->create();
