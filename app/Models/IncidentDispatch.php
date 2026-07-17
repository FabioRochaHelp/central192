<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Operations\Enums\DispatchStage;
use App\Models\Concerns\BelongsToMunicipio;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IncidentDispatch extends Model
{
    use BelongsToMunicipio, SoftDeletes;

    protected $fillable = [
        'municipio_id',
        'incident_id',
        'shift_id',
        'stage',
        'stage_position',
        'notes_seen_at',
        'dispatched_at',
        'departed_base_at',
        'arrived_scene_at',
        'left_scene_at',
        'arrived_hospital_at',
        'released_hospital_at',
        'returned_base_at',
        'released_at',
        'cancelled_at_scene_at',
        'scene_cancel_reason',
        'is_primary',
    ];

    protected static function booted(): void
    {
        static::saving(function (IncidentDispatch $dispatch): void {
            if ($dispatch->stage instanceof DispatchStage) {
                $dispatch->stage_position = $dispatch->stage->index() + 1;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'stage' => DispatchStage::class,
            'notes_seen_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'departed_base_at' => 'datetime',
            'arrived_scene_at' => 'datetime',
            'left_scene_at' => 'datetime',
            'arrived_hospital_at' => 'datetime',
            'released_hospital_at' => 'datetime',
            'returned_base_at' => 'datetime',
            'released_at' => 'datetime',
            'cancelled_at_scene_at' => 'datetime',
            'is_primary' => 'boolean',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function applyStageTimestamp(DispatchStage $stage, CarbonInterface $at): void
    {
        match ($stage) {
            DispatchStage::Dispatched => $this->dispatched_at = $this->dispatched_at ?? $at,
            DispatchStage::DepartedBase => $this->departed_base_at = $at,
            DispatchStage::ArrivedScene => $this->arrived_scene_at = $at,
            DispatchStage::LeftScene => $this->left_scene_at = $at,
            DispatchStage::ArrivedHospital => $this->arrived_hospital_at = $at,
            DispatchStage::ReleasedHospital => $this->released_hospital_at = $at,
        };
    }
}
