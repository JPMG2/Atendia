<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Client;

use App\Actions\Support\CreateSupportTicket;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Enums\SupportTicketKind;
use App\Livewire\Forms\BaseForm;
use App\Models\SupportTicket;
use App\Rules\AttributeValidator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * One required field and nothing else. The kind and the screen have defaults
 * and are asked AFTER the text, because a form that demands a category before
 * letting someone write is the first reason a report never gets sent.
 */
class SupportTicketForm extends BaseForm
{
    /** What a person can describe without the box turning into an essay. */
    private const int MAX_BODY = 2000;

    private const int MAX_IMAGE_KB = 4096;

    public string $body = '';

    public string $kind = 'problem';

    public ?string $screen = null;

    /** True when Help handed her over: the deflection that did not work. */
    public bool $afterHelp = false;

    /**
     * Untyped on purpose: Livewire holds a TemporaryUploadedFile here, and a
     * type would reject the plain UploadedFile a test hands over.
     *
     * @var TemporaryUploadedFile|null
     */
    public $attachment = null;

    public ?SupportTicket $ticket = null;

    /**
     * @param  array<string, mixed>  $context  Captured by the widget, never asked.
     */
    public function save(array $context): NotificationDto
    {
        if (Auth::user()?->business === null) {
            return new NotificationDto(__('support.no_business'), NotificationType::Error);
        }

        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($validated, $context): NotificationDto {
            $this->ticket = app(CreateSupportTicket::class)->handle(
                user: Auth::user(),
                body: $validated['body'],
                kind: SupportTicketKind::from($validated['kind']),
                screen: $validated['screen'],
                context: $context,
                attachment: $validated['attachment'],
                afterHelp: $this->afterHelp,
            );

            return new NotificationDto(
                __('support.sent', ['code' => $this->ticket->code]),
                NotificationType::Success,
            );
        }, __('support.failed'));
    }

    public function reset(...$properties): void
    {
        parent::reset(...$properties);

        $this->kind = 'problem';
    }

    protected function transformServiceData(): array
    {
        return [
            'body' => trim($this->body),
            'kind' => $this->kind,
            'screen' => $this->screen !== null && trim($this->screen) !== '' ? trim($this->screen) : null,
            'attachment' => $this->attachment,
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            'body' => ['required', 'string', 'min:10', 'max:'.self::MAX_BODY],
            'kind' => ['required', Rule::enum(SupportTicketKind::class)],
            'screen' => ['nullable', 'string', 'max:80'],
            // Nothing is captured on its own: this is only what she chose to
            // attach, and it goes through the same gate as every other upload.
            'attachment' => AttributeValidator::imageUpload('support', false, self::MAX_IMAGE_KB),
        ];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return [
            'body' => __('support.fields.body'),
            'kind' => __('support.fields.kind'),
            'screen' => __('support.fields.screen'),
            'attachment' => __('support.fields.attachment'),
        ];
    }
}
