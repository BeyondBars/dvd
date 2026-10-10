<?php

namespace App\Models;

use App\Notifications\Auth\TwoFactorVerificationNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use App\Support\Enums\UserRole;
use App\Support\Enums\UserStatus;
use App\Support\Traits\VerifiesTwoFactorCodes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Notification;
use Silber\Bouncer\Database\HasRolesAndAbilities;

class User extends Authenticatable
{
    use HasFactory;
    use HasRolesAndAbilities;
    use Notifiable;
    use VerifiesTwoFactorCodes;

    public const TWO_FACTOR_METHODS = ['email', 'sms'];

    protected $fillable = [
        'first_name',
        'last_name',
        'mobile',
        'email',
        'new_email',
        'new_email_requested_at',
        'password',
        'status',
        'provider',
        'provider_id',
        'last_login_at',
        'last_login_ip',
        'terms_accepted_at',
        'two_factor_required',
        'two_factor_method_enabled',
        'two_factor_method_preferred',
        'two_factor_secret',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    /**
     * Default attribute values (mirrors the database defaults so new models are consistent).
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'password' => 'hashed',
            'two_factor_required' => 'boolean',
            'email_verified_at' => 'datetime',
            'new_email_requested_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function verificationCodes(): HasMany
    {
        return $this->hasMany(UserVerificationCode::class);
    }

    /**
     * A user has a single role through Bouncer's assigned roles table.
     */
    public function role(): HasOneThrough
    {
        return $this->hasOneThrough(Role::class, AssignedRole::class, 'entity_id', 'id', 'id', 'role_id')
            ->where('assigned_roles.entity_type', $this->getMorphClass());
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes & accessors
    |--------------------------------------------------------------------------
    */

    public function scopeEnabled($query): void
    {
        $query->where('users.status', UserStatus::Enabled->value);
    }

    public function getNameAttribute(): ?string
    {
        return trim(sprintf('%s %s', $this->first_name, $this->last_name));
    }

    public function isDisabled(): bool
    {
        return $this->status === UserStatus::Disabled;
    }

    /*
    |--------------------------------------------------------------------------
    | Roles & abilities
    |--------------------------------------------------------------------------
    */

    /**
     * Load the user's effective abilities: their role's abilities plus the ones given to them
     * directly, minus any forbidden to them directly (see App\Services\UserPermissions).
     */
    public function loadAbilities(): self
    {
        $this->loadMissing('role.abilities', 'abilities');

        [$forbidden, $allowed] = $this->abilities->partition(fn ($ability) => (bool) $ability->pivot->forbidden);

        $this->setRelation('abilities', $allowed
            ->merge($this->role?->abilities ?? [])
            ->unique('id')
            ->whereNotIn('id', $forbidden->modelKeys())
            ->values());

        return $this;
    }

    public function requiredPasswordLength(): int
    {
        return $this->role?->name === UserRole::SuperAdmin->value ? 12 : 8;
    }

    /*
    |--------------------------------------------------------------------------
    | Email verification
    |--------------------------------------------------------------------------
    */

    /**
     * Send the email verification link. When $email is given (an email change) the link goes to
     * that address and the signature is bound to it, so it can only verify that exact address.
     */
    public function sendEmailVerificationNotification(?string $email = null): void
    {
        $notification = new VerifyEmailNotification($this->emailVerificationSignatureParameters($email));

        if ($email) {
            Notification::route('mail', $email)->notify($notification);

            return;
        }

        $this->notify($notification);
    }

    /**
     * Parameters used to sign and validate an email verification link.
     */
    public function emailVerificationSignatureParameters(?string $email = null): array
    {
        return [
            'id' => $this->getKey(),
            'hash' => sha1($email ?? $this->getEmailForVerification()),
        ];
    }

    public function isEmailVerified(): bool
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Promote a pending (verified) new email address to be the user's email.
     */
    public function markNewEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email' => $this->new_email,
            'new_email' => null,
            'new_email_requested_at' => null,
            'email_verified_at' => now(),
        ])->save();
    }

    /**
     * Determine if the user has verified their sign-up email signature.
     */
    public function hasVerifiedSignature(): bool
    {
        return EmailSignature::where('email', $this->email)->whereNotNull('verified_at')->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Two factor
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the user must pass a 2FA challenge after logging in.
     */
    public function needsTwoFactor(): bool
    {
        return $this->two_factor_required === true
            && in_array($this->two_factor_method_enabled, self::TWO_FACTOR_METHODS, true);
    }

    /**
     * Create a new verification code, replacing any previous code for the same method.
     */
    public function createVerificationCode(string $method): UserVerificationCode
    {
        $this->verificationCodes()->where('method', $method)->delete();

        return $this->verificationCodes()->create([
            'method' => $method,
            'code' => (string) random_int(100000, 999999),
        ]);
    }

    /**
     * Create and send a 2FA code via the given method ("email" or "sms").
     */
    public function sendTwoFactorCode(string $method): void
    {
        $this->notify(new TwoFactorVerificationNotification($this->createVerificationCode($method), $method));
    }

    /**
     * Route SMS notifications (Vonage) to the user's mobile in international format.
     */
    public function routeNotificationForVonage($notification = null): ?string
    {
        if (!$this->mobile) {
            return null;
        }

        $number = preg_replace('/\D+/', '', $this->mobile);

        // Australian local format (04xx xxx xxx) -> 614xx xxx xxx
        return str_starts_with($number, '0') ? '61'.substr($number, 1) : $number;
    }
}
