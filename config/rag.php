<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Embeddings
    |--------------------------------------------------------------------------
    |
    | Modelo y dimensiones para embeddear el conocimiento. La dimensión queda
    | FIJA en la columna `vector(dimensions)` de knowledge_chunks: cambiarla
    | obliga a re-embeddear todo. Se centraliza acá para que el indexado y la
    | consulta usen SIEMPRE el mismo modelo (si no, la similitud es basura).
    | El proveedor de embeddings es OpenAI (config/ai.php default_for_embeddings).
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
