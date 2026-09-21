<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Country;
use App\Models\KnowledgeDocument;
use Illuminate\Database\Seeder;

/**
 * The landing demo's business: "Clínica Vida", the same clinic the scripted
 * hero conversation shows, now backed by real knowledge so the interactive
 * demo answers with the REAL assistant. Idempotent by design; indexing
 * happens through the observer's queued job, so run it with the worker up.
 */
class DemoBusinessSeeder extends Seeder
{
    public function run(): void
    {
        // The catalog holds the countries in production; the factory only
        // steps in on a bare test database.
        $countryId = Country::query()->where('iso2', 'AR')->value('id')
            ?? Country::query()->value('id')
            ?? Country::factory()->create()->id;

        $business = Business::query()->updateOrCreate(
            ['billing_email' => Business::DEMO_EMAIL],
            ['name' => 'Clínica Vida', 'city' => 'Buenos Aires', 'country_id' => $countryId, 'is_active' => true],
        );

        $faqs = [
            [
                'title' => 'Turnos y cómo reservar',
                'content' => 'Pregunta: ¿Cómo saco un turno?'."\n".'Respuesta: Atendemos con turno de lunes a viernes de 8 a 20 y sábados de 9 a 13. Decime qué estudio necesitás y te ofrezco los próximos horarios libres; esta semana suele haber lugar jueves y viernes por la mañana.',
            ],
            [
                'title' => 'Precio de la ecografía',
                'content' => 'Pregunta: ¿Cuánto sale una ecografía?'."\n".'Respuesta: La ecografía abdominal cuesta $45.000 y la ecografía doppler $60.000. Con obra social suele tener cobertura total o un copago menor, según el plan.',
            ],
            [
                'title' => 'Precio del electrocardiograma',
                'content' => 'Pregunta: ¿Cuánto cuesta un electro?'."\n".'Respuesta: El electrocardiograma cuesta $30.000 e incluye el informe del cardiólogo en el día.',
            ],
            [
                'title' => 'Dónde estamos',
                'content' => 'Pregunta: ¿Dónde están ubicados?'."\n".'Respuesta: Estamos en Av. Siempreviva 742, Buenos Aires, a dos cuadras de la estación. Hay estacionamiento propio para pacientes.',
            ],
            [
                'title' => 'Preparación y ayuno',
                'content' => 'Pregunta: ¿Necesito ayuno?'."\n".'Respuesta: Para la ecografía abdominal se piden 6 horas de ayuno. El electrocardiograma no necesita ninguna preparación.',
            ],
            [
                'title' => 'Obras sociales y medios de pago',
                'content' => 'Pregunta: ¿Qué obras sociales aceptan?'."\n".'Respuesta: Trabajamos con las principales obras sociales y prepagas. También podés abonar en efectivo, débito o crédito en hasta 3 cuotas sin interés.',
            ],
        ];

        foreach ($faqs as $faq) {
            KnowledgeDocument::query()->updateOrCreate(
                ['business_id' => $business->id, 'title' => $faq['title']],
                ['source_type' => 'faq', 'content' => $faq['content']],
            );
        }
    }
}
