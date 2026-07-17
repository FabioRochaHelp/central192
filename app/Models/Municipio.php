<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @see docs/migracao/banco-dados.md — contract → municipios */
class Municipio extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'razao_social',
        'cnpj',
        'ie',
        'phone',
        'zipcode',
        'address',
        'number',
        'district',
        'city',
        'state',
        'active',
        'ibge_municipio_id',
        'dispatch_contact_ramal',
        'dispatch_contact_phone',
        'dispatch_contact_whatsapp',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function ibgeMunicipio(): BelongsTo
    {
        return $this->belongsTo(IbgeMunicipio::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function protectedAreas(): HasMany
    {
        return $this->hasMany(ProtectedArea::class);
    }
}
