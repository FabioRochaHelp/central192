<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToMunicipio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Victim extends Model
{
    use BelongsToMunicipio, SoftDeletes;

    protected $fillable = [
        'municipio_id',
        'incident_id',
        'victim_type_id',
        'care_local_id',
        'health_unit_id',
        'name',
        'sex',
        'rg',
        'age',
        'ssp',
        'situacao',
        'status',
        'hospital',
        'transporte',
        'unidade_saude',
        'medico_us',
        'crm_medico_us',
        'dados_complementares',
        'fall_height',
        'fall_height_meters',
        'halito_etilico',
        'burn',
        'burn_percentage',
        'vehicle_role',
        'accident_type',
        'pupil_notes',
        'pupil_light_reaction',
        'pupil_symmetry',
        'pupil_size',
        'pupil_side',
        'witness_name',
        'witness_rg',
        'witness_ssp',
        'death_where',
        'death_notes',
    ];

    protected function casts(): array
    {
        return [
            'fall_height' => 'boolean',
            'fall_height_meters' => 'decimal:2',
            'halito_etilico' => 'boolean',
            'burn' => 'boolean',
            'burn_percentage' => 'integer',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function victimType(): BelongsTo
    {
        return $this->belongsTo(VictimType::class);
    }

    public function careLocal(): BelongsTo
    {
        return $this->belongsTo(CareLocal::class);
    }

    public function healthUnit(): BelongsTo
    {
        return $this->belongsTo(HealthUnit::class);
    }

    public function vitalSigns(): HasMany
    {
        return $this->hasMany(VictimVitalSign::class)->orderBy('recorded_at');
    }

    public function procedures(): BelongsToMany
    {
        return $this->belongsToMany(Procedure::class, 'victim_procedure')->withTimestamps();
    }

    public function accessories(): BelongsToMany
    {
        return $this->belongsToMany(Accessory::class, 'victim_accessory')->withTimestamps();
    }

    public function injurySites(): BelongsToMany
    {
        return $this->belongsToMany(InjurySite::class, 'victim_injury_site')->withTimestamps();
    }

    public function injuryMatrixEntries(): HasMany
    {
        return $this->hasMany(VictimInjuryMatrixEntry::class)->orderBy('matrix_region')->orderBy('matrix_lesion');
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class)->latest();
    }
}
