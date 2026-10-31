<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Business;
use App\Models\Company;
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

    /** @var array<int, string> business id => its clock, resolved once per request */
    private array $timezones = [];

    /** @var array<int|string, bool> user id => whether they are platform staff, asked once per request */
    private array $staff = [];

    /** The current business's clock, for showing times the way its owner lives them. */
    public function timezone(): string
    {
        $id = $this->id();

        // No business means the admin side: it lives on the platform's own clock, not on UTC.
        if ($id === null) {
            return Company::localTimezone();
        }

        return $this->timezones[$id] ??= Business::query()->find($id)?->localTimezone() ?? (string) config('app.timezone');
    }

    public function id(): ?int
    {
        if ($this->overridden) {
            return $this->businessId;
        }

        return auth()->user()?->business_id;
    }

    /**
     * With no business, who may still see every business's rows: the platform's
     * staff, a job or the console (nobody signed in, or "act as admin" set by
     * hand). A signed-in client who has no business yet sees NONE: having no
     * business is not being the admin.
     */
    public function unrestricted(): bool
    {
        if ($this->overridden) {
            return $this->businessId === null;
        }

        $user = auth()->user();

        if ($user === null) {
            return true;
        }

        return $user->business_id === null
            && ($this->staff[$user->getAuthIdentifier()] ??= $user->can('access-admin-panel'));
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
     * itself. Empty means "no tenant" (staff, console, seeders) and opens them;
     * a client with no business gets `0`, an id nobody has, and they close.
     */
    public function syncDatabase(): void
    {
        $id = $this->id();

        DB::selectOne('select set_config(?, ?, false)', ['app.current_tenant', $id !== null ? (string) $id : ($this->unrestricted() ? '' : '0')]);
    }

    /**
     * The business's rows AND its voice: a worker writing to its customers
     * has no visitor session, and would otherwise speak the neutral default.
     */
    public function speakingAs(Business $business, Closure $callback): mixed
    {
        return $this->for((int) $business->id, fn (): mixed => $business->speaking($callback));
    }

    /**
     * Runs the callback in a business context and restores the previous one even
     * if it blows up. For jobs: `Tenant::for($id, fn () => ...)`.
     */
    public function for(?int $businessId, Closure $callback): mixed
    {
        $previousId = $this->businessId;
        $previousOverridden = $this->overridden;
        // The database's own value, not one worked out again: a business born inside a request (its trial
        // subscription) must leave Postgres exactly as it found it.
        $previousFence = (string) DB::selectOne("select current_setting('app.current_tenant', true) as tenant")?->tenant;

        $this->set($businessId);

        try {
            return $callback();
        } finally {
            $this->businessId = $previousId;
            $this->overridden = $previousOverridden;

            DB::selectOne('select set_config(?, ?, false)', ['app.current_tenant', $previousFence]);
        }
    }
}
