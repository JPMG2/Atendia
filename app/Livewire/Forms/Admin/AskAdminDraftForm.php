<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Admin;

use App\Ai\Tools\AdminEmailBusiness;
use App\Livewire\Forms\BaseForm;

/** The e-mail the assistant drafted, as she leaves it before sending: the same bounds the tool keeps. */
class AskAdminDraftForm extends BaseForm
{
    public string $subject = '';

    public string $message = '';

    /** @return array{subject: string, message: string} */
    public function validatedDraft(): array
    {
        return $this->validateServiceData();
    }

    protected function transformServiceData(): array
    {
        return ['subject' => trim($this->subject), 'message' => trim($this->message)];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return self::fieldRules();
    }

    /** @return array<string, list<string>> */
    public static function fieldRules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:'.AdminEmailBusiness::SUBJECT_MAX],
            'message' => ['required', 'string', 'max:'.AdminEmailBusiness::MESSAGE_MAX],
        ];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return self::fieldNames();
    }

    /** @return array<string, string> */
    public static function fieldNames(): array
    {
        return ['subject' => __('ask_admin.draft.subject'), 'message' => __('ask_admin.draft.message')];
    }
}
