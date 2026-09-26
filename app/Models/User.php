<?php

namespace App\Models;

use App\Support\Phone;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Not scoped by BelongsToSchool: platform admins have no school, and the
 * auth provider must load the user before a school is known. Login and
 * ResolveSchool check school_id explicitly instead.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public const TYPE_PLATFORM_ADMIN = 'platform_admin';

    public const TYPE_STAFF = 'staff';

    public const TYPE_GUARDIAN = 'guardian';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $attributes = [
        'user_type' => self::TYPE_STAFF,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    protected function email(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null || $value === '' ? null : strtolower(trim($value)));
    }

    protected function phone(): Attribute
    {
        return Attribute::set(fn (?string $value) => Phone::normalize($value));
    }
}
