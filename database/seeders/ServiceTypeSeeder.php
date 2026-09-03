<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BusinessActivity;
use App\Models\BusinessSector;
use App\Models\ServiceAttribute;
use App\Models\ServiceModality;
use App\Models\ServiceType;
use Illuminate\Database\Seeder;

class ServiceTypeSeeder extends Seeder
{
    /**
     * Service types with their attributes and the activities they are suggested to.
     *
     * Look at the bakery: it is suggested counter products and takeaway orders but
     * NOT tables, and it can still adopt tables, because the pivot is a SUGGESTION
     * and not a permission. Idempotent, keyed by `code`, both pivots synced.
     */
    public function run(): void
    {
        foreach ($this->types() as $code => $type) {
            $model = ServiceType::query()->updateOrCreate(
                ['code' => $code],
                [
                    'code' => $code,
                    'name' => $type['name'],
                    'description' => $type['description'],
                    'service_modality_id' => $this->modalityId($type['modality']),
                    'business_sector_id' => $this->sectorId($type['sector']),
                    'sort_order' => $type['sort_order'],
                    'is_active' => true,
                ],
            );

            $model->serviceAttributes()->sync($this->attributePivot($type['attributes']));
            $model->activities()->sync($this->activityPivot($type['activities']));
        }
    }

    /**
     * @return array<string, array{name: string, description: string, modality: string, sector: ?string, sort_order: int, attributes: array<string, array<string, mixed>>, activities: list<string>}>
     */
    private function types(): array
    {
        return [
            'consulta' => [
                'name' => 'Consulta', 'description' => 'Atención con turno, uno a la vez.',
                'modality' => 'cita', 'sector' => 'salud', 'sort_order' => 1,
                'attributes' => [
                    'obra_social' => ['is_required' => true, 'sort_order' => 1],
                    'profesional' => ['sort_order' => 2],
                    'notas' => ['sort_order' => 3],
                ],
                'activities' => ['consultorio-medico', 'odontologia', 'kinesiologia', 'psicologia', 'nutricion', 'veterinaria'],
            ],
            'estudio' => [
                'name' => 'Estudio', 'description' => 'Práctica con preparación previa y resultados.',
                'modality' => 'cita', 'sector' => 'salud', 'sort_order' => 2,
                'attributes' => [
                    'requiere_ayuno' => ['is_required' => true, 'sort_order' => 1],
                    'preparacion' => ['sort_order' => 2],
                    'obra_social' => ['sort_order' => 3],
                ],
                'activities' => ['laboratorio', 'optica'],
            ],
            'control' => [
                'name' => 'Control', 'description' => 'Seguimiento de un tratamiento ya empezado.',
                'modality' => 'cita', 'sector' => 'salud', 'sort_order' => 3,
                'attributes' => ['profesional' => ['sort_order' => 1], 'notas' => ['sort_order' => 2]],
                'activities' => ['consultorio-medico', 'odontologia', 'veterinaria'],
            ],
            'plato' => [
                'name' => 'Plato', 'description' => 'Un ítem de la carta.',
                'modality' => 'producto', 'sector' => 'gastronomia', 'sort_order' => 10,
                'attributes' => [
                    'foto' => ['sort_order' => 1],
                    'picante' => ['sort_order' => 2],
                    'apto_celiaco' => ['sort_order' => 3],
                ],
                'activities' => ['restaurante', 'pizzeria', 'rotiseria', 'bar', 'cafeteria'],
            ],
            'combo' => [
                'name' => 'Combo', 'description' => 'Varios ítems a precio de conjunto.',
                'modality' => 'producto', 'sector' => 'gastronomia', 'sort_order' => 11,
                'attributes' => ['foto' => ['sort_order' => 1], 'incluye' => ['is_required' => true, 'sort_order' => 2]],
                'activities' => ['restaurante', 'pizzeria', 'rotiseria'],
            ],
            'mesa' => [
                'name' => 'Mesa', 'description' => 'Lugar en el salón para una franja horaria.',
                'modality' => 'reserva', 'sector' => 'gastronomia', 'sort_order' => 12,
                'attributes' => [
                    // This is exactly what the override is for: the global attribute is
                    // named one way, and a table calls the same thing another.
                    'personas' => ['is_required' => true, 'sort_order' => 1, 'label_override' => 'Comensales'],
                    'zona' => ['sort_order' => 2, 'label_override' => 'Sector del salón'],
                ],
                'activities' => ['restaurante', 'pizzeria', 'bar', 'cafeteria'],
            ],
            'pedido-llevar' => [
                'name' => 'Pedido para llevar', 'description' => 'Una orden que se retira o se envía.',
                'modality' => 'pedido', 'sector' => 'gastronomia', 'sort_order' => 13,
                'attributes' => [
                    'items' => ['is_required' => true, 'sort_order' => 1],
                    'hora_retiro' => ['sort_order' => 2],
                    'a_domicilio' => ['sort_order' => 3],
                ],
                'activities' => ['restaurante', 'pizzeria', 'rotiseria', 'panaderia', 'heladeria'],
            ],
            'turno-belleza' => [
                'name' => 'Turno de belleza', 'description' => 'Atención personal con turno.',
                'modality' => 'cita', 'sector' => 'belleza', 'sort_order' => 20,
                'attributes' => ['profesional' => ['sort_order' => 1], 'notas' => ['sort_order' => 2]],
                'activities' => ['peluqueria', 'barberia', 'manicuria', 'centro-estetico', 'depilacion', 'spa', 'tatuajes', 'peluqueria-canina'],
            ],
            'bono-sesiones' => [
                'name' => 'Bono de sesiones', 'description' => 'Varias sesiones pagas por adelantado.',
                'modality' => 'bono', 'sector' => 'belleza', 'sort_order' => 21,
                'attributes' => ['sesiones' => ['is_required' => true, 'sort_order' => 1], 'vigencia' => ['sort_order' => 2]],
                'activities' => ['kinesiologia', 'centro-estetico', 'depilacion', 'spa', 'entrenador-personal'],
            ],
            'producto-mostrador' => [
                'name' => 'Producto de mostrador', 'description' => 'Lo que se vende y se lleva.',
                'modality' => 'producto', 'sector' => 'comercio', 'sort_order' => 30,
                'attributes' => ['foto' => ['sort_order' => 1], 'talle' => ['sort_order' => 2], 'color' => ['sort_order' => 3]],
                'activities' => ['kiosco', 'almacen', 'supermercado', 'dietetica', 'tienda-ropa', 'libreria', 'ferreteria', 'electronica', 'floreria', 'petshop', 'farmacia', 'panaderia', 'pasteleria', 'heladeria'],
            ],
            'arreglo' => [
                'name' => 'Arreglo', 'description' => 'Se deja, se trabaja y se retira.',
                'modality' => 'encargo', 'sector' => 'servicios', 'sort_order' => 40,
                'attributes' => ['notas' => ['is_required' => true, 'sort_order' => 1, 'label_override' => 'Qué hay que hacer'], 'hora_retiro' => ['sort_order' => 2]],
                'activities' => ['lavanderia', 'reparacion-electrodomesticos', 'gomeria', 'cerrajeria', 'taller-mecanico'],
            ],
            'visita-presupuesto' => [
                'name' => 'Visita a presupuestar', 'description' => 'Se releva en el lugar y después se cotiza.',
                'modality' => 'presupuesto', 'sector' => 'servicios', 'sort_order' => 41,
                'attributes' => ['zona' => ['is_required' => true, 'sort_order' => 1], 'notas' => ['sort_order' => 2]],
                'activities' => ['mudanzas', 'limpieza', 'fotografia', 'eventos', 'arquitectura', 'cerrajeria'],
            ],
            'alquiler-equipo' => [
                'name' => 'Alquiler de equipo', 'description' => 'Se entrega por un período y se devuelve.',
                'modality' => 'alquiler', 'sector' => 'servicios', 'sort_order' => 42,
                'attributes' => ['deposito' => ['is_required' => true, 'sort_order' => 1], 'garantia' => ['sort_order' => 2]],
                'activities' => ['eventos', 'alquiler-canchas'],
            ],
            'clase-grupal' => [
                'name' => 'Clase grupal', 'description' => 'Horario fijo con cupo.',
                'modality' => 'clase', 'sector' => 'bienestar', 'sort_order' => 50,
                'attributes' => [
                    'cupo' => ['is_required' => true, 'sort_order' => 1],
                    'profesional' => ['sort_order' => 2, 'label_override' => 'Instructor'],
                    'lugar' => ['sort_order' => 3],
                ],
                'activities' => ['yoga', 'pilates', 'natacion', 'gimnasio', 'academia-musica', 'instituto-idiomas'],
            ],
            'abono-mensual' => [
                'name' => 'Abono mensual', 'description' => 'Acceso que se renueva mes a mes.',
                'modality' => 'suscripcion', 'sector' => 'bienestar', 'sort_order' => 51,
                'attributes' => ['vigencia' => ['is_required' => true, 'sort_order' => 1], 'incluye' => ['sort_order' => 2]],
                'activities' => ['gimnasio', 'yoga', 'pilates', 'natacion', 'guarderia-mascotas'],
            ],
            'reserva-habitacion' => [
                'name' => 'Reserva de habitación', 'description' => 'Noches, huéspedes y confirmación al instante.',
                'modality' => 'reserva', 'sector' => 'turismo', 'sort_order' => 60,
                'attributes' => ['personas' => ['is_required' => true, 'sort_order' => 1, 'label_override' => 'Huéspedes'], 'notas' => ['sort_order' => 2]],
                'activities' => ['hotel'],
            ],
            'excursion' => [
                'name' => 'Excursión', 'description' => 'Salida programada con cupo.',
                'modality' => 'reserva', 'sector' => 'turismo', 'sort_order' => 61,
                'attributes' => ['personas' => ['is_required' => true, 'sort_order' => 1], 'lugar' => ['sort_order' => 2]],
                'activities' => ['excursiones', 'agencia-viajes', 'hotel'],
            ],
            'traslado' => [
                'name' => 'Traslado', 'description' => 'De un punto a otro, con hora pactada.',
                'modality' => 'reserva', 'sector' => 'turismo', 'sort_order' => 62,
                'attributes' => ['zona' => ['is_required' => true, 'sort_order' => 1], 'personas' => ['sort_order' => 2]],
                'activities' => ['traslados', 'agencia-viajes', 'hotel'],
            ],
            'visita-propiedad' => [
                'name' => 'Visita a la propiedad', 'description' => 'Recorrer el inmueble con cita.',
                'modality' => 'cita', 'sector' => 'inmobiliaria', 'sort_order' => 70,
                'attributes' => ['zona' => ['is_required' => true, 'sort_order' => 1], 'notas' => ['sort_order' => 2]],
                'activities' => ['inmobiliaria'],
            ],
            'tasacion' => [
                'name' => 'Tasación', 'description' => 'Se releva la propiedad y se cotiza su valor.',
                'modality' => 'presupuesto', 'sector' => 'inmobiliaria', 'sort_order' => 71,
                'attributes' => ['zona' => ['is_required' => true, 'sort_order' => 1], 'notas' => ['sort_order' => 2]],
                'activities' => ['inmobiliaria', 'administracion-alquileres'],
            ],
            'alquiler-temporario' => [
                'name' => 'Alquiler temporario', 'description' => 'Estadías cortas con depósito y vigencia.',
                'modality' => 'alquiler', 'sector' => 'inmobiliaria', 'sort_order' => 72,
                'attributes' => ['personas' => ['is_required' => true, 'sort_order' => 1], 'deposito' => ['sort_order' => 2]],
                'activities' => ['inmobiliaria', 'administracion-alquileres'],
            ],
            'clase-particular' => [
                'name' => 'Clase particular', 'description' => 'Uno a uno, con horario propio.',
                'modality' => 'cita', 'sector' => 'educacion', 'sort_order' => 80,
                'attributes' => ['profesional' => ['sort_order' => 1, 'label_override' => 'Docente'], 'notas' => ['sort_order' => 2]],
                'activities' => ['apoyo-escolar', 'instituto-idiomas', 'academia-musica', 'autoescuela'],
            ],
            'curso' => [
                'name' => 'Curso', 'description' => 'Programa con inicio, fin e inscripción.',
                'modality' => 'clase', 'sector' => 'educacion', 'sort_order' => 81,
                'attributes' => ['cupo' => ['is_required' => true, 'sort_order' => 1], 'vigencia' => ['sort_order' => 2]],
                'activities' => ['instituto-idiomas', 'academia-musica', 'apoyo-escolar', 'autoescuela'],
            ],

            // The open door's generics: what any trade can offer from day one.
            'turno' => [
                'name' => 'Turno', 'description' => 'Atención con día y hora.',
                'modality' => 'cita', 'sector' => 'otro', 'sort_order' => 90,
                'attributes' => ['notas' => ['sort_order' => 1]],
                'activities' => ['otra-actividad', 'lavadero-autos', 'concesionaria'],
            ],
            'pedido' => [
                'name' => 'Pedido', 'description' => 'Una orden con entrega o retiro.',
                'modality' => 'pedido', 'sector' => 'otro', 'sort_order' => 91,
                'attributes' => ['items' => ['is_required' => true, 'sort_order' => 1], 'a_domicilio' => ['sort_order' => 2]],
                'activities' => ['otra-actividad', 'catering', 'almacen', 'supermercado', 'dietetica', 'floreria', 'libreria'],
            ],
            'presupuesto' => [
                'name' => 'Presupuesto', 'description' => 'Se consulta, se cotiza y se decide.',
                'modality' => 'presupuesto', 'sector' => 'otro', 'sort_order' => 92,
                'attributes' => ['notas' => ['is_required' => true, 'sort_order' => 1, 'label_override' => 'Qué necesita']],
                'activities' => ['otra-actividad', 'imprenta', 'agencia-marketing', 'desarrollo-software', 'seguros', 'repuestos'],
            ],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $attributes
     * @return array<int, array<string, mixed>>
     */
    private function attributePivot(array $attributes): array
    {
        $pivot = [];

        foreach ($attributes as $code => $options) {
            $id = ServiceAttribute::query()->where('code', $code)->value('id');

            if ($id === null) {
                continue;
            }

            $pivot[$id] = [
                'is_required' => $options['is_required'] ?? false,
                'sort_order' => $options['sort_order'] ?? 0,
                'label_override' => $options['label_override'] ?? null,
                'hint_override' => $options['hint_override'] ?? null,
            ];
        }

        return $pivot;
    }

    /**
     * @param  list<string>  $codes
     * @return array<int, array<string, int>>
     */
    private function activityPivot(array $codes): array
    {
        $pivot = [];
        $order = 0;

        foreach ($codes as $code) {
            $id = BusinessActivity::query()->where('code', $code)->value('id');

            if ($id === null) {
                continue;
            }

            $pivot[$id] = ['sort_order' => ++$order];
        }

        return $pivot;
    }

    private function modalityId(string $code): int
    {
        return ServiceModality::query()->where('code', $code)->value('id');
    }

    private function sectorId(?string $code): ?int
    {
        return $code === null ? null : BusinessSector::query()->where('code', $code)->value('id');
    }
}
