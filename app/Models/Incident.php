<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Enums\ManchesterRisk;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Incident extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'municipio_id',
        'dispatch_year',
        'talao',
        'status',
        'nature_id',
        'primary_shift_id',
        'occurred_at',
        'call_received_at',
        'address_line',
        'number',
        'district',
        'city',
        'reference_notes',
        'description',
        'caller_name',
        'caller_phone',
        'pabx_uniqueid',
        'patient_age',
        'patient_sex',
        'patient_name',
        'patient_call_type',
        'manchester_risk',
        'is_qta',
        'expected_victim_total',
        'total_death_count',
        'dispatched_at',
        'departed_base_at',
        'arrived_scene_at',
        'left_scene_at',
        'arrived_hospital_at',
        'released_hospital_at',
        'returned_base_at',
        'latitude',
        'longitude',
        'protected_area_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => IncidentStatus::class,
            'manchester_risk' => ManchesterRisk::class,
            'occurred_at' => 'datetime',
            'call_received_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'departed_base_at' => 'datetime',
            'arrived_scene_at' => 'datetime',
            'left_scene_at' => 'datetime',
            'arrived_hospital_at' => 'datetime',
            'released_hospital_at' => 'datetime',
            'returned_base_at' => 'datetime',
            'is_qta' => 'boolean',
        ];
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function nature(): BelongsTo
    {
        return $this->belongsTo(Nature::class);
    }

    public function primaryShift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'primary_shift_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function protectedArea(): BelongsTo
    {
        return $this->belongsTo(ProtectedArea::class);
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(IncidentDispatch::class);
    }

    public function incidentEvents(): HasMany
    {
        return $this->hasMany(IncidentEvent::class)->orderByDesc('recorded_at');
    }

    /** @deprecated usar incidentEvents; mantido para compatibilidade com blades existentes */
    public function timelineEvents(): HasMany
    {
        return $this->incidentEvents();
    }

    public function victims(): HasMany
    {
        return $this->hasMany(Victim::class);
    }

    public function nurseReport(): HasOne
    {
        return $this->hasOne(IncidentNurseReport::class);
    }

    public function regulation(): HasOne
    {
        return $this->hasOne(IncidentRegulation::class);
    }

    public function finalReport(): HasOne
    {
        return $this->hasOne(IncidentFinalReport::class);
    }

    /** Solicitações adicionais recebidas para este mesmo ponto, uma por ligação. */
    public function callRequests(): HasMany
    {
        return $this->hasMany(IncidentCallRequest::class)->orderByDesc('created_at');
    }

    /** Total de solicitações do ponto: a chamada que abriu a ocorrência mais as adicionais. */
    public function totalCallRequestCount(): int
    {
        $additional = $this->call_requests_count ?? $this->callRequests()->count();

        return 1 + (int) $additional;
    }

    /** Alertas operacionais convertidos nesta ocorrência (`operational_call_alerts.converted_incident_id`). */
    public function operationalCallAlerts(): HasMany
    {
        return $this->hasMany(OperationalCallAlert::class, 'converted_incident_id');
    }

    public function activeDispatch(): ?IncidentDispatch
    {
        return $this->activeDispatches()->orderByDesc('id')->first();
    }

    /** @return HasMany<IncidentDispatch, $this> */
    public function activeDispatches(): HasMany
    {
        return $this->dispatches()->whereNull('deleted_at');
    }
}
