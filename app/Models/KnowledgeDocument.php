<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\KnowledgeDocumentObserver;
use App\Traits\BelongsToBusiness;
use Database\Factories\KnowledgeDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(KnowledgeDocumentObserver::class)]
class KnowledgeDocument extends Model
{
    /** @use HasFactory<KnowledgeDocumentFactory> */
    use BelongsToBusiness;

    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'business_id',
        'title',
        'source_type',
        'content',
        'content_hash',
        'status',
        'indexed_at',
    ];

    protected function casts(): array
    {
        return [
            'indexed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<KnowledgeChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class);
    }
}
