<?php

declare(strict_types=1);

namespace App\Interfaces\Main;

use App\Ai\Agents\AsistenteAtendia;
use Laravel\Ai\Contracts\Tool;

/**
 * A tool the assistant can be given as a skill. It builds itself from the
 * assistant's own context and returns null when that context lacks what it
 * needs (no conversation, no customer), so the registry never special-cases.
 */
interface AssistantSkillTool extends Tool
{
    public static function forAssistant(AsistenteAtendia $assistant): ?static;
}
