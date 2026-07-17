<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Local auxiliar em vítima (legacy `local`) — cadastro global. */
class CareLocal extends Model
{
    use SoftDeletes;

    protected $table = 'care_locals';

    protected $fillable = [
        'name',
    ];
}
