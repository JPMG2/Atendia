<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Services\EvolutionApi;
use App\Services\ProactiveSends;
use App\Services\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * The birthday charm: every customer whose captured birthday is today — on
 * the business's calendar, at its local morning — and who said yes to its
 * messages gets one greeting over the business's own WhatsApp.
 */
class SendBirthdayGreetings extends Command
{
    protected $signature = 'atendia:birthday-greetings';

    protected $description = 'Greet every customer whose birthday is today, per business instance';

    public function handle(EvolutionApi $evolution): int
    {
        $time = (string) config('atendia.schedule.birthday_greetings');

        $businesses = Business::query()
            ->whereNotNull('whatsapp_instance')
            ->whereNotNull('whatsapp_connected_at')
            ->get()
            ->filter(fn (Business $business): bool => $business->isDueAt($time) && ! $business->isSilenced());

        foreach ($businesses as $business) {
            app(Tenant::class)->speakingAs($business, fn () => $this->greet($evolution, $business));
        }

        return self::SUCCESS;
    }

    /**
     * Today on the business's own calendar, and only customers who said yes
     * to its messages: an unasked greeting is what gets a number reported.
     */
    private function greet(EvolutionApi $evolution, Business $business): void
    {
        $today = now($business->localTimezone());

        $celebrants = Customer::query()
            ->whereNotNull('birthday')
            ->whereNotNull('marketing_opt_in_at')
            ->whereNull('blocked_at')
            ->whereMonth('birthday', $today->month)
            ->whereDay('birthday', $today->day)
            ->get();

        $gate = ProactiveSends::for($business);

        foreach ($celebrants as $customer) {
            // A held greeting is skipped, not postponed: a birthday greeting a day late reads wrong.
            if ($gate->refusal($customer->phone, $customer) !== null) {
                continue;
            }

            // Once a year per customer: a rerun or a second tick never greets twice.
            if (! Cache::add("birthday:{$customer->id}:{$today->year}", true, now()->addDays(400))) {
                continue;
            }

            // One failed send never cuts off the celebrants after it.
            rescue(function () use ($evolution, $business, $customer, $gate): void {
                $text = ProactiveSends::wording('client.customers.birthday_greeting', $customer->id, [
                    'name' => ($who = $customer->displayName()) !== null ? ' '.$who : '',
                    'business' => $business->name,
                ]);

                $gate->pace();
                $evolution->sendText((string) $business->whatsapp_instance, $customer->phone, $text);
                $gate->sent($customer->phone);

                // In the thread too: a "¡gracias!" back needs the assistant to know why.
                Conversation::query()->where('contact_phone', $customer->phone)->first()?->messages()->create([
                    'direction' => MessageDirection::Out,
                    'author' => MessageAuthor::Assistant,
                    'body' => $text,
                ]);

                $this->info("Birthday greeting sent for {$business->name} to {$customer->phone}");
            });
        }
    }
}
