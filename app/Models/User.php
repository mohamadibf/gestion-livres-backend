<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /** Limite d'emprunts simultanés applicable à ce lecteur (exception, sinon réglage global). 0 = illimité. */
    public function effectiveLoanLimit(): int
    {
        return $this->loan_limit ?? (int) app(\App\Services\Settings::class)->get('max_active_loans');
    }

    /** Durée de prêt applicable à ce lecteur, en jours. */
    public function effectiveLoanDuration(): int
    {
        return $this->loan_duration_days ?? (int) app(\App\Services\Settings::class)->get('loan_duration_days');
    }

    public const ROLE_ADMIN = 'admin';

    public const ROLE_READER = 'reader';

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'loan_limit' => 'integer',
            'loan_duration_days' => 'integer',
        ];
    }

    public function activeLoans(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Loan::class)->whereNull('returned_at');
    }

    public function loans(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Loan::class);
    }
}
