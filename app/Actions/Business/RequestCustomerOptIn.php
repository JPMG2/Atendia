<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Models\Business;
use App\Models\Customer;
use App\Services\EvolutionApi;

/**
 * Sends the marketing consent request over the business's own WhatsApp and
 * stamps when it was asked. The assistant seals the customer's yes; asking
 * is the only thing this action does.
 */
class RequestCustomerOptIn
{
    public function __construct(private EvolutionApi $evolution) {}

    public function handle(Business $business, Customer $customer): bool
    {
        if (! $business->isConnected() || $business->whatsapp_instance === null) {
            return false;
        }

        $this->evolution->sendText(
            $business->whatsapp_instance,
            $customer->phone,
            __('client.customers.opt_in_message', [
                // Prefixed space so a nameless customer reads "Hola 👋", not "Hola  👋".
                'name' => ($who = $customer->displayName()) !== null ? ' '.$who : '',
                'business' => $business->name,
            ]),
        );

        $customer->forceFill(['marketing_opt_in_requested_at' => now()])->save();

        return true;
    }
}
