<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Operations\Enums\UserLegacyProfile;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable([
    'name',
    'email',
    'password',
    'municipio_id',
    'user_type_id',
    'staff_id',
    'users_type_legacy',
    'active_operational',
    'call_intake_websocket_enabled',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active_operational' => 'boolean',
            'call_intake_websocket_enabled' => 'boolean',
        ];
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function userType(): BelongsTo
    {
        return $this->belongsTo(UserType::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function legacyProfile(): ?UserLegacyProfile
    {
        if ($this->users_type_legacy === null) {
            return null;
        }

        return UserLegacyProfile::tryFrom((int) $this->users_type_legacy);
    }

    /** Central ampla / administrativo (legado users_type ≤ 2). */
    public function isOperationalCentral(): bool
    {
        return $this->legacyProfile()?->isCentral() ?? false;
    }

    /**
     * Administrador da central — gestão de identidades/usuários do sistema.
     *
     * @see docs/migracao/entidades.md — `users_type <= 2` como acesso central; o perfil 1
     *      corresponde ao nível administrativo que mantém cadastro de usuários.
     */
    public function isCentralAdministrator(): bool
    {
        return $this->users_type_legacy === UserLegacyProfile::CentralAdministrator->value;
    }

    /** Perfis com visão em todas as bases (central, despachador e atendente). */
    public function hasMultiMunicipioAccess(): bool
    {
        return $this->legacyProfile()?->hasMultiMunicipioAccess() ?? false;
    }

    public function canViewOperationalIncidents(): bool
    {
        return $this->hasOperationalAbility('dispatch.view')
            || $this->hasOperationalAbility('incident.view');
    }

    public function receivesOperationalCallIntakeBroadcast(): bool
    {
        $profile = $this->legacyProfile();

        if ($profile === UserLegacyProfile::Attendant) {
            return true;
        }

        if ($profile === UserLegacyProfile::Dispatcher) {
            return (bool) $this->call_intake_websocket_enabled;
        }

        return false;
    }

    /** Bridge no layout para Atendente/Despachador mesmo antes de ativar o websocket (toggle). */
    public function shouldMountOperationalCallIntakeBridge(): bool
    {
        if (! $this->hasOperationalAbility('incident.create')) {
            return false;
        }

        return in_array($this->legacyProfile(), [UserLegacyProfile::Attendant, UserLegacyProfile::Dispatcher], true);
    }

    /** @return list<string> */
    public function operationalAbilities(): array
    {
        return $this->legacyProfile()?->abilities() ?? [];
    }

    public function hasOperationalAbility(string $ability): bool
    {
        $abilities = $this->operationalAbilities();

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }

    /** Escopo operacional por `municipio_id` (docs/migracao). */
    public function canAccessOperationalMunicipio(?int $municipioId): bool
    {
        if ($municipioId === null) {
            return false;
        }

        if ($this->hasMultiMunicipioAccess()) {
            return true;
        }

        return $this->municipio_id !== null && (int) $this->municipio_id === $municipioId;
    }
}
