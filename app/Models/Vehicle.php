<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToMunicipio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use BelongsToMunicipio, SoftDeletes;

    protected $fillable = [
        'municipio_id',
        'plate',
        'prefix',
        'make',
        'model',
        'year',
        'status_legacy',
        'device_id',
        'ssx_integration_code',
    ];

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function position(): HasOne
    {
        return $this->hasOne(VehiclePosition::class);
    }
}
