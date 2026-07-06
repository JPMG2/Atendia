<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CurrentStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name'])]
class CurrentStatus extends Model
{
    /** @use HasFactory<CurrentStatusFactory> */
    use HasFactory;
}
