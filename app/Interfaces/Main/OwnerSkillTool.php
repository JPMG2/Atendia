<?php

declare(strict_types=1);

namespace App\Interfaces\Main;

use App\Ai\Agents\AskAtendia;
use Laravel\Ai\Contracts\Tool;

/**
 * A skill of "Ask AtendIa", the owner's panel assistant. Read-only by
 * contract: it answers about the business, it never changes it. The
 * business comes from the agent, never from an argument the model writes.
 */
interface OwnerSkillTool extends Tool
{
    public static function forOwner(AskAtendia $assistant): ?static;
}
