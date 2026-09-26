<?php

use App\Models\School;
use App\Support\CurrentSchool;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('roles are shared by all schools and the seeder is idempotent', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::count())->toBe(4)
        ->and(Role::whereNotNull('school_id')->count())->toBe(0)
        ->and(Permission::count())->toBe(count(RolesAndPermissionsSeeder::PERMISSIONS));
});

test('school_admin has every permission', function () {
    $user = $this->schoolUser();
    $user->assignRole('school_admin');

    foreach (RolesAndPermissionsSeeder::PERMISSIONS as $permission) {
        expect($user->can($permission))->toBeTrue();
    }
});

test('an accountant can collect fees but not enter marks', function () {
    $user = $this->schoolUser();
    $user->assignRole('accountant');

    expect($user->can('fees.collect'))->toBeTrue()
        ->and($user->can('marks.enter'))->toBeFalse();
});

test('a role given in one school grants nothing in another', function () {
    $user = $this->schoolUser();
    $user->assignRole('school_admin');

    app(CurrentSchool::class)->set(School::factory()->create());
    $user->unsetRelation('roles')->unsetRelation('permissions');

    expect($user->hasRole('school_admin'))->toBeFalse()
        ->and($user->can('students.view'))->toBeFalse();
});

test('the user\'s permissions are shared with the frontend', function () {
    $user = $this->schoolUser();
    $user->assignRole('teacher');

    $this->actingAs($user)->get('/dashboard')->assertInertia(fn ($page) => $page
        ->where('auth.permissions', fn ($permissions) => collect($permissions)->sort()->values()->all()
            === collect(RolesAndPermissionsSeeder::ROLES['teacher'])->sort()->values()->all()));
});

test('a user without a role gets no permissions', function () {
    $user = $this->schoolUser();

    $this->actingAs($user)->get('/dashboard')->assertInertia(fn ($page) => $page
        ->where('auth.permissions', []));
});
