<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Operations\Enums\PrescriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Prescription extends Model
{
    protected $fillable = [
        'victim_id',
        'medical_staff_id',
        'prescribed_by_user_id',
        'status',
        'description',
        'approved_by_user_id',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PrescriptionStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function victim(): BelongsTo
    {
        return $this->belongsTo(Victim::class);
    }

    public function medicalStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'medical_staff_id');
    }

    public function prescribedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prescribed_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }
}
