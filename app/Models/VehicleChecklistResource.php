<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToMunicipio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Item de referência do check-list de viatura (ex.: Água — litros). */
class VehicleChecklistResource extends Model
{
    use BelongsToMunicipio;
    use SoftDeletes;

    protected $fillable = [
        'municipio_id',
        'name',
        'unit_of_measure',
    ];

    public function shiftChecklistItems(): HasMany
    {
        return $this->hasMany(ShiftChecklistItem::class);
    }
}
