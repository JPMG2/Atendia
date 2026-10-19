<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\MentionGoal;

class CreateMentionGoal
{
    /**
     * @param  array{threshold: int, caption: string, is_active: bool}  $data  Already validated by MentionGoalForm.
     */
    public function handle(array $data): MentionGoal
    {
        return MentionGoal::query()->create($data);
    }
}
