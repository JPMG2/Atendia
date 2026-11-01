<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Country;
use App\Models\Currency;
use App\Models\KnowledgeDocument;
use App\Services\Tenant;
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
        // Venezuela is the pilot and the plans sell in dollars, so the
        // examples live there and quote in dollars. The factory only steps in
        // on a bare test database.
        $countryId = Country::query()->where('iso2', 'VE')->value('id')
            ?? Country::query()->value('id')
            ?? Country::factory()->create()->id;
        $currencyId = Currency::query()->where('code', 'USD')->value('id');

        foreach ($this->rubros() as $rubro => $definition) {
            $business = Business::query()->where('is_demo', true)->updateOrCreate(
                ['billing_email' => Business::DEMO_EMAILS[$rubro]],
                // `is_demo` is written on every run on purpose: it is what
                // keeps these eight out of the money, and a demo that lost the
                // flag would be counted as a paying client.
                ['name' => $definition['name'], 'city' => 'Caracas', 'country_id' => $countryId, 'currency_id' => $currencyId, 'is_active' => true, 'is_demo' => true],
            );

            // An example's rows are seen only from inside its own tenant.
            app(Tenant::class)->for($business->id, function () use ($business, $definition): void {
                // A renamed FAQ must not keep answering under its old title.
                // The retriever reads chunks without looking at the parent's
                // soft delete, so a trashed row goes for good too.
                KnowledgeDocument::withTrashed()
                    ->where('business_id', $business->id)
                    ->where('source_type', 'faq')
                    ->where(fn ($stale) => $stale
                        ->whereNotIn('title', array_column($definition['faqs'], 'title'))
                        ->orWhereNotNull('deleted_at'))
                    ->get()
                    ->each->forceDelete();

                foreach ($definition['faqs'] as $faq) {
                    KnowledgeDocument::query()->updateOrCreate(
                        ['business_id' => $business->id, 'title' => $faq['title']],
                        ['source_type' => 'faq', 'content' => $faq['content']],
                    );
                }
            });
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
                        'title' => 'Citas y cómo reservar',
                        'content' => 'Pregunta: ¿Cómo pido una cita?'."\n".'Respuesta: Atendemos con cita de lunes a viernes de 8 a 20 y sábados de 9 a 13. Dime qué estudio necesitas y te ofrezco los próximos horarios libres; esta semana suele haber cupo jueves y viernes en la mañana.',
                    ],
                    [
                        'title' => 'Precio de la ecografía',
                        'content' => 'Pregunta: ¿Cuánto cuesta una ecografía?'."\n".'Respuesta: La ecografía abdominal cuesta $25 y la ecografía doppler $40. Con seguro suele tener cobertura total o un copago menor, según la póliza.',
                    ],
                    [
                        'title' => 'Precio del electrocardiograma',
                        'content' => 'Pregunta: ¿Cuánto cuesta un electro?'."\n".'Respuesta: El electrocardiograma cuesta $20 e incluye el informe del cardiólogo el mismo día.',
                    ],
                    [
                        'title' => 'Dónde estamos',
                        'content' => 'Pregunta: ¿Dónde están ubicados?'."\n".'Respuesta: Estamos en Av. Libertad 742, Caracas, a una cuadra de la plaza. Hay estacionamiento propio para pacientes.',
                    ],
                    [
                        'title' => 'Preparación y ayuno',
                        'content' => 'Pregunta: ¿Necesito ayuno?'."\n".'Respuesta: Para la ecografía abdominal se piden 6 horas de ayuno. El electrocardiograma no necesita ninguna preparación.',
                    ],
                    [
                        'title' => 'Seguros y medios de pago',
                        'content' => 'Pregunta: ¿Qué seguros aceptan?'."\n".'Respuesta: Trabajamos con los principales seguros y pólizas de salud. También puedes pagar con tarjeta, transferencia o efectivo, y además con pago móvil o Zelle.',
                    ],
                ],
            ],
            'peluqueria' => [
                'name' => 'Peluquería Lumen',
                'faqs' => [
                    [
                        'title' => 'Precio del corte',
                        'content' => 'Pregunta: ¿Cuánto cuesta el corte?'."\n".'Respuesta: El corte de dama cuesta $10 y el de caballero $6, con lavado incluido. Los viernes hay 20% de descuento en cortes en la mañana.',
                    ],
                    [
                        'title' => 'Color y mechas',
                        'content' => 'Pregunta: ¿Hacen color y mechas?'."\n".'Respuesta: Sí: color completo desde $25 y mechas balayage desde $40, según el largo. Incluyen tratamiento de hidratación.',
                    ],
                    [
                        'title' => 'Citas y sin cita',
                        'content' => 'Pregunta: ¿Atienden sin cita?'."\n".'Respuesta: Con cita aseguras tu horario, pero también recibimos sin cita según disponibilidad. Martes a sábado de 9 a 19.',
                    ],
                    [
                        'title' => 'Dónde estamos',
                        'content' => 'Pregunta: ¿Dónde están ubicados?'."\n".'Respuesta: Estamos en Calle Luna 123, Caracas, frente a la plaza. Hay estacionamiento en la misma cuadra.',
                    ],
                ],
            ],
            'dr-juan' => [
                'name' => 'Dr. Juan Herrera',
                'faqs' => [
                    [
                        'title' => 'Días y citas del doctor',
                        'content' => 'Pregunta: ¿Qué días atiende el doctor?'."\n".'Respuesta: El Dr. Juan Herrera atiende lunes, miércoles y viernes de 14 a 19 en su consultorio. Dime qué día te queda cómodo y te reservo la cita por aquí.',
                    ],
                    [
                        'title' => 'Precio de la consulta',
                        'content' => 'Pregunta: ¿Cuánto cuesta la consulta?'."\n".'Respuesta: La consulta particular cuesta $30. Con seguro puede tener cobertura total o un copago menor, según la póliza.',
                    ],
                    [
                        'title' => 'Dónde atiende',
                        'content' => 'Pregunta: ¿Dónde queda el consultorio?'."\n".'Respuesta: El consultorio está en Av. Urdaneta 456, piso 2, Caracas, a una cuadra del Metro.',
                    ],
                    [
                        'title' => 'Récipes y estudios',
                        'content' => 'Pregunta: ¿Puedo renovar un récipe sin cita?'."\n".'Respuesta: Si eres paciente en seguimiento, el doctor renueva récipes sin cita presencial: pídelo por aquí y lo retiras en secretaría. Los resultados de estudios se revisan en la consulta.',
                    ],
                ],
            ],
            'panaderia' => [
                'name' => 'Panadería La Espiga',
                'faqs' => [
                    [
                        'title' => 'Horarios',
                        'content' => 'Pregunta: ¿A qué hora abren?'."\n".'Respuesta: Abrimos todos los días de 7 a 21, con pan recién horneado en la mañana y a media tarde.',
                    ],
                    [
                        'title' => 'Tortas por encargo',
                        'content' => 'Pregunta: ¿Hacen tortas por encargo?'."\n".'Respuesta: Sí, tortas personalizadas desde $15 el kilo. Pídelas con 48 horas de anticipación; se apartan con un abono por pago móvil o transferencia.',
                    ],
                    [
                        'title' => 'Productos sin gluten',
                        'content' => 'Pregunta: ¿Tienen productos sin gluten?'."\n".'Respuesta: Sí: pan y dulces sin gluten los martes y jueves. Conviene apartarlos por aquí porque se acaban rápido.',
                    ],
                    [
                        'title' => 'Envíos',
                        'content' => 'Pregunta: ¿Hacen envíos?'."\n".'Respuesta: Hacemos envíos hasta 15 cuadras con un mínimo de $8. Los pedidos antes de las 11 llegan al mediodía.',
                    ],
                ],
            ],
            'restaurante' => [
                'name' => 'Restaurante La Nona',
                'faqs' => [
                    [
                        'title' => 'Reservas',
                        'content' => 'Pregunta: ¿Puedo reservar para hoy?'."\n".'Respuesta: Sí, tomamos reservas hasta las 21:30. Dime cuántos son y a qué hora vienen y te confirmo la mesa.',
                    ],
                    [
                        'title' => 'Horarios',
                        'content' => 'Pregunta: ¿Qué días abren?'."\n".'Respuesta: Abrimos de martes a domingo: mediodía de 12 a 15 y noche de 20 a 24.',
                    ],
                    [
                        'title' => 'Menú del día',
                        'content' => 'Pregunta: ¿Tienen menú del día?'."\n".'Respuesta: Sí, el menú ejecutivo cuesta $8 e incluye entrada, principal y postre. Va de martes a viernes al mediodía.',
                    ],
                    [
                        'title' => 'Delivery',
                        'content' => 'Pregunta: ¿Hacen delivery?'."\n".'Respuesta: Sí, delivery propio en la zona y también por las apps. La demora ronda los 40 minutos.',
                    ],
                ],
            ],
            'ferreteria' => [
                'name' => 'Ferretería El Tornillo',
                'faqs' => [
                    [
                        'title' => 'Horarios',
                        'content' => 'Pregunta: ¿A qué hora abren?'."\n".'Respuesta: Abrimos de lunes a sábado de 8 a 19, horario corrido.',
                    ],
                    [
                        'title' => 'Copias de llaves',
                        'content' => 'Pregunta: ¿Hacen copias de llaves?'."\n".'Respuesta: Sí, en el momento: la llave común cuesta $2 y la de carro $5.',
                    ],
                    [
                        'title' => 'Envíos a obra',
                        'content' => 'Pregunta: ¿Llevan pedidos a obra?'."\n".'Respuesta: Sí, el envío es gratis desde $30 dentro de la zona. Los pedidos antes de las 14 salen el mismo día.',
                    ],
                    [
                        'title' => 'Medios de pago',
                        'content' => 'Pregunta: ¿Cómo puedo pagar?'."\n".'Respuesta: Aceptamos efectivo, tarjetas, pago móvil y Zelle.',
                    ],
                ],
            ],
            'veterinaria' => [
                'name' => 'Veterinaria Patitas',
                'faqs' => [
                    [
                        'title' => 'Citas y urgencias',
                        'content' => 'Pregunta: ¿Atienden urgencias?'."\n".'Respuesta: Consultas con cita de lunes a sábado de 9 a 20. Las urgencias se atienden en el día: avisa por aquí y te esperamos.',
                    ],
                    [
                        'title' => 'Precio de la consulta',
                        'content' => 'Pregunta: ¿Cuánto cuesta la consulta?'."\n".'Respuesta: La consulta cuesta $15 e incluye el control general de tu mascota.',
                    ],
                    [
                        'title' => 'Vacunas',
                        'content' => 'Pregunta: ¿Qué vacunas aplican?'."\n".'Respuesta: Aplicamos todas: la antirrábica cuesta $10 y la quíntuple $20. Te recordamos el refuerzo cuando toca.',
                    ],
                    [
                        'title' => 'Baño y peluquería canina',
                        'content' => 'Pregunta: ¿Hacen baño y corte?'."\n".'Respuesta: Sí, baño y corte desde $12 según el tamaño, siempre con cita. Buscamos y llevamos a domicilio en la zona.',
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
                        'content' => 'Pregunta: ¿Hacen envíos?'."\n".'Respuesta: Sí, envíos propios hasta 10 cuadras con un mínimo de $5; llegan en menos de 30 minutos.',
                    ],
                    [
                        'title' => 'Ofertas del día',
                        'content' => 'Pregunta: ¿Qué ofertas tienen hoy?'."\n".'Respuesta: Hoy hay 2x1 en chocolates y el refresco de 2 litros a mitad de precio llevando dos. Las ofertas cambian todos los días.',
                    ],
                    [
                        'title' => 'Medios de pago',
                        'content' => 'Pregunta: ¿Cómo puedo pagar?'."\n".'Respuesta: Aceptamos efectivo, tarjetas, pago móvil y Zelle.',
                    ],
                ],
            ],
        ];
    }
}
