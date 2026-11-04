<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\ConversationStatus;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Enums\SubscriptionStatus;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The account Google Play's reviewer signs in with. Its business is flagged
 * `is_demo` on purpose: that flag already keeps a business out of every
 * platform list, figure and job, and still lets it see itself, so the review
 * account never reads as a paying client. Idempotent; the password is minted
 * once, on creation, and printed once.
 */
class ReviewerAccountSeeder extends Seeder
{
    public const string EMAIL = 'revision@aliadito.com';

    public function run(): void
    {
        $user = User::query()->where('email', self::EMAIL)->first();
        $password = null;

        if ($user === null) {
            $password = Str::password(16, symbols: false);
            $user = new User(['name' => 'Revisión de Google Play', 'email' => self::EMAIL, 'password' => Hash::make($password)]);
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $user->syncRoles(['client']);

        $business = Business::query()->where('is_demo', true)->updateOrCreate(
            ['billing_email' => self::EMAIL],
            [
                'name' => 'Estudio Brisa',
                'city' => 'Caracas',
                'country_id' => Country::query()->where('iso2', 'VE')->value('id') ?? Country::query()->value('id'),
                'currency_id' => Currency::query()->where('code', 'USD')->value('id'),
                'is_active' => true,
                'is_demo' => true,
            ],
        );

        $user->forceFill(['business_id' => $business->id])->save();

        app(Tenant::class)->for($business->id, function () use ($business): void {
            $this->activatePlan($business);
            $this->sampleData($business);
        });

        $this->command?->info('Cuenta de revisión: '.self::EMAIL);
        $this->command?->info($password === null ? 'Ya existía: la contraseña no se tocó.' : 'Contraseña (se muestra solo ahora): '.$password);
    }

    /** Paid for years ahead: a review that lasts weeks must never meet a lapsed plan. */
    private function activatePlan(Business $business): void
    {
        $business->subscription->forceFill([
            'plan' => 'negocio',
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'yearly',
            'trial_ends_at' => null,
            'current_period_ends_at' => now()->addYears(5),
        ])->save();
    }

    private function sampleData(Business $business): void
    {
        $typeId = ServiceType::query()->where('code', 'consulta')->value('id') ?? ServiceType::query()->value('id');

        $services = collect([
            ['Corte y peinado', 12, 45],
            ['Color completo', 35, 120],
            ['Tratamiento de hidratación', 20, 60],
        ])->map(fn (array $row): Service => Service::query()->updateOrCreate(
            ['business_id' => $business->id, 'name' => $row[0]],
            ['service_type_id' => $typeId, 'price_type' => 'fixed', 'price' => $row[1], 'duration_minutes' => $row[2], 'is_bookable' => true, 'is_active' => true],
        ));

        foreach ([['Shampoo reparador', 9], ['Mascarilla nutritiva', 14], ['Aceite capilar', 11]] as [$name, $price]) {
            Product::query()->updateOrCreate(
                ['business_id' => $business->id, 'name' => $name],
                ['price' => $price, 'in_stock' => true, 'is_active' => true],
            );
        }

        $people = [
            ['584121110001', 'Carla Méndez', '¿Cuánto cuesta el color completo y tienen cupo esta semana?', 'El color completo cuesta $35 y dura unas 2 horas. Tengo cupo el jueves a las 10:00 y el viernes a las 15:00. ¿Cuál te sirve?'],
            ['584121110002', 'Luis Rondón', '¿A qué hora abren los sábados?', 'Los sábados atendemos de 9:00 a 17:00. ¿Quieres que te reserve un horario?'],
            ['584121110003', 'Andrea Silva', 'Buenas, ¿tienen shampoo reparador?', 'Sí, el shampoo reparador cuesta $9 y hay en stock. Puedes retirarlo cuando vengas.'],
        ];

        foreach ($people as $index => [$phone, $name, $question, $answer]) {
            $customer = Customer::query()->updateOrCreate(
                ['business_id' => $business->id, 'phone' => $phone],
                ['profile_name' => $name, 'name' => $name, 'first_seen_at' => now()->subDays(3 - $index), 'last_activity_at' => now()->subHours($index + 1)],
            );

            $conversation = Conversation::query()->updateOrCreate(
                ['business_id' => $business->id, 'contact_phone' => $phone],
                ['customer_id' => $customer->id, 'contact_name' => $name, 'status' => ConversationStatus::Open, 'last_message_at' => now()->subHours($index + 1)],
            );

            if (! $conversation->messages()->exists()) {
                foreach ([[MessageDirection::In, null, $question], [MessageDirection::Out, MessageAuthor::Assistant, $answer]] as [$direction, $author, $body]) {
                    ConversationMessage::query()->create([
                        'business_id' => $business->id,
                        'conversation_id' => $conversation->id,
                        'direction' => $direction,
                        'author' => $author,
                        'kind' => MessageKind::Message,
                        'body' => $body,
                    ]);
                }
            }

            if ($index < 2 && ! Appointment::query()->where('customer_id', $customer->id)->exists()) {
                $start = now()->addDays($index + 1)->setTime(10 + $index * 5, 0);
                $service = $services[$index];

                $appointment = new Appointment([
                    'customer_id' => $customer->id,
                    'service_id' => $service->id,
                    'conversation_id' => $conversation->id,
                    'starts_at' => $start,
                    'ends_at' => $start->copy()->addMinutes($service->duration_minutes),
                    'status' => AppointmentStatus::Confirmed,
                    'source' => AppointmentSource::Assistant,
                ]);
                $appointment->forceFill(['business_id' => $business->id])->save();
            }
        }
    }
}
