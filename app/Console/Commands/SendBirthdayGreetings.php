<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\EvolutionApi;
use Illuminate\Console\Command;

/**
 * The birthday charm: every customer whose captured birthday is today gets
 * one greeting over the business's own WhatsApp. Console runs tenantless,
 * so the sweep sees every business by design.
 */
class SendBirthdayGreetings extends Command
{
    protected $signature = 'atendia:birthday-greetings';

    protected $description = 'Greet every customer whose birthday is today, per business instance';

    public function handle(EvolutionApi $evolution): int
    {
        $celebrants = Customer::query()
            ->with('business')
            ->whereNotNull('birthday')
            ->whereMonth('birthday', now()->month)
            ->whereDay('birthday', now()->day)
            ->get();

        foreach ($celebrants as $customer) {
            $business = $customer->business;

            if ($business === null || ! $business->isConnected() || $business->whatsapp_instance === null) {
                continue;
            }

            $evolution->sendText(
                $business->whatsapp_instance,
                $customer->phone,
                __('client.customers.birthday_greeting', [
                    'name' => ($who = $customer->displayName()) !== null ? ' '.$who : '',
                    'business' => $business->name,
                ]),
            );

            $this->info("Birthday greeting sent for {$business->name} to {$customer->phone}");
        }

        return self::SUCCESS;
    }
}
