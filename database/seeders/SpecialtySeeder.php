<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BusinessActivity;
use App\Models\Specialty;
use Illuminate\Database\Seeder;

class SpecialtySeeder extends Seeder
{
    /**
     * What a business marks inside its activity: the specialties without services
     * of their own (the ones that have them are activities) and the cuisines.
     * Idempotent AND the curated source for names and order, like its siblings;
     * `is_active` is never touched, so a switch-off in the master survives a re-run.
     * Run after {@see BusinessActivitySeeder}.
     */
    public function run(): void
    {
        foreach ($this->specialties() as $activityCode => $specialties) {
            $activity = BusinessActivity::query()->where('code', $activityCode)->sole();

            // Only on birth: a re-run must not undo a label she renamed in the master.
            if ($activity->specialty_label === null) {
                $activity->forceFill(['specialty_label' => $this->labels()[$activityCode]])->saveQuietly();
            }

            $order = 0;

            foreach ($specialties as $code => $name) {
                Specialty::query()->updateOrCreate(
                    ['code' => $code],
                    ['business_activity_id' => $activity->id, 'name' => $name, 'sort_order' => ++$order],
                );
            }
        }
    }

    /**
     * @return array<string, string> activity code => how the field is called for it
     */
    private function labels(): array
    {
        return [
            'consultorio-medico' => 'Especialidad',
            'restaurante' => 'Tipo de cocina',
        ];
    }

    /**
     * @return array<string, array<string, string>> activity code => [code => name]
     */
    private function specialties(): array
    {
        return [
            'consultorio-medico' => [
                'medicina-general' => 'Medicina general',
                'medicina-interna' => 'Medicina interna',
                'alergologia' => 'Alergología',
                'andrologia' => 'Andrología',
                'angiologia' => 'Angiología',
                'gastroenterologia' => 'Gastroenterología',
                'geriatria' => 'Geriatría',
                'hepatologia' => 'Hepatología',
                'infectologia' => 'Infectología',
                'inmunologia' => 'Inmunología',
                'mastologia' => 'Mastología',
                'hematologia' => 'Hematología',
                'oncologia' => 'Oncología',
                'patologia' => 'Patología',
                'nefrologia' => 'Nefrología',
                'neumonologia' => 'Neumonología',
                'neurologia' => 'Neurología',
                'neurocirugia' => 'Neurocirugía',
                'neuropediatria' => 'Neuropediatría',
                'proctologia' => 'Proctología',
                'reumatologia' => 'Reumatología',
                'sexologia' => 'Sexología',
                'fertilidad' => 'Fertilidad',
                'anestesiologia' => 'Anestesiología',
                'medicina-antienvejecimiento' => 'Medicina antienvejecimiento',
                'ozonoterapia' => 'Ozonoterapia',
                'trastornos-del-sueno' => 'Trastornos del sueño',
                'terapia-del-dolor' => 'Terapia del dolor',
                'toxicologia' => 'Toxicología',
                'estomatologia' => 'Estomatología',
            ],

            'restaurante' => [
                'cocina-china' => 'Cocina china',
                'cocina-japonesa' => 'Cocina japonesa y sushi',
                'cocina-tailandesa' => 'Cocina tailandesa',
                'cocina-arabe' => 'Cocina árabe',
                'cocina-italiana' => 'Cocina italiana',
                'cocina-espanola' => 'Cocina española y paella',
                'cocina-francesa' => 'Cocina francesa',
                'cocina-alemana' => 'Cocina alemana',
                'cocina-mexicana' => 'Cocina mexicana',
                'cocina-peruana' => 'Cocina peruana',
                'cocina-mediterranea' => 'Cocina mediterránea',
                'cocina-fusion' => 'Cocina de fusión',
                'cocina-criolla' => 'Cocina criolla',
                'cocina-de-carnes' => 'Cocina de carnes',
                'cocina-de-pescados' => 'Cocina de pescados y mariscos',
                'cocina-vegetariana' => 'Cocina vegetariana',
                'cocina-gourmet' => 'Cocina gourmet',
                'cocina-campestre' => 'Cocina campestre',
                'cocina-de-playa' => 'Cocina de playa',
            ],
        ];
    }
}
