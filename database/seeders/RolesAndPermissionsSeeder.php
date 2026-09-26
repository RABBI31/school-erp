<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles are global (school_id = null) and shared by every school; they are
 * assigned to users per school. Idempotent: safe to run again.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public const PERMISSIONS = [
        'setup.manage',
        'students.view',
        'students.manage',
        'staff.view',
        'staff.manage',
        'attendance.view',
        'attendance.take',
        'fees.view',
        'fees.manage',
        'fees.collect',
        'fees.reports',
        'payments.cancel',
        'exams.manage',
        'marks.enter',
        'results.view',
        'results.publish',
        'notices.manage',
        'sms.send',
    ];

    public const ROLES = [
        'school_admin' => self::PERMISSIONS,
        'accountant' => [
            'students.view',
            'fees.view',
            'fees.manage',
            'fees.collect',
            'fees.reports',
        ],
        'teacher' => [
            'students.view',
            'attendance.view',
            'attendance.take',
            'marks.enter',
            'results.view',
        ],
        'guardian' => [],
    ];

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = getPermissionsTeamId();
        setPermissionsTeamId(null);

        $registrar->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (self::ROLES as $name => $permissions) {
            Role::findOrCreate($name, 'web')->syncPermissions($permissions);
        }

        $registrar->forgetCachedPermissions();
        setPermissionsTeamId($previousTeamId);
    }
}
