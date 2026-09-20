<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Ai\Agents\FaqDrafter;
use App\Models\Business;
use App\Services\Knowledge\KnowledgeRetriever;
use Throwable;

/**
 * For each unanswered question, hunts NEAR-MISS knowledge — content that
 * existed but ranked under the answering floor — and drafts a suggested
 * answer from it. No near-miss, no draft: the owner answers those by hand.
 */
class DraftFaqSuggestions
{
    /** Looser than answering on purpose: the near-miss is the prey. */
    private const float DRAFT_SIMILARITY = 0.18;

    public function __construct(private KnowledgeRetriever $retriever) {}

    /**
     * @param  list<string>  $questions
     * @return array<string, string> question => drafted answer
     */
    public function handle(Business $business, array $questions): array
    {
        $drafts = [];

        foreach ($questions as $question) {
            $context = $this->retriever->context($question, $business->id, minSimilarity: self::DRAFT_SIMILARITY);

            if ($context === '') {
                continue;
            }

            try {
                $response = new FaqDrafter()->prompt(
                    "Pregunta del cliente: {$question}\n\nFragmentos del conocimiento del negocio:\n{$context}",
                );
            } catch (Throwable $e) {
                report($e);

                continue;
            }

            $answer = trim((string) ($response['answer'] ?? ''));

            if ($answer !== '') {
                $drafts[$question] = mb_substr($answer, 0, 2000);
            }
        }

        return $drafts;
    }
}
