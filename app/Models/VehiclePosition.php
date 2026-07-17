<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehiclePosition extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'vehicle_id',
        'device_id',
        'latitude',
        'longitude',
        'altitude',
        'speed_kmh',
        'course',
        'address',
        'valid',
        'fix_time',
        'synced_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'altitude' => 'float',
        'speed_kmh' => 'float',
        'course' => 'float',
        'valid' => 'boolean',
        'fix_time' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
