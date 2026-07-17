<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Equipamentos/acessórios usados no atendimento — cadastro global. */
class Accessory extends Model
{
    use SoftDeletes;

    protected $table = 'accessories';

    protected $fillable = [
        'name',
    ];
}
