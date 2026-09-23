<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Billing;

use App\Classes\Main\Client;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Rules\AttributeValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

/** The transfer receipt the client uploads; it waits as pending until the admin verifies it. */
class PaymentReceiptForm extends BaseForm
{
    public ?UploadedFile $receipt = null;

    public string $reference = '';

    public function setup(): void
    {
        $this->reset('receipt', 'reference');
    }

    public function save(): NotificationDto
    {
        $validated = $this->validateServiceData();
        $billing = $this->client()->billing;

        if ($billing === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        // One receipt under review at a time: a second one would pay twice.
        if ($billing->pending !== null) {
            return new NotificationDto(__('billing.receipt.already_pending'), NotificationType::Warning);
        }

        return $this->tryAction(function () use ($billing, $validated): NotificationDto {
            $billing->submitReceipt($validated['receipt'], filled($validated['reference']) ? $validated['reference'] : null);
            $this->setup();

            return new NotificationDto(__('billing.receipt.sent'), NotificationType::Success);
        }, __('notifications.not_updated'));
    }

    /** Rebuilt per request: a Livewire form cannot hold it in a constructor. */
    private function client(): Client
    {
        return Client::for(Auth::user());
    }

    protected function transformServiceData(): array
    {
        return ['receipt' => $this->receipt, 'reference' => trim($this->reference)];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'reference' => ['nullable', 'string', 'max:60', AttributeValidator::xssFree()],
        ];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return [
            'receipt' => __('billing.receipt.file'),
            'reference' => __('billing.receipt.reference'),
        ];
    }
}
