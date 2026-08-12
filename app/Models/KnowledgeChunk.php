<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\KnowledgeChunkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeChunk extends Model
{
    /** @use HasFactory<KnowledgeChunkFactory> */
    use HasFactory;

    protected $fillable = [
        'knowledge_document_id',
        'company_id',
        'chunk_index',
        'content',
        'token_count',
        'embedding',
    ];

    protected function casts(): array
    {
        return [
            // pgvector nativo: la columna vector se castea a array de floats sin
            // dependencia ni cast propio (soporte nativo de Laravel 13).
            'embedding' => 'array',
        ];
    }

    /**
     * @return BelongsTo<KnowledgeDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'knowledge_document_id');
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
