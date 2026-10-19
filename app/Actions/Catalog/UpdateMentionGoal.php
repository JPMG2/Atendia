<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\MentionGoal;

class UpdateMentionGoal
{
    /**
     * @param  array{threshold: int, caption: string, is_active: bool}  $data  Already validated by MentionGoalForm.
     */
    public function handle(int $id, array $data): MentionGoal
    {
        $record = MentionGoal::query()->findOrFail($id);
        $record->update($data);

        return $record;
    }
}
