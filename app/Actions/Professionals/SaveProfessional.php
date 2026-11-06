<?php

declare(strict_types=1);

namespace App\Actions\Professionals;

use App\Classes\Main\OpeningHours;
use App\Models\Business;
use App\Models\Professional;
use App\Models\ProfessionalHour;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SaveProfessional
{
    /**
     * @param  array{name: string, is_active: bool, uses_business_hours: bool, week: array<int, array{open: bool, opens_at: string, closes_at: string}>, appointment_capacity: ?string, appointments_per_day: ?string, services: array<int, array{duration_minutes: ?string, price_type: ?string, price: ?string, deposit: ?string}>}  $data
     */
    public function handle(Business $business, array $data, ?int $id = null): Professional
    {
        // Editing keeps its place; only a new one needs room under the plan.
        if ($id === null && $business->professionals()->count() >= $business->plan()->professionalsCap) {
            throw new RuntimeException('The plan has no room for another professional.');
        }

        // All or nothing: the person, their week and their services are one decision.
        return DB::transaction(function () use ($business, $data, $id): Professional {
            $professional = $id === null
                ? new Professional(['sort_order' => (int) $business->professionals()->withTrashed()->max('sort_order') + 1])
                : $business->professionals()->findOrFail($id);

            $professional->fill([
                'name' => $data['name'],
                'is_active' => $data['is_active'],
                'appointment_capacity' => $this->number($data['appointment_capacity']),
                'appointments_per_day' => $this->number($data['appointments_per_day']),
            ]);
            $professional->business()->associate($business)->save();

            $this->replaceHours($business, $professional, $data['uses_business_hours'] ? [] : OpeningHours::toStored($data['week']));
            $this->replaceServices($business, $professional, $data['services']);

            return $professional;
        });
    }

    /**
     * The week replaces whole, like the business's own: shifts have no identity
     * worth reconciling. No shifts at all means they follow the business.
     *
     * @param  array<int, array{0: string, 1: string}>  $stored
     */
    private function replaceHours(Business $business, Professional $professional, array $stored): void
    {
        $professional->hours()->delete();

        foreach ($stored as $day => [$opens, $closes]) {
            $hour = new ProfessionalHour(['day_of_week' => $day, 'opens_at' => $opens, 'closes_at' => $closes]);
            $hour->business()->associate($business);
            $hour->professional()->associate($professional)->save();
        }

        $professional->unsetRelation('hours');
    }

    /**
     * Only this business's services that take a booking can be attended: an id
     * from anywhere else in the request reaches nothing.
     *
     * @param  array<int, array{duration_minutes: ?string, price_type: ?string, price: ?string, deposit: ?string}>  $services
     */
    private function replaceServices(Business $business, Professional $professional, array $services): void
    {
        $allowed = $business->services()->where('is_bookable', true)->whereKey(array_keys($services))->pluck('id');

        $professional->services()->sync($allowed->mapWithKeys(fn (int $serviceId): array => [$serviceId => [
            'duration_minutes' => $this->number($services[$serviceId]['duration_minutes']),
            'price_type' => $services[$serviceId]['price_type'],
            'price' => $services[$serviceId]['price'],
            'deposit' => $services[$serviceId]['deposit'],
        ]])->all());

        $professional->unsetRelation('services');
    }

    private function number(?string $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
