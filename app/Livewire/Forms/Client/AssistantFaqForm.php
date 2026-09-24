<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Client;

use App\Actions\Business\SaveAssistantFaq;
use App\Dto\DtoCast;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSuggestion;
use App\Rules\AttributeValidator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;

/**
 * The teach-the-assistant sheet: one question, one answer, straight into
 * the knowledge base. Editing re-indexes only when the text really moved.
 */
class AssistantFaqForm extends BaseForm
{
    #[Locked]
    public ?int $editingId = null;

    /** The thread a new answer is being taught from; edits never move it. */
    #[Locked]
    public ?int $conversationId = null;

    /** The queued suggestion this answer settles, if it came from one. */
    #[Locked]
    public ?int $suggestionId = null;

    public string $question = '';

    public string $answer = '';

    public function setup(?KnowledgeDocument $faq = null): void
    {
        $this->editingId = $faq?->id;
        $this->conversationId = null;
        $this->suggestionId = null;
        $this->question = (string) $faq?->title;
        $this->answer = (string) $faq?->faqAnswer();

        $this->resetErrorBag();
    }

    /** The sheet opens on the rewritten question, with the team's answer as the draft. */
    public function setupFromSuggestion(KnowledgeSuggestion $suggestion): void
    {
        $this->setup();
        $this->suggestionId = $suggestion->id;
        $this->conversationId = $suggestion->latestQuestion?->conversation_id;
        $this->question = mb_substr($suggestion->question, 0, 200);
        $this->answer = (string) $suggestion->teamAnswer?->answer;
    }

    public function save(): NotificationDto
    {
        $business = Auth::user()?->business;

        if ($business === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($business, $validated): NotificationDto {

            $faq = app(SaveAssistantFaq::class)->handle($business, $validated, $this->editingId, $this->conversationId, $this->suggestionId);

            return $this->notificationService()->notificationFor($faq, $this->editingId === null ? 'created' : 'updated');

        }, __('notifications.not_created'));
    }

    protected function transformServiceData(): array
    {
        return [
            'question' => DtoCast::squish($this->question) ?? '',
            'answer' => trim($this->answer),
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            'question' => [...AttributeValidator::stringValid(true, '5'), 'max:200'],
            'answer' => ['required', 'string', 'min:2', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getValidationAttributes(): array
    {
        return [
            'question' => __('client.assistant.field_question'),
            'answer' => __('client.assistant.field_answer'),
        ];
    }
}
