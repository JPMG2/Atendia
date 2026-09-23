<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Services\EvolutionApi;

/**
 * Sends the marketing consent request over the business's own WhatsApp,
 * stamps when it was asked and writes it into the customer's thread. The
 * thread is the assistant's memory: without the question there, a "sí"
 * was read as the answer to whatever came before (2026-09-23).
 */
class RequestCustomerOptIn
{
    public function __construct(private EvolutionApi $evolution) {}

    public function handle(Business $business, Customer $customer): bool
    {
        if (! $business->isConnected() || $business->whatsapp_instance === null) {
            return false;
        }

        $text = __('client.customers.opt_in_message', [
            // Prefixed space so a nameless customer reads "Hola 👋", not "Hola  👋".
            'name' => ($who = $customer->displayName()) !== null ? ' '.$who : '',
            'business' => $business->name,
        ]);

        $this->evolution->sendText($business->whatsapp_instance, $customer->phone, $text);

        $customer->forceFill(['marketing_opt_in_requested_at' => now()])->save();

        // Same key the inbound worker threads on, so the reply lands right here.
        $conversation = Conversation::query()->firstOrCreate(
            ['contact_phone' => $customer->phone],
            ['contact_name' => $customer->displayName(), 'customer_id' => $customer->id],
        );

        $conversation->messages()->create([
            'direction' => MessageDirection::Out,
            'author' => MessageAuthor::Human,
            'body' => $text,
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();

        return true;
    }
}
