<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\PlatformContact;
use Illuminate\Console\Command;

/**
 * A new layer must adopt the data born before it: every thread that predates
 * the customer records gets its person here, idempotently. Console runs
 * tenantless, so the sweep sees every business by design.
 */
class AdoptOrphanCustomers extends Command
{
    protected $signature = 'atendia:adopt-customers';

    protected $description = 'Give every pre-existing conversation its customer and platform contact';

    public function handle(): int
    {
        $orphans = Conversation::query()->whereNull('customer_id')->get();

        foreach ($orphans as $conversation) {
            $customer = Customer::query()->firstOrCreate(
                ['business_id' => $conversation->business_id, 'phone' => $conversation->contact_phone],
                [
                    'profile_name' => $conversation->contact_name,
                    'language' => $conversation->language,
                    'first_seen_at' => $conversation->created_at,
                    'last_activity_at' => $conversation->last_message_at,
                ],
            );

            $contact = PlatformContact::query()->firstOrCreate(
                ['phone' => $conversation->contact_phone],
                ['first_seen_at' => $conversation->created_at, 'last_activity_at' => $conversation->last_message_at],
            );

            if ($customer->platform_contact_id === null) {
                $customer->platformContact()->associate($contact);
            }

            $conversation->update(['customer_id' => $customer->id]);

            $customer->conversations_count = $customer->conversations()->count();
            $customer->save();

            // Tenantless console: counting across businesses here is legit,
            // exactly like the seeders.
            $contact->businesses_count = $contact->customers()->count();
            $contact->conversations_count = $contact->customers()->sum('conversations_count');
            $contact->save();

            $this->info("Adopted {$conversation->contact_phone} for business {$conversation->business_id}");
        }

        $this->info("Done: {$orphans->count()} thread(s) adopted.");

        return self::SUCCESS;
    }
}
