<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BusinessActivity;
use App\Models\BusinessSector;
use Illuminate\Database\Seeder;

class BusinessActivitySeeder extends Seeder
{
    /**
     * The concrete trade, grouped by sector ({@see BusinessSectorSeeder}, which
     * has to run first).
     *
     * This is the level that talks to the assistant: the tone, what it asks for
     * and the trade's seed knowledge all hang off this `code`, which is why it is
     * unique across the table. Idempotent, keyed by `code`.
     */
    public function run(): void
    {
        $activities = [
            'gastronomia' => [
                'panaderia' => 'Panadería',
                'pasteleria' => 'Pastelería',
                'restaurante' => 'Restaurante',
                'pizzeria' => 'Pizzería',
                'cafeteria' => 'Cafetería',
                'rotiseria' => 'Rotisería',
                'heladeria' => 'Heladería',
                'bar' => 'Bar',
                'catering' => 'Catering',
            ],
            'salud' => [
                'farmacia' => 'Farmacia',
                'consultorio-medico' => 'Consultorio médico',
                'odontologia' => 'Odontología',
                'kinesiologia' => 'Kinesiología',
                'psicologia' => 'Psicología',
                'nutricion' => 'Nutrición',
                'laboratorio' => 'Laboratorio de análisis',
                'optica' => 'Óptica',
            ],
            'belleza' => [
                'peluqueria' => 'Peluquería',
                'barberia' => 'Barbería',
                'manicuria' => 'Manicuría',
                'centro-estetico' => 'Centro de estética',
                'depilacion' => 'Depilación',
                'spa' => 'Spa',
                'tatuajes' => 'Tatuajes y piercing',
            ],
            'comercio' => [
                'kiosco' => 'Kiosco',
                'almacen' => 'Almacén',
                'supermercado' => 'Supermercado',
                'dietetica' => 'Dietética',
                'tienda-ropa' => 'Tienda de ropa',
                'libreria' => 'Librería',
                'ferreteria' => 'Ferretería',
                'electronica' => 'Electrónica',
                'floreria' => 'Florería',
            ],
            'servicios' => [
                'lavanderia' => 'Lavandería',
                'cerrajeria' => 'Cerrajería',
                'limpieza' => 'Servicio de limpieza',
                'mudanzas' => 'Mudanzas y fletes',
                'imprenta' => 'Imprenta',
                'fotografia' => 'Fotografía',
                'eventos' => 'Organización de eventos',
                'reparacion-electrodomesticos' => 'Reparación de electrodomésticos',
            ],
            'profesionales' => [
                'estudio-contable' => 'Estudio contable',
                'estudio-juridico' => 'Estudio jurídico',
                'arquitectura' => 'Arquitectura',
                'inmobiliaria' => 'Inmobiliaria',
                'seguros' => 'Seguros',
                'agencia-marketing' => 'Agencia de marketing',
                'desarrollo-software' => 'Desarrollo de software',
            ],
            'bienestar' => [
                'gimnasio' => 'Gimnasio',
                'yoga' => 'Estudio de yoga',
                'pilates' => 'Pilates',
                'natacion' => 'Natación',
                'entrenador-personal' => 'Entrenador personal',
                'alquiler-canchas' => 'Alquiler de canchas',
            ],
            'mascotas' => [
                'veterinaria' => 'Veterinaria',
                'peluqueria-canina' => 'Peluquería canina',
                'petshop' => 'Pet shop',
                'guarderia-mascotas' => 'Guardería de mascotas',
            ],
            'automotor' => [
                'taller-mecanico' => 'Taller mecánico',
                'lavadero-autos' => 'Lavadero de autos',
                'gomeria' => 'Gomería',
                'repuestos' => 'Repuestos',
                'concesionaria' => 'Concesionaria',
            ],
            'educacion' => [
                'apoyo-escolar' => 'Apoyo escolar',
                'instituto-idiomas' => 'Instituto de idiomas',
                'academia-musica' => 'Academia de música',
                'jardin-infantes' => 'Jardín de infantes',
                'autoescuela' => 'Autoescuela',
            ],
        ];

        foreach ($activities as $sectorCode => $names) {
            $sector = BusinessSector::query()->where('code', $sectorCode)->sole();
            $order = 0;

            foreach ($names as $code => $name) {
                $order++;

                BusinessActivity::query()->firstOrCreate(
                    ['code' => $code],
                    [
                        'business_sector_id' => $sector->id,
                        'code' => $code,
                        'name' => $name,
                        'sort_order' => $order,
                    ],
                );
            }
        }
    }
}
