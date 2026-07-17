<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProtectedAreaContact extends Model
{
    protected $fillable = [
        'protected_area_id',
        'name',
        'phone',
        'notify',
    ];

    protected function casts(): array
    {
        return [
            'notify' => 'boolean',
        ];
    }

    public function protectedArea(): BelongsTo
    {
        return $this->belongsTo(ProtectedArea::class);
    }
}
