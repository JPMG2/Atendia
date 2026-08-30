<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ServiceModality;
use Illuminate\Database\Seeder;

class ServiceModalitySeeder extends Seeder
{
    /**
     * The modalities the master starts with.
     *
     * What earns a place here rather than being a service TYPE: does it change
     * what the assistant ASKS and what the system has to REMEMBER? If only the
     * word changes, it is a type. Idempotent, keyed by `code`.
     */
    public function run(): void
    {
        $modalities = [
            ['code' => 'cita', 'name' => 'Cita / Turno', 'description' => 'Fecha, hora y duración con un profesional o un recurso. Uno a la vez.', 'icon' => 'calendar-check', 'sort_order' => 1],
            ['code' => 'clase', 'name' => 'Clase / Evento', 'description' => 'Horario fijo con cupo: varios asistentes en el mismo turno.', 'icon' => 'users', 'sort_order' => 2],
            ['code' => 'reserva', 'name' => 'Reserva', 'description' => 'Un espacio para una franja horaria y una cantidad de personas.', 'icon' => 'calendar', 'sort_order' => 3],
            ['code' => 'fila', 'name' => 'Orden de llegada', 'description' => 'Sin horario: número de espera y tiempo estimado de atención.', 'icon' => 'bell', 'sort_order' => 4],
            ['code' => 'producto', 'name' => 'Producto', 'description' => 'Stock y precio fijo: se elige y se lleva.', 'icon' => 'package', 'sort_order' => 5],
            ['code' => 'pedido', 'name' => 'Pedido', 'description' => 'Varios ítems en una misma orden, con entrega o retiro.', 'icon' => 'store', 'sort_order' => 6],
            ['code' => 'encargo', 'name' => 'Encargo', 'description' => 'Se deja algo, se trabaja y se retira. Lleva estado de avance.', 'icon' => 'workflow', 'sort_order' => 7],
            ['code' => 'presupuesto', 'name' => 'Presupuesto', 'description' => 'Sin precio fijo: se releva, se cotiza y recién ahí se acepta.', 'icon' => 'message-square', 'sort_order' => 8],
            ['code' => 'alquiler', 'name' => 'Alquiler', 'description' => 'Se entrega por un período y se devuelve. Lleva depósito y estado.', 'icon' => 'repeat', 'sort_order' => 9],
            ['code' => 'suscripcion', 'name' => 'Suscripción / Abono', 'description' => 'Vigencia y cobro que se renueva por período.', 'icon' => 'refresh-cw', 'sort_order' => 10],
            ['code' => 'bono', 'name' => 'Bono de sesiones', 'description' => 'Saldo prepago que se descuenta con cada uso.', 'icon' => 'ticket', 'sort_order' => 11],
            ['code' => 'donacion', 'name' => 'Aporte / Donación', 'description' => 'Monto libre, sin contraprestación.', 'icon' => 'heart', 'sort_order' => 12],
        ];

        foreach ($modalities as $modality) {
            ServiceModality::query()->firstOrCreate(
                ['code' => $modality['code']],
                $modality,
            );
        }
    }
}
