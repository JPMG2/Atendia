<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Which business is the current one.
 *
 * It comes from the logged-in user, and can be set by hand where there is no
 * session — queues, commands, seeders — which is exactly where isolation
 * breaks if nobody remembers. A container singleton and not static state, so
 * a business set in one test does not leak into the next.
 */
class Tenant
{
    private ?int $businessId = null;

    /**
     * An id set by hand beats the logged-in user. The flag is needed because
     * setting `null` is a valid order ("act as admin"), which is not the same
     * as never having set anything.
     */
    private bool $overridden = false;

    public function id(): ?int
    {
        if ($this->overridden) {
            return $this->businessId;
        }

        return auth()->user()?->business_id;
    }

    public function set(?int $businessId): void
    {
        $this->businessId = $businessId;
        $this->overridden = true;

        $this->syncDatabase();
    }

    public function forget(): void
    {
        $this->businessId = null;
        $this->overridden = false;

        $this->syncDatabase();
    }

    /**
     * Pushes the current tenant into the DATABASE session, where the RLS
     * policies read it: from there even raw SQL is fenced by Postgres
     * itself. Empty string means "no tenant" — admin, console, seeders —
     * and the policies open up, mirroring the Eloquent scope.
     */
    public function syncDatabase(): void
    {
        DB::selectOne('select set_config(?, ?, false)', ['app.current_tenant', (string) $this->id()]);
    }

    /**
     * Runs the callback in a business context and restores the previous one even
     * if it blows up. For jobs: `Tenant::for($id, fn () => ...)`.
     */
    public function for(?int $businessId, Closure $callback): mixed
    {
        $previousId = $this->businessId;
        $previousOverridden = $this->overridden;

        $this->set($businessId);

        try {
            return $callback();
        } finally {
            $this->businessId = $previousId;
            $this->overridden = $previousOverridden;

            $this->syncDatabase();
        }
    }
}
