<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Admin;

use App\Actions\Support\AnswerSupportTicket;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Enums\SupportDelivery;
use App\Livewire\Forms\BaseForm;
use App\Models\SupportTicket;
use App\Models\User;
use App\Rules\AttributeValidator;

/**
 * What the team writes to the business. It keeps how the last one left so the
 * screen can say it: a reply that reached nobody is not an answered report.
 */
class SupportReplyForm extends BaseForm
{
    public string $body = '';

    /** How the reply just sent left, as a SupportDelivery value; null before any. */
    public ?string $delivery = null;

    public function setup(): void
    {
        $this->reset();
    }

    public function save(SupportTicket $ticket, User $author): NotificationDto
    {
        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($ticket, $author, $validated): NotificationDto {
            $delivery = app(AnswerSupportTicket::class)->reply($ticket, $validated['body'], $author);
            $this->delivery = $delivery->value;

            // The text stays when it did not leave: it was saved, but the person
            // may want to resend it by another road, and retyping it is the cost.
            if ($delivery->reached()) {
                $this->body = '';
            }

            return new NotificationDto(
                $delivery->outcome(),
                $delivery->reached() ? NotificationType::Success : NotificationType::Warning,
            );
        }, __('support.admin.reply_failed'));
    }

    protected function transformServiceData(): array
    {
        return ['body' => trim($this->body)];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            // Under the WhatsApp ceiling: a longer text would be cut on the way.
            'body' => ['required', 'string', 'min:2', 'max:900', AttributeValidator::xssFree()],
        ];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return ['body' => __('support.admin.reply')];
    }

    /** Whether the last reply left: the screen shows the outcome while this is false. */
    public function failedToLeave(): bool
    {
        return $this->delivery !== null && ! SupportDelivery::from($this->delivery)->reached();
    }
}
