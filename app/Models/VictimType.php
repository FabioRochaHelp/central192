<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tipo de vítima (legacy `vitima_tipo`) — cadastro global. */
class VictimType extends Model
{
    use SoftDeletes;

    protected $table = 'victim_types';

    protected $fillable = [
        'name',
    ];
}
