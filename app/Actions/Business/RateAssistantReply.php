<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Models\AssistantRating;
use App\Models\Business;
use App\Models\ConversationMessage;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Marks one reply of the assistant as good or wrong, from the thread itself.
 *
 * Marking twice corrects the mark instead of stacking another: whoever reads
 * a thread changes their mind, and a tally that grows with the clicks would
 * say more about the mouse than about the assistant.
 */
class RateAssistantReply
{
    public function handle(Business $business, int $messageId, bool $isGood): AssistantRating
    {
        /** @var ConversationMessage $message */
        $message = ConversationMessage::query()
            ->where('business_id', $business->id)
            ->findOrFail($messageId);

        // Only what the assistant WROTE can be judged as an answer: a human
        // reply is the team's own work and a customer's is not an answer.
        if ($message->direction !== MessageDirection::Out || $message->author !== MessageAuthor::Assistant) {
            throw new RuntimeException('Only an assistant reply can be rated.');
        }

        return AssistantRating::updateOrCreate(
            ['conversation_message_id' => $message->id, 'user_id' => Auth::id()],
            ['business_id' => $business->id, 'is_good' => $isGood],
        );
    }
}
