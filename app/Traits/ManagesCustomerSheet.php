<?php

declare(strict_types=1);

namespace App\Traits;

use App\Actions\Business\RequestCustomerOptIn;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\Client\CustomerForm;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

/**
 * The customer sheet's shared brain: Conversations and the directory open
 * the SAME fiche, so its state and actions live once. The host only says
 * which record the sheet is looking at.
 */
trait ManagesCustomerSheet
{
    public CustomerForm $customerForm;

    public bool $showCustomer = false;

    /** The record the sheet edits right now, resolved by the host screen. */
    abstract protected function sheetCustomer(): ?Customer;

    #[Computed]
    public function customer(): ?Customer
    {
        return $this->sheetCustomer();
    }

    /** The sibling record sharing this email, if any: the merge suggestion. */
    #[Computed]
    public function customerDuplicate(): ?Customer
    {
        return $this->customer?->duplicateOf();
    }

    public function openCustomer(): void
    {
        if ($this->customer === null) {
            return;
        }

        $this->customerForm->setup($this->customer);
        $this->showCustomer = true;
    }

    public function closeCustomer(): void
    {
        $this->showCustomer = false;
    }

    public function saveCustomer(): void
    {
        $this->dispatchNotification($this->customerForm->save());
    }

    public function requestOptIn(): void
    {
        $customer = $this->customer;
        $business = Auth::user()?->business;

        if ($customer === null || $business === null) {
            return;
        }

        $sent = app(RequestCustomerOptIn::class)->handle($business, $customer);

        $this->dispatchNotification($sent
            ? new NotificationDto(__('client.customers.opt_in_sent'), NotificationType::Success)
            : new NotificationDto(__('client.customers.opt_in_unavailable'), NotificationType::Error));
    }

    /** The duplicate's threads move here and its row dies; the dialog confirmed. */
    public function mergeCustomer(): void
    {
        $customer = $this->customer;
        $duplicate = $this->customerDuplicate;

        if ($customer === null || $duplicate === null) {
            return;
        }

        $customer->mergeFrom($duplicate);

        $this->dispatchNotification(new NotificationDto(__('client.customers.merged'), NotificationType::Success));
    }
}
