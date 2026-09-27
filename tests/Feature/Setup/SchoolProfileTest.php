<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = $this->schoolUser();
    $this->admin->assignRole('school_admin');
    $this->actingAs($this->admin);
});

function profileData(array $overrides = []): array
{
    return [
        'name_en' => 'Tongi Model High School',
        'name_bn' => 'টঙ্গী মডেল হাই স্কুল',
        'eiin' => '108000',
        'address' => 'Tongi, Gazipur',
        'phone' => '01711000000',
        'email' => 'office@example.com',
        'default_locale' => 'bn',
        'absent_sms_enabled' => true,
        ...$overrides,
    ];
}

test('the profile can be updated', function () {
    $this->post('/setup/profile', profileData())->assertSessionHasNoErrors();

    expect($this->admin->school->refresh()->only(['name_bn', 'eiin']))
        ->toBe(['name_bn' => 'টঙ্গী মডেল হাই স্কুল', 'eiin' => '108000']);
});

test('the logo is public and the signature is private', function () {
    $this->post('/setup/profile', profileData([
        'logo' => UploadedFile::fake()->image('logo.png'),
        'signature' => UploadedFile::fake()->image('signature.png'),
    ]))->assertSessionHasNoErrors();

    $school = $this->admin->school->refresh();
    Storage::disk('public')->assertExists($school->logo_path);
    Storage::disk('local')->assertExists($school->principal_signature_path);
    Storage::disk('public')->assertMissing($school->principal_signature_path);

    $this->get('/setup/profile/signature')->assertOk();
});

test('a new upload replaces the old file', function () {
    $this->post('/setup/profile', profileData(['logo' => UploadedFile::fake()->image('old.png')]));
    $old = $this->admin->school->refresh()->logo_path;

    $this->post('/setup/profile', profileData(['logo' => UploadedFile::fake()->image('new.png')]));

    Storage::disk('public')->assertMissing($old);
    Storage::disk('public')->assertExists($this->admin->school->refresh()->logo_path);
});

test('uploads must be small images', function () {
    $this->post('/setup/profile', profileData(['logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')]))
        ->assertSessionHasErrors('logo');
    $this->post('/setup/profile', profileData(['signature' => UploadedFile::fake()->image('big.png')->size(2048)]))
        ->assertSessionHasErrors('signature');
});

test('the signature is only served to setup admins', function () {
    $this->post('/setup/profile', profileData(['signature' => UploadedFile::fake()->image('signature.png')]));

    $teacher = $this->schoolUser($this->admin->school);
    $teacher->assignRole('teacher');

    $this->actingAs($teacher)->get('/setup/profile/signature')->assertForbidden();
});

test('absent SMS can be switched off', function () {
    $this->post('/setup/profile', profileData(['absent_sms_enabled' => false]))->assertSessionHasNoErrors();

    expect($this->admin->school->refresh()->absent_sms_enabled)->toBeFalse();
});
