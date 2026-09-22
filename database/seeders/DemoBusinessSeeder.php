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
            'dr-juan' => [
                'name' => 'Dr. Juan Herrera',
                'faqs' => [
                    [
                        'title' => 'Días y turnos del doctor',
                        'content' => 'Pregunta: ¿Qué días atiende el doctor?'."\n".'Respuesta: El Dr. Juan Herrera atiende lunes, miércoles y viernes de 14 a 19 en su consultorio. Decime qué día te queda cómodo y te reservo el turno por acá.',
                    ],
                    [
                        'title' => 'Precio de la consulta',
                        'content' => 'Pregunta: ¿Cuánto sale la consulta?'."\n".'Respuesta: La consulta particular cuesta $25.000. Con obra social o prepaga puede tener cobertura total o un copago menor, según el plan.',
                    ],
                    [
                        'title' => 'Dónde atiende',
                        'content' => 'Pregunta: ¿Dónde queda el consultorio?'."\n".'Respuesta: El consultorio está en Av. Callao 456, piso 2, Buenos Aires, a una cuadra del subte.',
                    ],
                    [
                        'title' => 'Recetas y estudios',
                        'content' => 'Pregunta: ¿Puedo renovar una receta sin turno?'."\n".'Respuesta: Si sos paciente en seguimiento, el doctor renueva recetas sin turno presencial: pedila por acá y la retirás en secretaría. Los resultados de estudios se revisan en la consulta.',
                    ],
                ],
            ],
            'panaderia' => [
                'name' => 'Panadería La Espiga',
                'faqs' => [
                    [
                        'title' => 'Horarios',
                        'content' => 'Pregunta: ¿A qué hora abren?'."\n".'Respuesta: Abrimos todos los días de 7 a 21, con pan recién horneado a la mañana y a media tarde.',
                    ],
                    [
                        'title' => 'Tortas por encargo',
                        'content' => 'Pregunta: ¿Hacen tortas por encargo?'."\n".'Respuesta: Sí, tortas personalizadas desde $28.000 el kilo. Pedilas con 48 horas de anticipación; se señan con transferencia.',
                    ],
                    [
                        'title' => 'Productos sin TACC',
                        'content' => 'Pregunta: ¿Tienen productos sin TACC?'."\n".'Respuesta: Sí: pan y facturas sin TACC los martes y jueves. Conviene reservarlos por acá porque vuelan.',
                    ],
                    [
                        'title' => 'Envíos',
                        'content' => 'Pregunta: ¿Hacen envíos?'."\n".'Respuesta: Hacemos envíos hasta 15 cuadras con un mínimo de $10.000. Los pedidos antes de las 11 llegan al mediodía.',
                    ],
                ],
            ],
            'restaurante' => [
                'name' => 'Restaurante La Nona',
                'faqs' => [
                    [
                        'title' => 'Reservas',
                        'content' => 'Pregunta: ¿Puedo reservar para hoy?'."\n".'Respuesta: Sí, tomamos reservas hasta las 21:30. Decime cuántos son y a qué hora vienen y te confirmo la mesa.',
                    ],
                    [
                        'title' => 'Horarios',
                        'content' => 'Pregunta: ¿Qué días abren?'."\n".'Respuesta: Abrimos de martes a domingo: mediodía de 12 a 15 y noche de 20 a 24.',
                    ],
                    [
                        'title' => 'Menú del día',
                        'content' => 'Pregunta: ¿Tienen menú del día?'."\n".'Respuesta: Sí, el menú ejecutivo cuesta $22.000 e incluye entrada, principal y postre. Va de martes a viernes al mediodía.',
                    ],
                    [
                        'title' => 'Delivery',
                        'content' => 'Pregunta: ¿Hacen delivery?'."\n".'Respuesta: Sí, delivery propio hasta 20 cuadras y también por las apps. La demora ronda los 40 minutos.',
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
                        'content' => 'Pregunta: ¿Hacen copias de llaves?'."\n".'Respuesta: Sí, en el momento: la llave común cuesta $4.000 y la de auto $9.000.',
                    ],
                    [
                        'title' => 'Envíos a obra',
                        'content' => 'Pregunta: ¿Llevan pedidos a obra?'."\n".'Respuesta: Sí, el envío es gratis desde $50.000 dentro del barrio. Pedidos antes de las 14 salen el mismo día.',
                    ],
                    [
                        'title' => 'Medios de pago',
                        'content' => 'Pregunta: ¿Cómo puedo pagar?'."\n".'Respuesta: Aceptamos efectivo, tarjetas y billeteras por QR. Desde $30.000 tenés 3 cuotas sin interés.',
                    ],
                ],
            ],
            'veterinaria' => [
                'name' => 'Veterinaria Patitas',
                'faqs' => [
                    [
                        'title' => 'Turnos y urgencias',
                        'content' => 'Pregunta: ¿Atienden urgencias?'."\n".'Respuesta: Consultas con turno de lunes a sábado de 9 a 20. Las urgencias se atienden en el día: avisá por acá y te esperamos.',
                    ],
                    [
                        'title' => 'Precio de la consulta',
                        'content' => 'Pregunta: ¿Cuánto sale la consulta?'."\n".'Respuesta: La consulta cuesta $20.000 e incluye el control general de tu mascota.',
                    ],
                    [
                        'title' => 'Vacunas',
                        'content' => 'Pregunta: ¿Qué vacunas aplican?'."\n".'Respuesta: Aplicamos todas: la antirrábica cuesta $15.000 y la quíntuple $25.000. Te recordamos el refuerzo cuando toca.',
                    ],
                    [
                        'title' => 'Baño y peluquería canina',
                        'content' => 'Pregunta: ¿Hacen baño y corte?'."\n".'Respuesta: Sí, baño y corte desde $18.000 según el tamaño, siempre con turno. Retiramos y llevamos a domicilio en la zona.',
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
