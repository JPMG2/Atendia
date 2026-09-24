<?php

declare(strict_types=1);

namespace App\Services;

use App\Ai\Agents\AsistenteAtendia;
use App\Interfaces\Main\AssistantSkillTool;
use App\Models\AssistantSkill;
use Laravel\Ai\Providers\Tools\ToolSearch;

/**
 * Hands the assistant its skills: the universal ones upfront, the trade's
 * own deferred behind ToolSearch so the provider loads a definition only
 * when a question calls for it — N trades without paying for all of them.
 */
class AssistantSkills
{
    /** @return list<AssistantSkillTool|ToolSearch> */
    public function for(AsistenteAtendia $assistant): array
    {
        if ($assistant->business === null) {
            return [];
        }

        // An unseeded catalog must not leave the assistant without hands:
        // every configured skill then counts as universal.
        $keys = AssistantSkill::query()->exists()
            ? AssistantSkill::keysFor($assistant->business)
            : ['universal' => array_keys((array) config('atendia.assistant.skills')), 'trade' => []];

        $universal = $this->build($keys['universal'], $assistant);
        $trade = $this->build($keys['trade'], $assistant);

        return $trade === [] ? $universal : [...$universal, new ToolSearch(tools: $trade)];
    }

    /**
     * @param  list<string>  $keys
     * @return list<AssistantSkillTool>
     */
    private function build(array $keys, AsistenteAtendia $assistant): array
    {
        return collect($keys)
            ->map(fn (string $key): ?string => config("atendia.assistant.skills.{$key}"))
            ->filter(fn (?string $class): bool => $class !== null && is_subclass_of($class, AssistantSkillTool::class))
            ->map(fn (string $class): ?AssistantSkillTool => $class::forAssistant($assistant))
            ->filter()
            ->values()
            ->all();
    }
}
