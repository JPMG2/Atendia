<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Embeddings
    |--------------------------------------------------------------------------
    |
    | Baseline model and length of the knowledge vectors: it is what rules until a
    | change of model is switched on from Admin > AI models, and what a fresh
    | database is built with. Once switched, the model in force lives in the
    | `embedding_migrations` table (App\Classes\Main\EmbeddingSpace), not here.
    |
    */

    'embedding' => [
        'model' => 'text-embedding-3-small',
        'dimensions' => 1536,
    ],

    /*
    |--------------------------------------------------------------------------
    | Chunking
    |--------------------------------------------------------------------------
    |
    | Tamaño de cada fragmento (aprox. en caracteres; ~4 chars ≈ 1 token) y el
    | solape entre fragmentos contiguos para no cortar ideas al medio.
    |
    */

    'chunk' => [
        'max_chars' => 3200,   // ~800 tokens
        'overlap_chars' => 480, // ~15 %
    ],

    /*
    |--------------------------------------------------------------------------
    | Recuperación (retrieval)
    |--------------------------------------------------------------------------
    |
    | Cuántos fragmentos se traen por consulta y el piso de similitud coseno
    | (0.0–1.0, 1.0 = idéntico) por debajo del cual se descartan.
    |
    */

    'retrieval' => [
        'top_k' => 5,
        'min_similarity' => 0.35,
    ],

];
