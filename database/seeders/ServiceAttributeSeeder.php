<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ServiceAttribute;
use Illuminate\Database\Seeder;

class ServiceAttributeSeeder extends Seeder
{
    /**
     * The attribute library the master starts with.
     *
     * GLOBAL and reusable on purpose: a photo is the same attribute on a dish and
     * on a shop product, and which type carries which is the pivot's business.
     * Price, stock and duration are NOT here — they are first-class fields.
     * Idempotent, keyed by `code`.
     */
    public function run(): void
    {
        $attributes = [
            // Cross-cutting: almost any trade uses them.
            ['code' => 'foto', 'name' => 'Foto', 'description' => 'Imagen para mostrarle al cliente.', 'data_type' => 'image', 'sort_order' => 3],
            ['code' => 'notas', 'name' => 'Notas', 'description' => 'Aclaraciones libres.', 'data_type' => 'text', 'sort_order' => 4],
            ['code' => 'profesional', 'name' => 'Profesional', 'description' => 'Quién atiende.', 'data_type' => 'text', 'sort_order' => 5],
            ['code' => 'lugar', 'name' => 'Lugar', 'description' => 'Dónde se presta.', 'data_type' => 'text', 'sort_order' => 6],

            // Scheduling and capacity.
            ['code' => 'personas', 'name' => 'Personas', 'description' => 'Para cuántos.', 'data_type' => 'number', 'unit' => 'personas', 'sort_order' => 10],
            ['code' => 'cupo', 'name' => 'Cupo', 'description' => 'Máximo de asistentes.', 'data_type' => 'number', 'unit' => 'lugares', 'sort_order' => 11],
            ['code' => 'zona', 'name' => 'Zona', 'description' => 'Sectores del local o áreas de cobertura.', 'data_type' => 'text', 'is_multiple' => true, 'sort_order' => 12],
            ['code' => 'hora_retiro', 'name' => 'Hora de retiro', 'description' => 'Cuándo se pasa a buscar.', 'data_type' => 'time', 'sort_order' => 13],

            // Salud.
            ['code' => 'obra_social', 'name' => 'Obra social', 'description' => 'Coberturas que se aceptan.', 'data_type' => 'text', 'is_multiple' => true, 'sort_order' => 20],
            ['code' => 'requiere_ayuno', 'name' => 'Requiere ayuno', 'description' => 'Si el paciente debe venir en ayunas.', 'data_type' => 'boolean', 'sort_order' => 21],
            ['code' => 'preparacion', 'name' => 'Preparación', 'description' => 'Qué tiene que hacer antes de venir.', 'data_type' => 'text', 'sort_order' => 22],

            // Food.
            ['code' => 'picante', 'name' => 'Picante', 'description' => 'Si lleva picante.', 'data_type' => 'boolean', 'sort_order' => 30],
            ['code' => 'apto_celiaco', 'name' => 'Apto celíaco', 'description' => 'Sin TACC.', 'data_type' => 'boolean', 'sort_order' => 31],
            ['code' => 'incluye', 'name' => 'Incluye', 'description' => 'Qué trae el combo o el paquete.', 'data_type' => 'text', 'sort_order' => 32],
            ['code' => 'items', 'name' => 'Ítems', 'description' => 'Qué entra en la orden.', 'data_type' => 'text', 'is_multiple' => true, 'sort_order' => 33],

            // Retail and rentals.
            ['code' => 'talle', 'name' => 'Talle', 'description' => 'Medida disponible.', 'data_type' => 'list', 'options' => ['XS', 'S', 'M', 'L', 'XL'], 'sort_order' => 41],
            ['code' => 'color', 'name' => 'Color', 'description' => 'Colores disponibles.', 'data_type' => 'text', 'is_multiple' => true, 'sort_order' => 42],
            ['code' => 'garantia', 'name' => 'Garantía', 'description' => 'Por cuánto responde.', 'data_type' => 'number', 'unit' => 'meses', 'sort_order' => 43],
            ['code' => 'deposito', 'name' => 'Depósito', 'description' => 'Lo que se deja en garantía y se devuelve.', 'data_type' => 'money', 'sort_order' => 44],

            // Passes and vouchers.
            ['code' => 'sesiones', 'name' => 'Sesiones', 'description' => 'Cuántos usos trae el bono.', 'data_type' => 'number', 'unit' => 'sesiones', 'sort_order' => 50],
            ['code' => 'vigencia', 'name' => 'Vigencia', 'description' => 'Por cuánto tiempo vale.', 'data_type' => 'number', 'unit' => 'días', 'sort_order' => 51],

            // Entrega.
            ['code' => 'a_domicilio', 'name' => 'A domicilio', 'description' => 'Si se lleva hasta la dirección del cliente.', 'data_type' => 'boolean', 'sort_order' => 60],
            ['code' => 'retiro_local', 'name' => 'Retiro en el local', 'description' => 'Si el cliente lo pasa a buscar.', 'data_type' => 'boolean', 'sort_order' => 61],
        ];

        foreach ($attributes as $attribute) {
            ServiceAttribute::query()->firstOrCreate(
                ['code' => $attribute['code']],
                $attribute,
            );
        }
    }
}
