<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Notifications\Customer\ResetPasswordNotification;
use App\Notifications\Customer\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\URL;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Customer extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, LogsActivity, Notifiable, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty();
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'company_name',
        'status',
        'email_verified_at',
        'tax_exempt',
        'tax_identification_number',
        'exemption_certificate_number',
        'entity_use_code',
        'exemption_signed_date',
        'exemption_expires_at',
        'exemption_exposure_zone',
    ];

    // avatax_customer_id, avatax_certificate_id, avatax_synced_at are server-set
    // only (forceFill in SyncCustomerToAvaTaxJob) — never mass-assignable.

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'status' => CustomerStatus::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'tax_exempt' => 'boolean',
            'avatax_synced_at' => 'datetime',
            'exemption_signed_date' => 'date',
            'exemption_expires_at' => 'date',
        ];
    }

    public function sendEmailVerificationNotification(): void
    {
        $url = URL::temporarySignedRoute(
            'portal.verification.verify',
            now()->addMinutes(60),
            ['id' => $this->getKey(), 'hash' => sha1($this->getEmailForVerification())],
        );

        $this->notify(new VerifyEmailNotification($url));
    }

    public function sendPasswordResetNotification($token): void
    {
        $url = route('portal.password.reset', [
            'token' => $token,
            'email' => $this->getEmailForPasswordReset(),
        ]);

        $this->notify(new ResetPasswordNotification($url));
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function scopeByStatus(Builder $query, CustomerStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('company_name', 'like', "%{$term}%");
        });
    }
}
