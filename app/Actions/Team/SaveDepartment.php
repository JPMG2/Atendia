<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\Business;
use App\Models\Department;

class SaveDepartment
{
    /** @param  array{name: string, routing_hint: string, uses_business_hours: bool, week: array<int, array{open: bool, opens: string, closes: string}>, user_ids: list<int>}  $data  Already validated. */
    public function handle(Business $business, array $data, ?int $id = null): Department
    {
        $department = $id === null
            ? new Department(['sort_order' => (int) $business->departments()->max('sort_order') + 1])
            : $business->departments()->findOrFail($id);

        $department->fill([
            'name' => $data['name'],
            'routing_hint' => $data['routing_hint'],
            'hours' => $data['uses_business_hours'] ? null : $this->storedHours($data['week']),
        ]);
        $department->business()->associate($business)->save();

        // Only this business's people can staff its departments.
        $department->users()->sync($business->users()->whereKey($data['user_ids'])->pluck('id'));

        return $department;
    }

    /**
     * @param  array<int, array{open: bool, opens: string, closes: string}>  $week
     * @return array<string, array{0: string, 1: string}>
     */
    private function storedHours(array $week): array
    {
        return collect($week)
            ->filter(fn (array $row): bool => $row['open'])
            ->mapWithKeys(fn (array $row, int $day): array => [(string) $day => [$row['opens'], $row['closes']]])
            ->all();
    }
}
