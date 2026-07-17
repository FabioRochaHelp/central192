<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Unidades de destino/atendimento — cadastro global. */
class HealthUnit extends Model
{
    use SoftDeletes;

    protected $table = 'health_units';

    protected $fillable = [
        'name',
        'notes',
    ];
}
