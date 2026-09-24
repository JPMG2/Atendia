<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Business\SendHumanReply;
use App\Enums\SuggestionStatus;
use App\Models\Business;
use App\Models\ConversationQuestion;
use App\Models\KnowledgeSuggestion;
use App\Services\Tenant;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends a just-taught answer to the customers who asked it and were left
 * without one, through the business's own WhatsApp and into each thread.
 * Unique per suggestion: a double click must not message anyone twice.
 */
class NotifyUnansweredCustomers implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public int $businessId, public int $suggestionId) {}

    public function uniqueId(): string
    {
        return (string) $this->suggestionId;
    }

    public function handle(SendHumanReply $reply): void
    {
        app(Tenant::class)->for($this->businessId, function () use ($reply): void {
            $business = Business::query()->find($this->businessId);
            $suggestion = KnowledgeSuggestion::query()->with('document')->find($this->suggestionId);

            if ($business === null || $suggestion?->status !== SuggestionStatus::Taught || $suggestion->document === null) {
                return;
            }

            $text = __('client.assistant.notify_customer_message', [
                'question' => $suggestion->question,
                'answer' => $suggestion->document->faqAnswer(),
            ]);

            $suggestion->notifiableQuestions()->with('conversation')->get()
                ->groupBy('conversation_id')
                ->each(function ($askings) use ($business, $reply, $text): void {
                    // Stamped only once it really left: a disconnected number retries later.
                    if ($reply->handle($business, $askings->first()->conversation, $text) !== null) {
                        ConversationQuestion::query()->whereKey($askings->pluck('id')->all())->update(['customer_notified_at' => now()]);
                    }
                });
        });
    }
}
