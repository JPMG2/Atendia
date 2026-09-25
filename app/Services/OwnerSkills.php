<?php

declare(strict_types=1);

namespace App\Services;

use App\Ai\Agents\AskAtendia;
use App\Interfaces\Main\OwnerSkillTool;
use App\Models\AssistantSkill;

/**
 * Hands "Ask AtendIa" its skills. Same catalog as the customer assistant's
 * (one config map, one table), filtered by audience: switching a row off
 * takes the skill away without touching code.
 */
class OwnerSkills
{
    /** @return list<OwnerSkillTool> */
    public function for(AskAtendia $assistant): array
    {
        $keys = AssistantSkill::query()->exists()
            ? AssistantSkill::ownerKeys()
            : array_keys((array) config('atendia.assistant.skills'));

        return collect($keys)
            ->map(fn (string $key): ?string => config("atendia.assistant.skills.{$key}"))
            ->filter(fn (?string $class): bool => $class !== null && is_subclass_of($class, OwnerSkillTool::class))
            ->map(fn (string $class): ?OwnerSkillTool => $class::forOwner($assistant))
            ->filter()
            ->values()
            ->all();
    }
}
