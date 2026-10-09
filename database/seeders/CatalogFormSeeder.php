<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CatalogForm;
use Illuminate\Database\Seeder;

class CatalogFormSeeder extends Seeder
{
    /**
     * The masters of the catalog panel. Each row is one the hub lists on the left
     * and renders with `<livewire:is>`. Adding a master is one more row.
     * Idempotent, keyed by `component`.
     */
    public function run(): void
    {
        $catalogs = [
            // Ubicaciones
            ['group' => 'Ubicaciones', 'title' => 'Países', 'description' => 'Cada país define su moneda y su código telefónico.', 'component' => 'catalog.country', 'permission_key' => 'catalog.country', 'icon' => 'globe', 'order' => 1],
            ['group' => 'Ubicaciones', 'title' => 'Provincias', 'description' => 'Se agrupan por país.', 'component' => 'catalog.province', 'permission_key' => 'catalog.province', 'icon' => 'globe', 'order' => 2],
            ['group' => 'Ubicaciones', 'title' => 'Regiones', 'description' => 'Cuelgan de cada provincia.', 'component' => 'catalog.region', 'permission_key' => 'catalog.region', 'icon' => 'globe', 'order' => 3],

            // Invoicing.
            ['group' => 'Facturación', 'title' => 'Monedas', 'description' => 'Divisas ISO 4217 disponibles para precios y facturación.', 'component' => 'catalog.currency', 'permission_key' => 'catalog.currency', 'icon' => 'star', 'order' => 4],
            ['group' => 'Facturación', 'title' => 'Condiciones fiscales', 'description' => 'Definidas por país (responsable inscripto, monotributo…).', 'component' => 'catalog.tax-condition', 'permission_key' => 'catalog.tax-condition', 'icon' => 'shield-check', 'order' => 5],

            // Business: what it picks while setting itself up.
            ['group' => 'Negocio', 'title' => 'Rubros', 'description' => 'Agrupación mayor del negocio: Salud, Gastronomía, Belleza…', 'component' => 'catalog.business-sector', 'permission_key' => 'catalog.business-sector', 'icon' => 'store', 'order' => 6],
            ['group' => 'Negocio', 'title' => 'Actividades', 'description' => 'El oficio concreto dentro del rubro. Define cómo atiende el asistente.', 'component' => 'catalog.business-activity', 'permission_key' => 'catalog.business-activity', 'icon' => 'workflow', 'order' => 7],
            ['group' => 'Servicios', 'title' => 'Tipos de servicio', 'description' => 'QUÉ ofrece un negocio: Consulta, Plato, Mesa. Hereda una modalidad y lleva atributos.', 'component' => 'catalog.service-type', 'permission_key' => 'catalog.service-type', 'icon' => 'library', 'order' => 8],
            ['group' => 'Servicios', 'title' => 'Modalidades', 'description' => 'CÓMO se ofrece un servicio: qué pregunta el asistente y qué recuerda el sistema.', 'component' => 'catalog.service-modality', 'permission_key' => 'catalog.service-modality', 'icon' => 'shapes', 'order' => 9],
            ['group' => 'Servicios', 'title' => 'Atributos', 'description' => 'Biblioteca de campos reutilizables que puede llevar un tipo de servicio.', 'component' => 'catalog.service-attribute', 'permission_key' => 'catalog.service-attribute', 'icon' => 'tags', 'order' => 10],

            // Sistema
            ['group' => 'Sistema', 'title' => 'Estados', 'description' => 'Estados genéricos reutilizables por el sistema.', 'component' => 'catalog.status', 'permission_key' => 'catalog.status', 'icon' => 'check', 'order' => 11],
            ['group' => 'Sistema', 'title' => 'Redes sociales', 'description' => 'Catálogo de redes con su URL base e ícono.', 'component' => 'catalog.social-network', 'permission_key' => 'catalog.social-network', 'icon' => 'message-circle', 'order' => 12],

            // Landing
            ['group' => 'Landing', 'title' => 'Temporadas', 'description' => 'Rangos de fechas que encienden y apagan solos lo que se muestra en esos días.', 'component' => 'catalog.seasonal-window', 'permission_key' => 'catalog.seasonal-window', 'icon' => 'calendar', 'order' => 13],
            // Support
            ['group' => 'Soporte', 'title' => 'Respuestas guardadas', 'description' => 'Las respuestas que soporte repite: se escriben una vez y se pegan en el reporte.', 'component' => 'catalog.support-reply', 'permission_key' => 'catalog.support-reply', 'icon' => 'message-circle', 'order' => 15],

            ['group' => 'Soporte', 'title' => 'Mensajes de adopción', 'description' => 'El correo que se abre a quien se quedó trabado en un paso del alta: uno por paso, se reescribe acá.', 'component' => 'catalog.adoption-nudge', 'permission_key' => 'catalog.adoption-nudge', 'icon' => 'mail', 'order' => 16],

            ['group' => 'Landing', 'title' => 'Ejemplos del hero', 'description' => 'Los negocios de ejemplo que prueba el visitante, con su versión de temporada.', 'component' => 'catalog.demo-tag', 'permission_key' => 'catalog.demo-tag', 'icon' => 'message-circle', 'order' => 14],
        ];

        foreach ($catalogs as $catalog) {
            CatalogForm::updateOrCreate(
                ['group' => $catalog['group'], 'title' => $catalog['title']],
                [...$catalog, 'is_active' => true],
            );
        }
    }
}
