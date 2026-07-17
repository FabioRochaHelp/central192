<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** @see docs/migracao/entidades.md — users_type */
class UserType extends Model
{
    protected $fillable = ['name'];
}
