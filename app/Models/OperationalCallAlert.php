<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Operations\Enums\OperationalCallAlertStatus;
use App\Support\Operations\OperationalCallAlertGrouper;
use Database\Factories\OperationalCallAlertFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalCallAlert extends Model
{
    /** @use HasFactory<OperationalCallAlertFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'phone',
        'caller_name',
        'latitude',
        'longitude',
        'call_received_at',
        'external_reference',
        'pabx_uniqueid',
        'metadata',
        'form_url',
        'expires_at',
        'status',
        'converted_incident_id',
        'aborted_by',
        'aborted_at',
        'monitored_by',
        'monitored_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OperationalCallAlertStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'metadata' => 'array',
            'call_received_at' => 'datetime',
            'expires_at' => 'datetime',
            'aborted_at' => 'datetime',
            'monitored_at' => 'datetime',
        ];
    }

    /** @param  Builder<self>  $query */
    public function scopeActiveOnMap(Builder $query): Builder
    {
        return $query
            ->where('status', OperationalCallAlertStatus::Pending)
            ->where('expires_at', '>', now());
    }

    public function convertedIncident(): BelongsTo
    {
        return $this->belongsTo(Incident::class, 'converted_incident_id');
    }

    public function abortedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aborted_by');
    }

    public function monitoredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'monitored_by');
    }

    public function isActionable(): bool
    {
        return $this->status === OperationalCallAlertStatus::Pending
            && $this->expires_at->isFuture();
    }

    public function resolvedTemperature(): ?float
    {
        $fromMetadata = $this->metadata['temperature'] ?? null;
        if ($fromMetadata === null || $fromMetadata === '') {
            return null;
        }

        return (float) $fromMetadata;
    }

    /** @return array<string, mixed> */
    public function toMapPayload(): array
    {
        return [
            'id' => $this->id,
            'lat' => (float) $this->latitude,
            'lng' => (float) $this->longitude,
            'phone' => $this->phone,
            'caller_name' => $this->caller_name,
            'temperature' => $this->resolvedTemperature(),
            'call_received_at' => $this->call_received_at?->toIso8601String(),
            'external_reference' => $this->external_reference,
            'metadata' => $this->metadata,
            'expires_at' => $this->expires_at->toIso8601String(),
            'status' => $this->status->value,
        ];
    }

    /** @return array<string, mixed> */
    public function toIntakePrefill(): array
    {
        return [
            'alert_id' => $this->id,
            'location_key' => OperationalCallAlertGrouper::locationKey(
                (float) $this->latitude,
                (float) $this->longitude,
            ),
            'form_url' => $this->form_url,
            'phone' => $this->phone,
            'expires_at' => $this->expires_at->toIso8601String(),
            'caller_name' => $this->caller_name,
            'latitude' => (string) $this->latitude,
            'longitude' => (string) $this->longitude,
            'call_received_at' => $this->call_received_at?->toIso8601String(),
            'external_reference' => $this->external_reference,
        ];
    }
}
