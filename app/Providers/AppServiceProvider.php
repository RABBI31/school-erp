<?php

namespace App\Providers;

use App\Services\Sms\LogSmsDriver;
use App\Services\Sms\SmsDriver;
use App\Support\CurrentAcademicYear;
use App\Support\CurrentSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentSchool::class);
        $this->app->scoped(CurrentAcademicYear::class);

        $this->app->bind(SmsDriver::class, fn () => match (config('services.sms.driver')) {
            'log' => new LogSmsDriver,
            default => throw new \InvalidArgumentException('Unknown SMS driver: '.config('services.sms.driver')),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
