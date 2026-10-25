<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Erases a removed business for good, for the test businesses nobody will
 * ever bring back: the soft delete keeps the e-mail and the row, so the same
 * person cannot sign up again. Real customers keep the rule "the information
 * is money, it stays": a business that ever paid is refused, and one that is
 * still active must be removed first, so it takes two deliberate steps.
 */
class PurgeBusiness
{
    /**
     * @return array<string, int> rows erased per table
     *
     * @throws RuntimeException when the business is not removed yet, or ever paid
     */
    public function handle(Business $business): array
    {
        if (! $business->trashed()) {
            throw new RuntimeException('Remove the business first; only a removed one can be erased.');
        }

        $id = $business->id;

        if (DB::table('payments')->where('business_id', $id)->exists()) {
            throw new RuntimeException('This business has payments on record; they are never erased.');
        }

        $userIds = DB::table('users')->where('business_id', $id)->pluck('id')->all();
        $erased = [];

        DB::transaction(function () use ($id, $userIds, &$erased): void {
            $this->eraseUserTraces($userIds);

            $morph = (new Business)->getMorphClass();
            DB::table('social_links')->where('linkable_type', $morph)->where('linkable_id', $id)->delete();
            DB::table('activity_log')->where('subject_type', $morph)->where('subject_id', $id)->delete();

            $erased = $this->eraseTenantRows($id);

            // The board's feed has no business_id and its key to the business is nulled when the row goes: erase first.
            $events = DB::table('platform_events')->where('about_business_id', $id)->delete();

            if ($events > 0) {
                $erased['platform_events'] = $events;
            }

            DB::table('users')->whereIn('id', $userIds)->delete();
            DB::table('businesses')->where('id', $id)->delete();
        });

        foreach (['public', 'local'] as $disk) {
            Storage::disk($disk)->deleteDirectory("businesses/{$id}");
        }

        return $erased;
    }

    /**
     * @param  list<int>  $userIds
     */
    private function eraseUserTraces(array $userIds): void
    {
        $morph = (new User)->getMorphClass();

        foreach (['login_devices', 'login_activities', 'sessions'] as $table) {
            DB::table($table)->whereIn('user_id', $userIds)->delete();
        }

        DB::table('personal_access_tokens')->where('tokenable_type', $morph)->whereIn('tokenable_id', $userIds)->delete();
        DB::table('model_has_roles')->where('model_type', $morph)->whereIn('model_id', $userIds)->delete();
    }

    /**
     * Foreign keys decide the order, so a table that still has children is
     * retried after the others; one that never frees up aborts the lot.
     *
     * @return array<string, int>
     */
    private function eraseTenantRows(int $businessId): array
    {
        $pending = collect(DB::select(
            "select table_name from information_schema.columns where column_name = 'business_id' and table_schema = current_schema()"
        ))->pluck('table_name')->reject(fn (string $table): bool => in_array($table, ['users', 'businesses'], true))->all();

        $erased = [];

        for ($pass = 0; $pass < 8 && $pending !== []; $pass++) {
            $blocked = [];

            foreach ($pending as $table) {
                DB::statement('savepoint purge_table');

                try {
                    $count = DB::table($table)->where('business_id', $businessId)->delete();
                    DB::statement('release savepoint purge_table');

                    if ($count > 0) {
                        $erased[$table] = $count;
                    }
                } catch (Throwable) {
                    DB::statement('rollback to savepoint purge_table');
                    $blocked[] = $table;
                }
            }

            $pending = $blocked;
        }

        if ($pending !== []) {
            throw new RuntimeException('Could not erase: '.implode(', ', $pending));
        }

        return $erased;
    }
}
