<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Operations\Enums\ShiftStatus;
use App\Models\Concerns\BelongsToMunicipio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use BelongsToMunicipio;

    protected $fillable = [
        'municipio_id',
        'vehicle_id',
        'starts_at',
        'ends_at',
        'status',
        'status_legacy',
        'available_at',
        'checklist_completed_at',
        'checklist_observation',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'available_at' => 'datetime',
            'status' => ShiftStatus::class,
            'checklist_completed_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'shift_staff')->withTimestamps();
    }

    public function incidentDispatches(): HasMany
    {
        return $this->hasMany(IncidentDispatch::class);
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(ShiftChecklistItem::class);
    }

    public function isChecklistComplete(): bool
    {
        return $this->checklist_completed_at !== null;
    }

    public function isChecklistPending(): bool
    {
        return ! $this->isChecklistComplete()
            && $this->starts_at->isPast()
            && $this->ends_at->isFuture();
    }

    public function scopeOperationalAvailability(Builder $query): Builder
    {
        return $query->where('ends_at', '>=', now())
            ->where('status', ShiftStatus::Disponivel);
    }

    /** Turnos vigentes disponíveis ou já empenhados (multi-ocorrência). */
    public function scopeOperationalForDispatch(Builder $query): Builder
    {
        return $query->where('ends_at', '>=', now())
            ->whereIn('status', [ShiftStatus::Disponivel, ShiftStatus::Empenhado]);
    }

    /** Turnos vigentes indisponíveis para empenho (baixado, oficina, acidente). */
    public function scopeOperationalIdle(Builder $query): Builder
    {
        return $query->where('ends_at', '>=', now())
            ->whereIn('status', [
                ShiftStatus::Baixado,
                ShiftStatus::Oficina,
                ShiftStatus::Acidente,
            ]);
    }
}
