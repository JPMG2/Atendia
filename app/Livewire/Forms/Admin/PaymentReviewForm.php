<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Admin;

use App\Actions\Billing\ApprovePayment;
use App\Actions\Billing\RejectPayment;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Livewire\Forms\BaseForm;
use App\Models\Payment;
use App\Rules\AttributeValidator;
use Illuminate\Support\Facades\Auth;

/** The admin's verdict on a receipt: credit it, or reject it with a reason the client reads. */
class PaymentReviewForm extends BaseForm
{
    public ?int $rejectingId = null;

    public string $reason = '';

    public function setup(): void
    {
        $this->reset('rejectingId', 'reason');
    }

    public function approve(int $paymentId): NotificationDto
    {
        $payment = $this->pending($paymentId);

        if ($payment === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        return $this->tryAction(function () use ($payment): NotificationDto {
            app(ApprovePayment::class)->handle($payment, Auth::user());

            return new NotificationDto(__('billing.admin.approved'), NotificationType::Success);
        }, __('notifications.not_updated'));
    }

    public function reject(): NotificationDto
    {
        $validated = $this->validateServiceData();
        $payment = $this->rejectingId === null ? null : $this->pending($this->rejectingId);

        if ($payment === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        return $this->tryAction(function () use ($payment, $validated): NotificationDto {
            app(RejectPayment::class)->handle($payment, Auth::user(), $validated['reason']);
            $this->setup();

            return new NotificationDto(__('billing.admin.rejected'), NotificationType::Success);
        }, __('notifications.not_updated'));
    }

    /** Only a receipt still waiting can be reviewed: a double click never credits twice. */
    private function pending(int $paymentId): ?Payment
    {
        return Payment::query()->whereKey($paymentId)->where('status', PaymentStatus::Pending)->first();
    }

    protected function transformServiceData(): array
    {
        return ['reason' => trim($this->reason)];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:200', AttributeValidator::xssFree()]];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return ['reason' => __('billing.admin.reason')];
    }
}
