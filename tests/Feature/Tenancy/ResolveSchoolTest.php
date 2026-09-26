<?php

use App\Models\School;
use Illuminate\Support\Facades\URL;

test('an unknown subdomain is 404', function () {
    URL::forceRootUrl('http://nope.'.config('app.domain'));

    $this->get('/login')->assertNotFound();
});

test('an inactive school is 404', function () {
    $this->inSchool(School::factory()->inactive()->create());

    $this->get('/login')->assertNotFound();
});

test('the bare domain shows the welcome page and has no school routes', function () {
    URL::forceRootUrl('http://'.config('app.domain'));

    $this->get('/')->assertOk();
    $this->get('/login')->assertNotFound();
    $this->get('/dashboard')->assertNotFound();
});

test('the school home redirects to the dashboard', function () {
    $this->inSchool();

    $this->get('/')->assertRedirect('/dashboard');
});

test('the school is shared with the frontend', function () {
    $school = $this->inSchool();

    $this->get('/login')->assertInertia(fn ($page) => $page
        ->where('school.slug', $school->slug)
        ->where('school.name_bn', $school->name_bn));
});
