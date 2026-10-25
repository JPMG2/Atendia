<?php

declare(strict_types=1);

namespace App\Interfaces\Main;

use App\Ai\Agents\AskAdmin;
use Laravel\Ai\Contracts\Tool;

/**
 * A skill of the admin's panel assistant. It reads across every business, so
 * the lock is the agent's: it is only ever built for a user who may ask. A
 * business is always named by the tool's own argument and read back from the
 * database, never trusted from the model's text.
 */
interface AdminSkillTool extends Tool
{
    public static function forAdmin(AskAdmin $assistant): ?static;
}
