<?php

use App\Models\School;

test('the school\'s default locale is used', function () {
    $this->inSchool(School::factory()->create(['default_locale' => 'bn']));

    $this->get('/login')->assertInertia(fn ($page) => $page->where('locale', 'bn'));
});

test('the locale can be switched', function () {
    $this->inSchool(School::factory()->create(['default_locale' => 'bn']));

    $this->from('/login')->post('/locale', ['locale' => 'en'])->assertRedirect('/login');

    $this->get('/login')->assertInertia(fn ($page) => $page->where('locale', 'en'));
});

test('an unsupported locale is rejected', function () {
    $this->inSchool();

    $this->post('/locale', ['locale' => 'fr'])->assertSessionHasErrors('locale');
});

test('validation messages follow the locale', function () {
    $this->inSchool(School::factory()->create(['default_locale' => 'bn']));

    $this->post('/login', ['login' => '', 'password' => ''])
        ->assertSessionHasErrors(['login' => 'ইমেইল বা মোবাইল নম্বর স্থানটি পূরণ করা বাধ্যতামূলক।']);
});

test('a failed login explains itself in Bangla', function () {
    $user = $this->schoolUser();

    $this->post('/login', ['login' => $user->email, 'password' => 'wrong'])
        ->assertSessionHasErrors(['login' => __('auth.failed', locale: 'bn')]);

    expect(__('auth.failed', locale: 'bn'))->not->toContain('পরিচয়পত্র');
});
