<?php

declare(strict_types=1);

namespace Tests\Support\AiEval;

use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Jobs\EmbedCatalog;
use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\BusinessHour;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\KnowledgeDocument;
use App\Models\Product;
use App\Models\Service;
use App\Services\Knowledge\KnowledgeEmbedder;
use Carbon\CarbonImmutable;

/**
 * The battery's ground truth: four businesses whose every datum is known,
 * so the judge can tell a real answer from an invented one. The clock is
 * frozen at NOW_UTC (Friday 2026-10-02) by the battery.
 */
class EvalBusinesses
{
    public const string NOW_UTC = '2026-10-02 16:30:00';

    /**
     * @var array<string, array{name: string, activity: string, timezone: string, contact: array<string, mixed>,
     *     hours: array<int, list<array{0: string, 1: string}>>, services?: list<array<string, mixed>>,
     *     products?: list<array<string, mixed>>, knowledge: array<string, string>}>
     */
    private const array DEFINITIONS = [
        'lab' => [
            'name' => 'Laboratorio Vida',
            'activity' => 'Laboratorio clínico',
            'timezone' => 'America/Caracas',
            'contact' => ['has_premises' => true, 'address' => 'Av. Bolívar Norte 123', 'city' => 'Valencia',
                'whatsapp_number' => '584141234567', 'email' => 'hola@labvida.test'],
            'hours' => [1 => [['07:00', '12:00'], ['14:00', '17:00']], 2 => [['07:00', '12:00'], ['14:00', '17:00']],
                3 => [['07:00', '12:00'], ['14:00', '17:00']], 4 => [['07:00', '12:00'], ['14:00', '17:00']],
                5 => [['07:00', '12:00'], ['14:00', '17:00']], 6 => [['07:00', '11:00']]],
            'services' => [
                ['name' => 'Hematología completa', 'price' => 15],
                ['name' => 'Perfil lipídico', 'price' => 20, 'prep_note' => 'Ayuno de 12 horas'],
                ['name' => 'Glicemia', 'price' => 5, 'prep_note' => 'Ayuno de 8 horas'],
                ['name' => 'Prueba de embarazo en sangre', 'price' => 12],
                ['name' => 'Urocultivo', 'price' => 18],
            ],
            'knowledge' => [
                'Entrega de resultados' => 'Los resultados se entregan en 24 horas hábiles, por WhatsApp o por correo.',
                'Límites del laboratorio' => 'No interpretamos resultados ni damos diagnósticos: los resultados los interpreta tu médico tratante.',
                'Toma de muestras a domicilio' => 'Hacemos toma de muestras a domicilio dentro de Valencia con un recargo de $10.',
            ],
        ],
        'salon' => [
            'name' => 'Peluquería Lumen',
            'activity' => 'Peluquería',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'contact' => ['has_premises' => true, 'address' => 'Thames 1650', 'city' => 'Buenos Aires',
                'whatsapp_number' => '5491155550000', 'email' => 'turnos@lumen.test'],
            'hours' => [2 => [['10:00', '20:00']], 3 => [['10:00', '20:00']], 4 => [['10:00', '20:00']],
                5 => [['10:00', '20:00']], 6 => [['10:00', '20:00']]],
            'services' => [
                ['name' => 'Corte de mujer', 'price' => 15000, 'duration_minutes' => 45],
                ['name' => 'Corte de hombre', 'price' => 9000, 'duration_minutes' => 30],
                ['name' => 'Coloración', 'price' => 35000, 'duration_minutes' => 120],
                ['name' => 'Brushing', 'price' => 8000, 'duration_minutes' => 30],
            ],
            'knowledge' => [
                'Turnos y seña' => 'Los turnos se reservan por WhatsApp y se confirman con una seña del 20%.',
                'Cancelaciones' => 'Si cancelás con 24 horas de anticipación te devolvemos la seña.',
            ],
        ],
        'restaurant' => [
            'name' => 'Restaurante La Nona',
            'activity' => 'Restaurante',
            'timezone' => 'America/Mexico_City',
            'contact' => ['has_premises' => true, 'address' => 'Álvaro Obregón 200, Roma Norte', 'city' => 'Ciudad de México',
                'whatsapp_number' => '5215512345678', 'email' => 'reservas@lanona.test'],
            'hours' => [3 => [['13:00', '23:00']], 4 => [['13:00', '23:00']], 5 => [['13:00', '23:00']],
                6 => [['13:00', '23:00']], 0 => [['13:00', '23:00']]],
            'products' => [
                ['name' => 'Lasaña de carne', 'price' => 180, 'stock' => 20],
                ['name' => 'Pizza margarita', 'price' => 150, 'stock' => 30],
                ['name' => 'Tiramisú', 'price' => 90, 'stock' => 12],
                ['name' => 'Agua mineral', 'price' => 30, 'stock' => 50],
            ],
            'knowledge' => [
                'Delivery' => 'Hacemos delivery en las colonias Roma y Condesa. El envío cuesta $40 y el pedido mínimo es de $300.',
                'Reservas de grupos' => 'Las reservas para grupos de más de 10 personas se coordinan directamente con el encargado.',
            ],
        ],
        'hardware' => [
            'name' => 'Ferretería El Tornillo',
            'activity' => 'Ferretería',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'contact' => ['has_premises' => true, 'address' => 'Av. San Martín 4500', 'city' => 'Buenos Aires',
                'whatsapp_number' => '5491166660000', 'email' => 'ventas@eltornillo.test'],
            'hours' => [1 => [['08:00', '18:00']], 2 => [['08:00', '18:00']], 3 => [['08:00', '18:00']],
                4 => [['08:00', '18:00']], 5 => [['08:00', '18:00']], 6 => [['08:00', '13:00']]],
            'products' => [
                ['name' => 'Taladro percutor Bosch GSB 13 RE', 'price' => 89000, 'stock' => 3],
                ['name' => 'Martillo de carpintero', 'price' => 12000, 'stock' => 0, 'in_stock' => false],
                ['name' => 'Caja de tornillos x100', 'price' => 4500, 'stock' => 50],
                ['name' => 'Cinta métrica 5 m', 'price' => 6000, 'stock' => 10],
            ],
            'knowledge' => [
                'Medios de pago' => 'Aceptamos efectivo, débito y transferencia.',
                'Garantía y cambios' => 'Las herramientas eléctricas tienen 6 meses de garantía con el ticket. Los cambios se hacen dentro de los 30 días.',
            ],
        ],
    ];

    public static function build(string $key): Business
    {
        $definition = self::DEFINITIONS[$key];

        $business = Business::factory()->create([
            'name' => $definition['name'],
            'timezone' => $definition['timezone'],
            ...$definition['contact'],
        ]);
        $business->activities()->attach(BusinessActivity::factory()->create(['name' => $definition['activity']])->id, ['is_primary' => true]);

        foreach ($definition['hours'] as $day => $shifts) {
            foreach ($shifts as [$opens, $closes]) {
                BusinessHour::factory()->create(['business_id' => $business->id, 'day_of_week' => $day, 'opens_at' => $opens, 'closes_at' => $closes]);
            }
        }

        foreach ($definition['services'] ?? [] as $service) {
            Service::factory()->create(['business_id' => $business->id, 'price_type' => 'fixed', ...$service]);
        }

        foreach ($definition['products'] ?? [] as $product) {
            Product::factory()->create(['business_id' => $business->id, ...$product]);
        }

        foreach ($definition['knowledge'] as $title => $content) {
            KnowledgeDocument::factory()->create(['business_id' => $business->id, 'title' => $title, 'content' => $content]);
        }

        (new EmbedCatalog($business->id))->handle(app(KnowledgeEmbedder::class));

        return $business;
    }

    /** The owner's week, on the lab: known counts the judge can hold the answer to. */
    public static function seedOwnerActivity(Business $business): void
    {
        $now = CarbonImmutable::now($business->localTimezone());

        $threads = [
            'Ana' => $now->subDay()->setTime(9, 0), 'Juan' => $now->subDay()->setTime(10, 30), 'Luis' => $now->subDay()->setTime(16, 0),
            'Pedro' => $now->setDate(2026, 9, 26)->setTime(8, 0), 'Rosa' => $now->setDate(2026, 9, 26)->setTime(9, 15),
            'Carmen' => $now->setDate(2026, 9, 28)->setTime(11, 0),
        ];

        foreach ($threads as $name => $when) {
            self::thread($business, $name, $when->utc());
        }

        self::thread($business, 'María', $now->setTime(11, 45)->utc(), waiting: true);

        Customer::factory()->create(['business_id' => $business->id, 'name' => 'Lucía Pérez',
            'birthday' => '1990-10-05', 'marketing_opt_in_at' => $now->subMonth()]);
        Customer::factory()->create(['business_id' => $business->id, 'name' => 'Jorge Díaz',
            'birthday' => '1985-11-20', 'marketing_opt_in_at' => $now->subMonth()]);
    }

    private static function thread(Business $business, string $name, CarbonImmutable $when, bool $waiting = false): void
    {
        $conversation = Conversation::factory()->create([
            'business_id' => $business->id,
            'contact_name' => $name,
            'last_message_at' => $when,
            ...($waiting ? ['status' => ConversationStatus::Team, 'escalated_at' => $when] : []),
        ]);

        $message = ConversationMessage::factory()->for($conversation)->create(['business_id' => $business->id, 'direction' => MessageDirection::In]);
        $message->forceFill(['created_at' => $when])->save();
        $conversation->forceFill(['created_at' => $when])->save();
    }
}
