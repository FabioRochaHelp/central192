<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Operations\Enums\StaffCargo;
use App\Models\Concerns\BelongsToMunicipio;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    use BelongsToMunicipio, SoftDeletes;

    protected $table = 'staff';

    protected $fillable = [
        'municipio_id',
        'name',
        'document_type',
        'document_number',
        'cpf',
        'email',
        'phone',
        'cargo',
    ];

    /**
     * Usa tryFrom() para ser resiliente a valores legados (ex: inteiro 2)
     * que podem existir no banco antes da migration de conversão rodar.
     */
    protected function cargo(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value !== null ? StaffCargo::tryFrom((string) $value) : null,
            set: fn ($value) => $value instanceof StaffCargo ? $value->value : $value,
        );
    }

    public function shifts(): BelongsToMany
    {
        return $this->belongsToMany(Shift::class, 'shift_staff')->withTimestamps();
    }
}
