<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Admin;

use App\Actions\Support\AddSupportNote;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\SupportTicket;
use App\Models\User;
use App\Rules\AttributeValidator;

/** What the team tells itself about a report: the business never reads it. */
class SupportNoteForm extends BaseForm
{
    public string $body = '';

    public function setup(): void
    {
        $this->reset();
    }

    public function save(SupportTicket $ticket, User $author): NotificationDto
    {
        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($ticket, $author, $validated): NotificationDto {
            app(AddSupportNote::class)->handle($ticket, $validated['body'], $author);
            $this->body = '';

            return new NotificationDto(__('support.admin.note_saved'), NotificationType::Success);
        }, __('support.admin.note_failed'));
    }

    protected function transformServiceData(): array
    {
        return ['body' => trim($this->body)];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return ['body' => ['required', 'string', 'min:2', 'max:2000', AttributeValidator::xssFree()]];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return ['body' => __('support.admin.note')];
    }
}
