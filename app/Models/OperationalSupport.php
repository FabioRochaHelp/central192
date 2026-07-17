<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tipos/órgãos de apoio — cadastro global. */
class OperationalSupport extends Model
{
    use SoftDeletes;

    protected $table = 'operational_supports';

    protected $fillable = [
        'name',
    ];
}
