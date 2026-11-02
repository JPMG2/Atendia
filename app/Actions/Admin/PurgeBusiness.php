<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Business;
use App\Models\SocialLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Erases a removed business for good: the test businesses nobody will bring
 * back, and every closed account once its grace period is over (Google Play
 * requires a real deletion, not a deactivation). Only a removed business can
 * be erased, and the money records survive whenever there are any.
 */
class PurgeBusiness
{
    /** The money records the law makes us keep: never erased, even when the owner asks. */
    public const array MONEY_TABLES = ['payments', 'subscriptions'];

    /**
     * What a kept business row still says once its owner asked to be erased: only
     * what the money records need (who was invoiced and under which tax id).
     */
    private const array FISCAL_COLUMNS = ['id', 'name', 'country_id', 'currency_id', 'tax_condition_id', 'tax_id', 'is_active', 'is_demo', 'handoff_level', 'appointments_enabled', 'appointment_capacity', 'appointment_slot_minutes', 'created_at', 'updated_at', 'deleted_at', 'billing_email'];

    /**
     * `$keepMoney` is the erase an owner asked for (Google Play's account deletion): everything
     * goes except the payments and the subscription, kept as accounting records with the
     * business's fiscal name and tax id. Without it, a business that ever paid is refused.
     *
     * @return array<string, int> rows erased per table
     *
     * @throws RuntimeException when the business is not removed yet, or ever paid and money is not kept
     */
    public function handle(Business $business, bool $keepMoney = false): array
    {
        if (! $business->trashed()) {
            throw new RuntimeException('Remove the business first; only a removed one can be erased.');
        }

        $id = $business->id;
        $paid = DB::table('payments')->where('business_id', $id)->exists();

        if ($paid && ! $keepMoney) {
            throw new RuntimeException('This business has payments on record; they are never erased.');
        }

        $userIds = DB::table('users')->where('business_id', $id)->pluck('id')->all();
        $contactIds = DB::table('customers')->where('business_id', $id)->whereNotNull('platform_contact_id')->pluck('platform_contact_id')->unique()->all();
        $erased = [];

        $keep = $paid ? self::MONEY_TABLES : [];

        $avatars = DB::table('users')->whereIn('id', $userIds)->whereNotNull('avatar_path')->pluck('avatar_path')->all();

        DB::transaction(function () use ($id, $userIds, $contactIds, $keep, &$erased): void {
            $this->eraseUserTraces($userIds);
            $this->eraseActivity($id, $userIds);

            $morph = (new Business)->getMorphClass();
            DB::table('social_links')->where('linkable_type', $morph)->where('linkable_id', $id)->delete();

            $erased = $this->eraseTenantRows($id, $keep);

            // The board's feed has no business_id and its key to the business is nulled when the row goes: erase first.
            $events = DB::table('platform_events')->where('about_business_id', $id)->delete();

            if ($events > 0) {
                $erased['platform_events'] = $events;
            }

            DB::table('users')->whereIn('id', $userIds)->delete();

            // Kept payments need their business row: it stays with only its fiscal identity.
            if ($keep === []) {
                DB::table('businesses')->where('id', $id)->delete();
            } else {
                $this->keepOnlyFiscal($id);
            }

            $contacts = $this->settleContacts($contactIds);

            if ($contacts > 0) {
                $erased['platform_contacts'] = $contacts;
            }
        });

        foreach (['public', 'local'] as $disk) {
            $this->eraseFiles($disk, "businesses/{$id}", $keep === [] ? null : "businesses/{$id}/payments");
        }

        self::eraseAvatars($avatars);
        Storage::disk('local')->deleteDirectory('imports/business-'.$id);

        return $erased;
    }

    /** @param  list<string>  $paths */
    public static function eraseAvatars(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk('public')->delete($paths);
        }
    }

    /**
     * The audit trail keeps names, prices and links in its changes: every line this
     * business's people caused, and every line about a row of this business, goes.
     *
     * @param  list<int>  $userIds
     */
    private function eraseActivity(int $businessId, array $userIds): void
    {
        $userMorph = (new User)->getMorphClass();
        DB::table('activity_log')->where('causer_type', $userMorph)->whereIn('causer_id', $userIds)->delete();
        DB::table('activity_log')->where('subject_type', $userMorph)->whereIn('subject_id', $userIds)->delete();

        $businessMorph = (new Business)->getMorphClass();
        DB::table('activity_log')->where('subject_type', $businessMorph)->where('subject_id', $businessId)->delete();
        DB::table('activity_log')->where('subject_type', (new SocialLink)->getMorphClass())
            ->whereIn('subject_id', DB::table('social_links')->where('linkable_type', $businessMorph)->where('linkable_id', $businessId)->select('id'))
            ->delete();

        foreach (DB::table('activity_log')->distinct()->pluck('subject_type') as $type) {
            $class = Relation::getMorphedModel((string) $type) ?? $type;

            if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $table = (new $class)->getTable();

            if (Schema::hasColumn($table, 'business_id')) {
                DB::table('activity_log')->where('subject_type', $type)
                    ->whereIn('subject_id', DB::table($table)->where('business_id', $businessId)->select('id'))
                    ->delete();
            }
        }
    }

    /** Every file of the business, except the payment receipts when the money is kept. */
    private function eraseFiles(string $disk, string $root, ?string $kept): void
    {
        $storage = Storage::disk($disk);

        if ($kept === null) {
            $storage->deleteDirectory($root);

            return;
        }

        foreach ($storage->directories($root) as $directory) {
            if ($directory !== $kept) {
                $storage->deleteDirectory($directory);
            }
        }

        $storage->delete($storage->files($root));
    }

    /** Wipes every column of the business row that is not its fiscal identity. */
    private function keepOnlyFiscal(int $id): void
    {
        $nullable = collect(DB::select(
            "select column_name from information_schema.columns where table_name = 'businesses' and table_schema = current_schema() and is_nullable = 'YES'"
        ))->pluck('column_name')->diff(self::FISCAL_COLUMNS);

        DB::table('businesses')->where('id', $id)->update([
            ...$nullable->mapWithKeys(fn (string $column): array => [$column => null])->all(),
            // Not nullable: an address that answers nothing, so no mail ever goes to the erased owner.
            'billing_email' => 'erased-'.$id.Business::ERASED_EMAIL_DOMAIN,
            'is_active' => false,
        ]);

        // Kept as a record, ended for good: no cycle ever bills or chases an erased business.
        DB::table('subscriptions')->where('business_id', $id)->update([
            'canceled_at' => DB::raw('coalesce(canceled_at, now())'),
            'current_period_ends_at' => now(),
        ]);
    }

    /**
     * The radar's people carry counters, not a business key: a person who only
     * ever wrote to the erased business would stay listed as a client of no
     * one. Recount the ones who wrote elsewhere; erase the rest.
     *
     * @param  list<int>  $contactIds
     * @return int people erased
     */
    private function settleContacts(array $contactIds): int
    {
        $erased = 0;

        foreach ($contactIds as $contactId) {
            $customers = DB::table('customers')->where('platform_contact_id', $contactId);

            if (! $customers->exists()) {
                $erased += DB::table('platform_contacts')->where('id', $contactId)->delete();

                continue;
            }

            DB::table('platform_contacts')->where('id', $contactId)->update([
                'businesses_count' => (clone $customers)->distinct()->count('business_id'),
                'conversations_count' => (int) (clone $customers)->sum('conversations_count'),
            ]);
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
        DB::table('model_has_permissions')->where('model_type', $morph)->whereIn('model_id', $userIds)->delete();
        DB::table('password_reset_tokens')->whereIn('email', DB::table('users')->whereIn('id', $userIds)->select('email'))->delete();
    }

    /**
     * Foreign keys decide the order, so a table that still has children is
     * retried after the others; one that never frees up aborts the lot.
     *
     * @param  list<string>  $keep  tables left untouched (the money records, when kept)
     * @return array<string, int>
     */
    private function eraseTenantRows(int $businessId, array $keep = []): array
    {
        $pending = collect(DB::select(
            "select table_name from information_schema.columns where column_name = 'business_id' and table_schema = current_schema()"
        ))->pluck('table_name')->reject(fn (string $table): bool => in_array($table, ['users', 'businesses', ...$keep], true))->values()->all();

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
