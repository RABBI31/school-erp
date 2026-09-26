<?php

namespace Tests;

use App\Models\School;
use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\URL;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        // Test-only tables (tests/Fixtures) are created by RefreshDatabase with the rest.
        $app['migrator']->path(__DIR__.'/Fixtures/migrations');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Page tests render the Blade shell; they must not depend on a running
        // Vite dev server (public/hot) or on built assets (public/build).
        $this->withoutVite();
    }

    /**
     * Send the following requests to the school's subdomain and make it the
     * current school for code running in the test itself (factories, roles).
     */
    protected function inSchool(?School $school = null): School
    {
        $school ??= School::factory()->create();

        app(CurrentSchool::class)->set($school);
        URL::forceRootUrl('http://'.$school->slug.'.'.config('app.domain'));

        return $school;
    }

    /**
     * Run $callback with $school as the current school (e.g. to create another
     * school's records), then switch back.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function forSchool(School $school, callable $callback): mixed
    {
        $currentSchool = app(CurrentSchool::class);
        $previous = $currentSchool->get();
        $currentSchool->set($school);

        try {
            return $callback();
        } finally {
            $currentSchool->set($previous);
        }
    }

    /**
     * A staff user of the given (or a new) school, with requests going to that school.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function schoolUser(?School $school = null, array $attributes = []): User
    {
        $school = $this->inSchool($school);

        return User::factory()->for($school)->create($attributes);
    }
}
