<?php

namespace App\Services\Staff;

use App\Exceptions\BusinessRuleException;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A staff member's login: a User in the same school, signing in with the
 * staff phone or email, with one school role.
 */
class StaffLoginService
{
    public const ROLES = ['school_admin', 'accountant', 'teacher'];

    public function create(Staff $staff, string $role, string $password): User
    {
        if ($staff->phone === null && $staff->email === null) {
            throw new BusinessRuleException(__('messages.login_needs_contact'));
        }

        $this->ensureContactIsFree($staff);

        return DB::transaction(function () use ($staff, $role, $password) {
            $user = new User;
            $user->forceFill([
                'school_id' => $staff->school_id,
                'user_type' => User::TYPE_STAFF,
                'name' => $staff->name_en,
                'email' => $staff->email,
                'phone' => $staff->phone,
                'password' => $password,
                'email_verified_at' => now(),
            ])->save();
            $user->assignRole($role);

            $staff->user()->associate($user)->save();

            return $user;
        });
    }

    public function update(Staff $staff, string $role, ?string $password, User $actor): void
    {
        $user = $staff->user ?? throw new BusinessRuleException(__('messages.no_login'));

        if ($user->is($actor) && ! $user->hasRole($role)) {
            throw new BusinessRuleException(__('messages.own_role'));
        }

        DB::transaction(function () use ($user, $role, $password) {
            $user->syncRoles([$role]);

            if ($password !== null && $password !== '') {
                $user->forceFill(['password' => $password])->save();
            }
        });
    }

    public function remove(Staff $staff, User $actor): void
    {
        $user = $staff->user;

        if ($user === null) {
            return;
        }

        if ($user->is($actor)) {
            throw new BusinessRuleException(__('messages.own_login'));
        }

        DB::transaction(function () use ($staff, $user) {
            $staff->user()->dissociate()->save();
            $user->delete();
        });
    }

    /**
     * Keep the login's name and contact details the same as the staff record.
     */
    public function sync(Staff $staff): void
    {
        $staff->user?->forceFill(['name' => $staff->name_en, 'email' => $staff->email, 'phone' => $staff->phone])->save();
    }

    private function ensureContactIsFree(Staff $staff): void
    {
        $taken = fn (string $column) => $staff->{$column} !== null
            && User::query()->where('school_id', $staff->school_id)->where($column, $staff->{$column})->exists();

        $errors = array_filter([
            'phone' => $taken('phone') ? __('validation.unique', ['attribute' => __('validation.attributes.phone')]) : null,
            'email' => $taken('email') ? __('validation.unique', ['attribute' => __('validation.attributes.email')]) : null,
        ]);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
