<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ServiceType;
use App\Models\Specialty;
use App\Models\SuggestedService;
use Illuminate\Database\Seeder;

class SpecialtySuggestionSeeder extends Seeder
{
    /**
     * The first services (or dishes) a business that marks a specialty is offered.
     * Each is a suggestion of the specialty's activity, tied to it and left out of
     * the activity's general list. Idempotent; run after {@see SpecialtySeeder}.
     * A name an activity already offers in general is never captured: it stays general.
     */
    public function run(): void
    {
        $types = ServiceType::query()->pluck('id', 'code');

        foreach ($this->suggestions() as $specialtyCode => $services) {
            $specialty = Specialty::query()->where('code', $specialtyCode)->sole();
            $order = 0;

            foreach ($services as $name => $typeCode) {
                $row = SuggestedService::query()->firstOrCreate(
                    ['business_activity_id' => $specialty->business_activity_id, 'name' => $name],
                    ['service_type_id' => $types[$typeCode], 'sort_order' => ++$order, 'is_active' => true],
                );

                if ($row->wasRecentlyCreated || $row->specialties()->exists()) {
                    $row->specialties()->syncWithoutDetaching([$specialty->id]);
                }
            }
        }
    }

    /**
     * @return array<string, array<string, string>> specialty code => [name => type code]
     */
    private function suggestions(): array
    {
        return [
            'medicina-general' => [
                'Consulta de medicina general' => 'consulta',
                'Chequeo anual' => 'control',
                'Control de enfermedades crónicas' => 'control',
                'Certificado de salud' => 'consulta',
                'Renovación de tratamiento' => 'control',
            ],
            'medicina-interna' => [
                'Consulta de medicina interna' => 'consulta',
                'Evaluación de síntomas persistentes' => 'consulta',
                'Control de hipertensión' => 'control',
                'Control de colesterol y triglicéridos' => 'control',
                'Segunda opinión clínica' => 'consulta',
            ],
            'alergologia' => [
                'Consulta de alergología' => 'consulta',
                'Pruebas de alergia en la piel' => 'estudio',
                'Tratamiento de rinitis alérgica' => 'consulta',
                'Control de asma y alergias' => 'control',
                'Inmunoterapia' => 'control',
            ],
            'andrologia' => [
                'Consulta de andrología' => 'consulta',
                'Espermograma' => 'estudio',
                'Control de testosterona' => 'control',
                'Consulta por disfunción eréctil' => 'consulta',
            ],
            'angiologia' => [
                'Consulta de angiología' => 'consulta',
                'Ecodoppler venoso' => 'estudio',
                'Tratamiento de várices' => 'consulta',
                'Control de circulación en las piernas' => 'control',
                'Evaluación de pie diabético' => 'consulta',
            ],
            'gastroenterologia' => [
                'Consulta de gastroenterología' => 'consulta',
                'Endoscopia digestiva' => 'estudio',
                'Colonoscopia' => 'estudio',
                'Control de gastritis y reflujo' => 'control',
                'Test de aliento' => 'estudio',
            ],
            'geriatria' => [
                'Consulta de geriatría' => 'consulta',
                'Valoración geriátrica integral' => 'consulta',
                'Control de medicación en adultos mayores' => 'control',
                'Prevención de caídas' => 'consulta',
                'Evaluación de memoria' => 'consulta',
            ],
            'hepatologia' => [
                'Consulta de hepatología' => 'consulta',
                'Elastografía hepática' => 'estudio',
                'Control de hígado graso' => 'control',
                'Seguimiento de hepatitis' => 'control',
            ],
            'infectologia' => [
                'Consulta de infectología' => 'consulta',
                'Control de VIH' => 'control',
                'Consulta del viajero' => 'consulta',
                'Tratamiento de infecciones recurrentes' => 'consulta',
                'Vacunas del adulto' => 'control',
            ],
            'inmunologia' => [
                'Consulta de inmunología' => 'consulta',
                'Estudio de inmunodeficiencias' => 'estudio',
                'Control de enfermedades autoinmunes' => 'control',
                'Evaluación de infecciones frecuentes' => 'consulta',
            ],
            'mastologia' => [
                'Consulta de mastología' => 'consulta',
                'Ecografía mamaria' => 'estudio',
                'Control de nódulos mamarios' => 'control',
                'Biopsia de mama' => 'estudio',
                'Chequeo mamario anual' => 'control',
            ],
            'hematologia' => [
                'Consulta de hematología' => 'consulta',
                'Estudio de anemia' => 'estudio',
                'Control de anticoagulación' => 'control',
                'Seguimiento de trastornos de la coagulación' => 'control',
            ],
            'oncologia' => [
                'Consulta de oncología' => 'consulta',
                'Segunda opinión oncológica' => 'consulta',
                'Control durante el tratamiento' => 'control',
                'Seguimiento posterior al tratamiento' => 'control',
            ],
            'patologia' => [
                'Consulta de patología' => 'consulta',
                'Informe de biopsia' => 'estudio',
                'Estudio de citología' => 'estudio',
                'Revisión de preparados' => 'estudio',
            ],
            'nefrologia' => [
                'Consulta de nefrología' => 'consulta',
                'Control de la función renal' => 'control',
                'Estudio de proteínas en orina' => 'estudio',
                'Seguimiento de diálisis' => 'control',
            ],
            'neumonologia' => [
                'Consulta de neumonología' => 'consulta',
                'Espirometría' => 'estudio',
                'Control de EPOC' => 'control',
                'Control de asma del adulto' => 'control',
                'Estudio del sueño respiratorio' => 'estudio',
            ],
            'neurologia' => [
                'Consulta de neurología' => 'consulta',
                'Electroencefalograma' => 'estudio',
                'Evaluación de cefaleas y migrañas' => 'consulta',
                'Control de epilepsia' => 'control',
                'Evaluación de memoria y demencia' => 'consulta',
            ],
            'neurocirugia' => [
                'Consulta de neurocirugía' => 'consulta',
                'Evaluación de hernia de disco' => 'consulta',
                'Control postoperatorio de columna' => 'control',
                'Segunda opinión neuroquirúrgica' => 'consulta',
            ],
            'neuropediatria' => [
                'Consulta de neuropediatría' => 'consulta',
                'Evaluación del desarrollo infantil' => 'consulta',
                'Electroencefalograma infantil' => 'estudio',
                'Control de epilepsia infantil' => 'control',
            ],
            'proctologia' => [
                'Consulta de proctología' => 'consulta',
                'Anoscopia' => 'estudio',
                'Tratamiento de hemorroides' => 'consulta',
                'Control postoperatorio anal' => 'control',
            ],
            'reumatologia' => [
                'Consulta de reumatología' => 'consulta',
                'Control de artritis' => 'control',
                'Infiltración articular' => 'consulta',
                'Estudio de enfermedades reumáticas autoinmunes' => 'estudio',
                'Control de osteoporosis' => 'control',
            ],
            'sexologia' => [
                'Consulta de sexología' => 'consulta',
                'Terapia sexual individual' => 'consulta',
                'Terapia sexual de pareja' => 'consulta',
                'Orientación sexual y reproductiva' => 'consulta',
            ],
            'fertilidad' => [
                'Consulta de fertilidad' => 'consulta',
                'Estudio de fertilidad de la pareja' => 'estudio',
                'Control de ovulación' => 'control',
                'Plan de reproducción asistida' => 'presupuesto',
            ],
            'anestesiologia' => [
                'Consulta preanestésica' => 'consulta',
                'Evaluación de riesgo anestésico' => 'consulta',
                'Control del dolor posoperatorio' => 'control',
            ],
            'medicina-antienvejecimiento' => [
                'Consulta de medicina antienvejecimiento' => 'consulta',
                'Evaluación de salud integral' => 'consulta',
                'Control de hormonas y vitaminas' => 'control',
                'Plan personalizado de bienestar' => 'consulta',
            ],
            'ozonoterapia' => [
                'Consulta de ozonoterapia' => 'consulta',
                'Sesión de ozonoterapia' => 'consulta',
                'Plan de sesiones de ozonoterapia' => 'bono-sesiones',
            ],
            'trastornos-del-sueno' => [
                'Consulta por trastornos del sueño' => 'consulta',
                'Polisomnografía' => 'estudio',
                'Estudio de apnea del sueño' => 'estudio',
                'Control de insomnio' => 'control',
            ],
            'terapia-del-dolor' => [
                'Consulta de terapia del dolor' => 'consulta',
                'Bloqueo nervioso' => 'consulta',
                'Infiltración para el dolor' => 'consulta',
                'Control del dolor crónico' => 'control',
            ],
            'toxicologia' => [
                'Consulta de toxicología' => 'consulta',
                'Evaluación por intoxicación' => 'consulta',
                'Estudio toxicológico' => 'estudio',
            ],
            'estomatologia' => [
                'Consulta de estomatología' => 'consulta',
                'Evaluación de lesiones en la boca' => 'consulta',
                'Control de la mucosa bucal' => 'control',
                'Biopsia de lesión oral' => 'estudio',
            ],

            'cocina-china' => [
                'Arroz frito' => 'plato',
                'Chow mein' => 'plato',
                'Pollo agridulce' => 'plato',
                'Rollitos primavera' => 'plato',
                'Sopa wantán' => 'plato',
                'Combo de comida china para dos' => 'combo',
            ],
            'cocina-japonesa' => [
                'Sushi roll' => 'plato',
                'Nigiri' => 'plato',
                'Sashimi' => 'plato',
                'Ramen' => 'plato',
                'Tempura' => 'plato',
                'Combo de sushi para compartir' => 'combo',
            ],
            'cocina-tailandesa' => [
                'Pad thai' => 'plato',
                'Curry verde' => 'plato',
                'Curry rojo' => 'plato',
                'Sopa tom yum' => 'plato',
                'Arroz frito tailandés' => 'plato',
            ],
            'cocina-arabe' => [
                'Shawarma' => 'plato',
                'Falafel' => 'plato',
                'Kibbe' => 'plato',
                'Hummus' => 'plato',
                'Tabule' => 'plato',
                'Combo árabe para compartir' => 'combo',
            ],
            'cocina-italiana' => [
                'Pasta fresca' => 'plato',
                'Lasaña' => 'plato',
                'Ravioles' => 'plato',
                'Risotto' => 'plato',
                'Ñoquis' => 'plato',
                'Tiramisú' => 'plato',
            ],
            'cocina-espanola' => [
                'Paella' => 'plato',
                'Tortilla española' => 'plato',
                'Croquetas' => 'plato',
                'Gambas al ajillo' => 'plato',
                'Calamares a la romana' => 'plato',
            ],
            'cocina-francesa' => [
                'Quiche' => 'plato',
                'Crepes' => 'plato',
                'Sopa de cebolla' => 'plato',
                'Pato confitado' => 'plato',
                'Crème brûlée' => 'plato',
            ],
            'cocina-alemana' => [
                'Schnitzel' => 'plato',
                'Salchichas con chucrut' => 'plato',
                'Codillo' => 'plato',
                'Strudel de manzana' => 'plato',
                'Papas alemanas' => 'plato',
            ],
            'cocina-mexicana' => [
                'Tacos al pastor' => 'plato',
                'Quesadillas' => 'plato',
                'Burritos' => 'plato',
                'Enchiladas' => 'plato',
                'Guacamole con nachos' => 'plato',
            ],
            'cocina-peruana' => [
                'Ceviche' => 'plato',
                'Lomo saltado' => 'plato',
                'Ají de gallina' => 'plato',
                'Causa limeña' => 'plato',
                'Anticuchos' => 'plato',
            ],
            'cocina-mediterranea' => [
                'Ensalada griega' => 'plato',
                'Pulpo a la parrilla' => 'plato',
                'Cordero asado' => 'plato',
                'Berenjenas rellenas' => 'plato',
                'Pescado a la sal' => 'plato',
            ],
            'cocina-fusion' => [
                'Plato del chef' => 'plato',
                'Menú degustación' => 'plato',
                'Tabla para compartir' => 'plato',
                'Postre de autor' => 'plato',
            ],
            'cocina-criolla' => [
                'Pabellón criollo' => 'plato',
                'Sancocho' => 'plato',
                'Asado criollo' => 'plato',
                'Cachapas con queso' => 'plato',
                'Locro' => 'plato',
            ],
            'cocina-de-carnes' => [
                'Bife de chorizo' => 'plato',
                'Lomito' => 'plato',
                'Costillas a la parrilla' => 'plato',
                'Churrasco' => 'plato',
                'Hamburguesa gourmet' => 'plato',
            ],
            'cocina-de-pescados' => [
                'Pescado frito' => 'plato',
                'Filete de pescado a la plancha' => 'plato',
                'Cazuela de mariscos' => 'plato',
                'Camarones al ajillo' => 'plato',
                'Parrillada de mariscos' => 'plato',
            ],
            'cocina-vegetariana' => [
                'Hamburguesa vegetariana' => 'plato',
                'Ensalada completa' => 'plato',
                'Wok de vegetales' => 'plato',
                'Milanesa de berenjena' => 'plato',
                'Tarta de verduras' => 'plato',
            ],
            'cocina-gourmet' => [
                'Menú de seis pasos' => 'plato',
                'Maridaje de vinos' => 'plato',
                'Mesa del chef' => 'plato',
                'Postre de temporada' => 'plato',
            ],
            'cocina-campestre' => [
                'Almuerzo campestre' => 'plato',
                'Parrillada para grupos' => 'plato',
                'Menú de fin de semana' => 'plato',
                'Postre casero' => 'plato',
            ],
            'cocina-de-playa' => [
                'Pescado frito con tostones' => 'plato',
                'Cóctel de camarones' => 'plato',
                'Ensalada de mariscos' => 'plato',
                'Bebidas frías' => 'plato',
            ],
        ];
    }
}
