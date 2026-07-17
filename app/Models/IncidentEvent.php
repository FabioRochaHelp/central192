<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToMunicipio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @see docs/migracao/plano-migracao-laravel.md — incident_events */
class IncidentEvent extends Model
{
    use BelongsToMunicipio;

    public $timestamps = false;

    protected $table = 'incident_events';

    protected $fillable = [
        'municipio_id',
        'incident_id',
        'event_key',
        'payload',
        'actor_id',
        'source',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
