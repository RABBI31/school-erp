<?php

use App\Models\School;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = $this->schoolUser();
    $this->admin->assignRole('school_admin');
    $this->actingAs($this->admin);
    $this->staff = Staff::factory()->create(['phone' => '01911000001', 'email' => 'nasrin@example.com']);
});

test('a login can be created with a role, and it works with the staff phone', function () {
    $this->post("/staff/{$this->staff->id}/login", ['role' => 'teacher', 'password' => 'secret-pass-1'])->assertSessionHasNoErrors();

    $user = $this->staff->refresh()->user;
    expect($user->school_id)->toBe($this->admin->school_id)
        ->and($user->user_type)->toBe(User::TYPE_STAFF)
        ->and($user->phone)->toBe('01911000001')
        ->and($user->hasRole('teacher'))->toBeTrue();

    auth()->logout();
    $this->post('/login', ['login' => '01911000001', 'password' => 'secret-pass-1'])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
});

test('a login needs a phone or email on the staff record', function () {
    $this->staff->update(['phone' => null, 'email' => null]);

    $this->post("/staff/{$this->staff->id}/login", ['role' => 'teacher', 'password' => 'secret-pass-1'])->assertSessionHas('error');
    expect($this->staff->refresh()->user_id)->toBeNull();
});

test('the role can be changed and the password reset', function () {
    $this->post("/staff/{$this->staff->id}/login", ['role' => 'teacher', 'password' => 'secret-pass-1']);

    $this->put("/staff/{$this->staff->id}/login", ['role' => 'accountant', 'password' => 'new-secret-2'])->assertSessionHasNoErrors();

    $user = $this->staff->refresh()->user;
    expect($user->getRoleNames()->all())->toBe(['accountant'])
        ->and(Hash::check('new-secret-2', $user->password))->toBeTrue();

    $this->put("/staff/{$this->staff->id}/login", ['role' => 'teacher', 'password' => ''])->assertSessionHasNoErrors();
    expect(Hash::check('new-secret-2', $user->refresh()->password))->toBeTrue();
});

test('the login can be removed', function () {
    $this->post("/staff/{$this->staff->id}/login", ['role' => 'teacher', 'password' => 'secret-pass-1']);
    $userId = $this->staff->refresh()->user_id;

    $this->delete("/staff/{$this->staff->id}/login")->assertSessionHas('success');

    expect(User::find($userId))->toBeNull()->and($this->staff->refresh()->user_id)->toBeNull();
});

test('editing staff contact details keeps the login in sync', function () {
    $this->post("/staff/{$this->staff->id}/login", ['role' => 'teacher', 'password' => 'secret-pass-1']);

    $this->put("/staff/{$this->staff->id}", [
        'employee_code' => $this->staff->employee_code, 'name_en' => 'Renamed', 'phone' => '01911000009',
        'email' => 'new@example.com', 'status' => 'active',
    ])->assertSessionHasNoErrors();

    expect($this->staff->refresh()->user->only(['name', 'phone', 'email']))
        ->toBe(['name' => 'Renamed', 'phone' => '01911000009', 'email' => 'new@example.com']);
});

test('login phone and email must not clash with another user of the school', function () {
    User::factory()->for($this->admin->school)->create(['phone' => '01911000001']);
    User::factory()->for(School::factory()->create())->create(['email' => 'nasrin@example.com']); // other school: fine

    $this->post("/staff/{$this->staff->id}/login", ['role' => 'teacher', 'password' => 'secret-pass-1'])->assertSessionHasErrors('phone');

    $this->staff->update(['phone' => null]);
    $this->post("/staff/{$this->staff->id}/login", ['role' => 'teacher', 'password' => 'secret-pass-1'])->assertSessionHasNoErrors();
});

test('an admin cannot change their own role or remove their own login', function () {
    $own = Staff::factory()->create();
    $own->user()->associate($this->admin)->save();

    $this->put("/staff/{$own->id}/login", ['role' => 'teacher', 'password' => ''])->assertSessionHas('error');
    $this->delete("/staff/{$own->id}/login")->assertSessionHas('error');
    $this->delete("/staff/{$own->id}")->assertSessionHas('error');

    expect($this->admin->refresh()->hasRole('school_admin'))->toBeTrue();
});

test('a staff member who has left cannot sign in', function () {
    $this->post("/staff/{$this->staff->id}/login", ['role' => 'teacher', 'password' => 'secret-pass-1']);

    $this->put("/staff/{$this->staff->id}", [
        'employee_code' => $this->staff->employee_code, 'name_en' => $this->staff->name_en,
        'phone' => $this->staff->phone, 'email' => $this->staff->email, 'status' => 'left',
    ])->assertSessionHasNoErrors();

    expect($this->staff->refresh()->user_id)->toBeNull();
});

test('the role must be a school role', function () {
    $this->post("/staff/{$this->staff->id}/login", ['role' => 'platform_admin', 'password' => 'secret-pass-1'])->assertSessionHasErrors('role');
    $this->post("/staff/{$this->staff->id}/login", ['role' => 'guardian', 'password' => 'secret-pass-1'])->assertSessionHasErrors('role');
    $this->post("/staff/{$this->staff->id}/login", ['role' => 'teacher', 'password' => 'short'])->assertSessionHasErrors('password');
});
