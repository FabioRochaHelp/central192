<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PrescriptionItem extends Model
{
    protected $fillable = [
        'prescription_id',
        'stock_id',
        'material_id',
        'medication_name',
        'quantity',
    ];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }
}
