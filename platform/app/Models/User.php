<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, \Illuminate\Database\Eloquent\Concerns\HasUuids, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = [];

    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    protected static function booted(): void
    {
        static::updating(function (User $user) {
            if ($user->isDirty('is_synthetic') && Enrolment::where('member_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['is_synthetic' => 'An existing training history cannot change synthetic status.']);
            }
        });
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permission_version' => 'integer',
            'active' => 'boolean',
            'is_synthetic' => 'boolean',
            'sensitive_access' => 'boolean',
            'permissions_synced_at' => 'immutable_datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
