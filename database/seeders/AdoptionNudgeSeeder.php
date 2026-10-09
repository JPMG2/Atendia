<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AdoptionStep;
use App\Models\AdoptionNudge;
use Illuminate\Database\Seeder;

class AdoptionNudgeSeeder extends Seeder
{
    /**
     * A first message for every step where someone can get stuck, so the button
     * opens something on day one. She rewrites any of them from the Catalogs
     * hub: this only creates what is missing and never overwrites her edits.
     */
    public function run(): void
    {
        $nudges = [
            AdoptionStep::Registered->value => [
                '¿Te ayudamos a crear tu negocio?',
                "Hola {nombre},\n\nVimos que te registraste pero todavía no creaste tu negocio. Son unos minutos y después tu asistente ya puede atender por WhatsApp.\n\n¿Quieres que te acompañemos? Respóndenos este correo y lo hacemos juntos.",
            ],
            AdoptionStep::BusinessCreated->value => [
                '¿Te ayudamos a cargar tu catálogo?',
                "Hola {nombre},\n\nYa creaste {negocio}, y lo que sigue es cargar lo que ofreces: sin eso tu asistente no tiene qué contarle a tus clientes.\n\nSi te cuesta, cuéntanos qué vendes y lo cargamos contigo.",
            ],
            AdoptionStep::CatalogLoaded->value => [
                'Falta conectar tu WhatsApp',
                "Hola {nombre},\n\nTu catálogo de {negocio} ya está listo. Falta conectar tu WhatsApp para que tu asistente empiece a contestar: se escanea un código QR con el teléfono del negocio.\n\n¿Lo hacemos juntos?",
            ],
            AdoptionStep::WhatsAppConnected->value => [
                'Tu asistente está esperando su primer cliente',
                "Hola {nombre},\n\n{negocio} ya tiene WhatsApp conectado, pero todavía no entró ningún mensaje. Prueba escribiéndole desde otro teléfono para ver cómo contesta, o cuéntanos si algo no funciona.",
            ],
            AdoptionStep::FirstConversation->value => [
                'Revisemos por qué tu asistente no contestó',
                "Hola {nombre},\n\nEntró un mensaje a {negocio} pero tu asistente todavía no llegó a contestar. Puede faltar algún dato del catálogo o del horario: respóndenos este correo y lo revisamos contigo.",
            ],
        ];

        foreach ($nudges as $step => [$subject, $body]) {
            AdoptionNudge::query()->firstOrCreate(['step' => $step], ['subject' => $subject, 'body' => $body, 'is_active' => true]);
        }
    }
}
