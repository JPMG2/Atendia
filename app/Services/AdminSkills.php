<?php

declare(strict_types=1);

namespace App\Services;

use App\Ai\Agents\AskAdmin;
use App\Interfaces\Main\AdminSkillTool;
use App\Models\AssistantSkill;

/**
 * Hands the admin's assistant its tools. Same catalog as the other two
 * assistants (one config map, one table) filtered by audience: switching a
 * row off takes the skill away from the admin without touching code.
 */
class AdminSkills
{
    /** @return list<AdminSkillTool> */
    public function for(AskAdmin $assistant): array
    {
        $keys = AssistantSkill::query()->exists()
            ? AssistantSkill::adminKeys()
            : array_keys((array) config('atendia.assistant.skills'));

        return collect($keys)
            ->map(fn (string $key): ?string => config("atendia.assistant.skills.{$key}"))
            ->filter(fn (?string $class): bool => $class !== null && is_subclass_of($class, AdminSkillTool::class))
            ->map(fn (string $class): ?AdminSkillTool => $class::forAdmin($assistant))
            ->filter()
            ->values()
            ->all();
    }
}
