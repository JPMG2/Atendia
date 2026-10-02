<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\HelpArticle;
use App\Services\Knowledge\KnowledgeEmbedder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Fills the meaning lane of the help. Run by hand after writing or editing
 * articles — not on save: a dozen fixed documents are not worth an API call
 * every time someone corrects a typo.
 */
#[Signature('atendia:embed-help')]
#[Description('Embeds the help articles that still have no vector')]
class EmbedHelpArticles extends Command
{
    public function handle(KnowledgeEmbedder $embedder): int
    {
        $pending = HelpArticle::withoutEmbedding();

        if ($pending->isEmpty()) {
            $this->info('Nothing to embed.');

            return self::SUCCESS;
        }

        // ONE call for the whole batch: a loop of calls is the same work at
        // ten times the price.
        $vectors = $embedder->embed(
            $pending->map(fn (HelpArticle $article): string => $article->embeddableText())->all(),
        );

        foreach ($pending->values() as $index => $article) {
            if (! isset($vectors[$index])) {
                continue;
            }

            $article->forceFill(['embedding' => $vectors[$index]])->saveQuietly();
        }

        $this->info($pending->count().' article(s) embedded.');

        return self::SUCCESS;
    }
}
