<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Database\Factories\KnowledgeMissFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One question the knowledge base could not answer, logged by the search
 * tool itself. The teaching queue feeds on these; teaching the answer makes
 * the same question stop missing on its own.
 */
#[Fillable(['business_id', 'query'])]
class KnowledgeMiss extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<KnowledgeMissFactory> */
    use HasFactory;
}
