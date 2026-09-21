<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Country;
use App\Models\KnowledgeDocument;
use Illuminate\Database\Seeder;

/**
 * The landing demo's businesses: one per rubro of the hero's selector, each
 * backed by real knowledge so the interactive demo answers with the REAL
 * assistant — proving "cualquier rubro" instead of claiming it. Idempotent
 * by design; indexing happens through the observer's queued job, so run it
 * with the worker up.
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

        foreach ($this->rubros() as $rubro => $definition) {
            $business = Business::query()->updateOrCreate(
                ['billing_email' => Business::DEMO_EMAILS[$rubro]],
                ['name' => $definition['name'], 'city' => 'Buenos Aires', 'country_id' => $countryId, 'is_active' => true],
            );

            foreach ($definition['faqs'] as $faq) {
                KnowledgeDocument::query()->updateOrCreate(
                    ['business_id' => $business->id, 'title' => $faq['title']],
                    ['source_type' => 'faq', 'content' => $faq['content']],
                );
            }
        }
    }

    /**
     * @return array<string, array{name: string, faqs: list<array{title: string, content: string}>}>
     */
    private function rubros(): array
    {
        return [
            'clinica' => [
                'name' => 'Clínica Vida',
                'faqs' => [
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
                ],
            ],
            'peluqueria' => [
                'name' => 'Peluquería Lumen',
                'faqs' => [
                    [
                        'title' => 'Precio del corte',
                        'content' => 'Pregunta: ¿Cuánto sale el corte?'."\n".'Respuesta: El corte de dama cuesta $18.000 y el de caballero $12.000, con lavado incluido. Los viernes hay 20% de descuento en cortes por la mañana.',
                    ],
                    [
                        'title' => 'Color y mechas',
                        'content' => 'Pregunta: ¿Hacen color y mechas?'."\n".'Respuesta: Sí: color completo desde $35.000 y mechas balayage desde $55.000, según largo. Incluyen tratamiento de hidratación.',
                    ],
                    [
                        'title' => 'Turnos y sin turno',
                        'content' => 'Pregunta: ¿Atienden sin turno?'."\n".'Respuesta: Con turno asegurás tu horario, pero también recibimos sin turno según disponibilidad. Martes a sábado de 9 a 19.',
                    ],
                    [
                        'title' => 'Dónde estamos',
                        'content' => 'Pregunta: ¿Dónde están ubicados?'."\n".'Respuesta: Estamos en Calle Luna 123, Buenos Aires, frente a la plaza. Podés venir en bici: hay bicicletero en la puerta.',
                    ],
                ],
            ],
            'kiosco' => [
                'name' => 'Kiosco El Faro',
                'faqs' => [
                    [
                        'title' => 'Horarios',
                        'content' => 'Pregunta: ¿A qué hora abren?'."\n".'Respuesta: Abrimos todos los días de 7 a 23, feriados incluidos.',
                    ],
                    [
                        'title' => 'Envíos',
                        'content' => 'Pregunta: ¿Hacen envíos?'."\n".'Respuesta: Sí, envíos propios hasta 10 cuadras con un mínimo de $8.000; llegan en menos de 30 minutos.',
                    ],
                    [
                        'title' => 'Promos del día',
                        'content' => 'Pregunta: ¿Qué promos tienen hoy?'."\n".'Respuesta: Hoy hay 2x1 en alfajores y la gaseosa de 1,5 L a mitad de precio llevando dos. Las promos cambian todos los días.',
                    ],
                    [
                        'title' => 'Medios de pago',
                        'content' => 'Pregunta: ¿Cómo puedo pagar?'."\n".'Respuesta: Aceptamos efectivo, tarjetas y billeteras virtuales por QR. No hay mínimo para pagar con QR.',
                    ],
                ],
            ],
        ];
    }
}
