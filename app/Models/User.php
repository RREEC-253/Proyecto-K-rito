<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasUuids, SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'username',
        'email',
        'password',
        'first_name',
        'last_name',
        'phone',
        'avatar',
        'status',
        'email_verified',
        'must_change_password',
        'failed_login_attempts',
        'locked_until',
        'last_login',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'deleted_at',
    ];

    protected $casts = [
        'status' => 'boolean',
        'email_verified' => 'boolean',
        'must_change_password' => 'boolean',
        'failed_login_attempts' => 'integer',
        'locked_until' => 'datetime',
        'last_login' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot('created_at');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(UserToken::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function getAuthPassword(): string
    {
        return $this->password;
    }
}
