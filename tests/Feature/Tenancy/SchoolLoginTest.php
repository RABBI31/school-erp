<?php

use App\Models\School;
use App\Models\User;

test('users can log in with their phone number in any common format', function (string $typed) {
    $user = $this->schoolUser(attributes: ['email' => null, 'phone' => '01711000000']);

    $this->post('/login', ['login' => $typed, 'password' => 'password'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
})->with(['01711000000', '+880 1711-000000', '8801711000000']);

test('a user cannot log in on another school\'s subdomain', function () {
    $user = $this->schoolUser();
    $this->inSchool(School::factory()->create());

    $this->post('/login', ['login' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('login');

    $this->assertGuest();
});

test('the same email in two schools logs into the right account', function () {
    [$a, $b] = School::factory()->count(2)->create();
    $inA = User::factory()->for($a)->create(['email' => 'rahim@example.com', 'password' => 'secret-a']);
    $inB = User::factory()->for($b)->create(['email' => 'rahim@example.com', 'password' => 'secret-b']);

    $this->inSchool($b);
    $this->post('/login', ['login' => 'rahim@example.com', 'password' => 'secret-a'])->assertSessionHasErrors('login');
    $this->post('/login', ['login' => 'rahim@example.com', 'password' => 'secret-b']);

    $this->assertAuthenticatedAs($inB);
    expect($inA->id)->not->toBe($inB->id);
});

test('a session from one school is logged out on another school\'s subdomain', function () {
    $user = $this->schoolUser();
    $this->inSchool(School::factory()->create());

    $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');

    $this->assertGuest();
});

test('a platform admin cannot log in on a school subdomain', function () {
    $admin = User::factory()->platformAdmin()->create();
    $this->inSchool();

    $this->post('/login', ['login' => $admin->email, 'password' => 'password'])
        ->assertSessionHasErrors('login');

    $this->assertGuest();
});
