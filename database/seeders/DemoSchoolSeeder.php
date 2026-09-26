<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Database\Seeder;

/**
 * The demo school on demo.localhost with its admin login. Idempotent.
 * Step 10 adds the rest of the demo data.
 */
class DemoSchoolSeeder extends Seeder
{
    public function run(CurrentSchool $currentSchool): void
    {
        $school = School::query()->updateOrCreate(['slug' => 'demo'], [
            'name_en' => 'Tongi Model High School',
            'name_bn' => 'টঙ্গী মডেল হাই স্কুল',
            'default_locale' => 'bn',
            'is_active' => true,
        ]);

        $previous = $currentSchool->get();
        $currentSchool->set($school);

        $admin = User::query()->firstOrNew(['school_id' => $school->id, 'email' => 'admin@demo']);
        $admin->forceFill([
            'user_type' => User::TYPE_STAFF,
            'name' => 'Demo Admin',
            'password' => 'password',
            'email_verified_at' => $admin->email_verified_at ?? now(),
        ])->save();
        $admin->syncRoles(['school_admin']);

        $currentSchool->set($previous);
    }
}
