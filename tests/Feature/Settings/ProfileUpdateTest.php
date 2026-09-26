<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = $this->schoolUser();

    $this->actingAs($user)->get('/settings/profile')->assertOk();
});

test('profile information can be updated', function () {
    $user = $this->schoolUser();

    $this->actingAs($user)
        ->patch('/settings/profile', ['name' => 'Test User', 'email' => 'test@example.com'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    $user->refresh();
    expect($user->name)->toBe('Test User')
        ->and($user->email)->toBe('test@example.com');
});

test('email must be unique within the school', function () {
    $user = $this->schoolUser();
    User::factory()->for($user->school)->create(['email' => 'taken@example.com']);

    $this->actingAs($user)
        ->patch('/settings/profile', ['name' => 'Test User', 'email' => 'taken@example.com'])
        ->assertSessionHasErrors('email');
});

test('the same email may be used in another school', function () {
    User::factory()->create(['email' => 'shared@example.com']);
    $user = $this->schoolUser();

    $this->actingAs($user)
        ->patch('/settings/profile', ['name' => 'Test User', 'email' => 'shared@example.com'])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->email)->toBe('shared@example.com');
});
