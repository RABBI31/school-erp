<?php

use App\Models\School;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('seeding twice creates nothing new', function () {
    $this->seed(DatabaseSeeder::class);
    $counts = [School::count(), User::count(), DB::table('model_has_roles')->count()];

    $this->seed(DatabaseSeeder::class);

    expect([School::count(), User::count(), DB::table('model_has_roles')->count()])->toBe($counts);
});

test('the demo admin can log in on the demo school', function () {
    $this->seed(DatabaseSeeder::class);
    $this->inSchool(School::where('slug', 'demo')->firstOrFail());

    $this->post('/login', ['login' => 'admin@demo', 'password' => 'password'])->assertRedirect('/dashboard');

    expect(auth()->user()->can('results.publish'))->toBeTrue();
});
