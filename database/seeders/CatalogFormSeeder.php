<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CatalogForm;
use Illuminate\Database\Seeder;

class CatalogFormSeeder extends Seeder
{
    /**
     * Maestros del panel de Catálogos. Cada fila es un maestro que el hub
     * (`⚡catalogs-hub`) lista a la izquierda y cuyo editor renderiza con
     * `<livewire:is :component="...">`. Sumar un maestro = una fila más.
     *
     * Idempotente: keyed por `component` (único por maestro).
     */
    public function run(): void
    {
        $catalogs = [
            // Ubicaciones
            ['group' => 'Ubicaciones', 'title' => 'Países', 'description' => 'Cada país define su moneda y su código telefónico.', 'component' => 'catalog.country', 'permission_key' => 'catalog.country', 'icon' => 'globe', 'order' => 1],
            ['group' => 'Ubicaciones', 'title' => 'Provincias', 'description' => 'Se agrupan por país.', 'component' => 'catalog.province', 'permission_key' => 'catalog.province', 'icon' => 'globe', 'order' => 2],
            ['group' => 'Ubicaciones', 'title' => 'Regiones', 'description' => 'Cuelgan de cada provincia.', 'component' => 'catalog.region', 'permission_key' => 'catalog.region', 'icon' => 'globe', 'order' => 3],

            // Facturación
            ['group' => 'Facturación', 'title' => 'Monedas', 'description' => 'Divisas ISO 4217 disponibles para precios y facturación.', 'component' => 'catalog.currency', 'permission_key' => 'catalog.currency', 'icon' => 'star', 'order' => 4],
            ['group' => 'Facturación', 'title' => 'Condiciones fiscales', 'description' => 'Definidas por país (responsable inscripto, monotributo…).', 'component' => 'catalog.tax-condition', 'permission_key' => 'catalog.tax-condition', 'icon' => 'shield-check', 'order' => 5],

            // Sistema
            ['group' => 'Sistema', 'title' => 'Estados', 'description' => 'Estados genéricos reutilizables por el sistema.', 'component' => 'catalog.status', 'permission_key' => 'catalog.status', 'icon' => 'check', 'order' => 6],
            ['group' => 'Sistema', 'title' => 'Redes sociales', 'description' => 'Catálogo de redes con su URL base e ícono.', 'component' => 'catalog.social-network', 'permission_key' => 'catalog.social-network', 'icon' => 'message-circle', 'order' => 7],
        ];

        foreach ($catalogs as $catalog) {
            CatalogForm::updateOrCreate(
                ['group' => $catalog['group'], 'title' => $catalog['title']],
                [...$catalog, 'is_active' => true],
            );
        }
    }
}
