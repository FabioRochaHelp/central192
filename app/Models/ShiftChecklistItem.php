<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Quantidade de um recurso registrada no check-list de um turno. */
class ShiftChecklistItem extends Model
{
    protected $fillable = [
        'shift_id',
        'vehicle_checklist_resource_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(VehicleChecklistResource::class, 'vehicle_checklist_resource_id');
    }
}
