<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\QuestionIntent;
use Illuminate\Database\Seeder;

class QuestionIntentSeeder extends Seeder
{
    /**
     * The universal seed: what customers ask any business, whatever the
     * sector. Keyed by `key` so a re-run refreshes wording without
     * orphaning the questions already classified.
     */
    public function run(): void
    {
        $intents = [
            ['key' => 'price', 'name' => 'Precios y presupuestos', 'description' => 'Cuánto cuesta algo, pedir un presupuesto o una cotización'],
            ['key' => 'hours', 'name' => 'Horarios', 'description' => 'Cuándo abren o cierran, si atienden un día puntual o en feriados'],
            ['key' => 'location', 'name' => 'Ubicación y cómo llegar', 'description' => 'Dirección, sucursales, cómo llegar, estacionamiento'],
            ['key' => 'booking', 'name' => 'Turnos, reservas y citas', 'description' => 'Pedir, cambiar o confirmar un turno, una reserva o una cita'],
            ['key' => 'availability', 'name' => 'Disponibilidad o stock', 'description' => 'Si hay lugar, cupo, stock o disponibilidad de algo'],
            ['key' => 'payment', 'name' => 'Formas de pago', 'description' => 'Medios de pago, cuotas, transferencias, obras sociales o seguros'],
            ['key' => 'delivery', 'name' => 'Envíos y entregas', 'description' => 'Envíos, domicilio, retiro, plazos de entrega o de resultados'],
            ['key' => 'requirements', 'name' => 'Requisitos o preparación', 'description' => 'Qué hace falta llevar, cumplir o preparar antes'],
            ['key' => 'service_info', 'name' => 'Información de un servicio o producto', 'description' => 'Qué incluye, cómo funciona o en qué consiste algo que ofrece el negocio'],
            ['key' => 'promotions', 'name' => 'Promociones', 'description' => 'Descuentos, ofertas, combos o beneficios'],
            ['key' => 'changes', 'name' => 'Cambios, cancelaciones y devoluciones', 'description' => 'Cancelar, cambiar, devolver o pedir un reembolso'],
            ['key' => 'complaint', 'name' => 'Reclamos', 'description' => 'Una queja o un problema con algo que ya pasó'],
            ['key' => 'human', 'name' => 'Hablar con una persona', 'description' => 'Pide que lo atienda alguien del equipo'],
        ];

        foreach ($intents as $order => $intent) {
            QuestionIntent::query()->updateOrCreate(
                ['key' => $intent['key']],
                ['name' => $intent['name'], 'description' => $intent['description'], 'sort_order' => $order + 1],
            );
        }
    }
}
