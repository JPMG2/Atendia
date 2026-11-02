<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Actions\Admin\PurgeBusiness;
use App\Models\Business;
use App\Models\User;
use App\Services\EvolutionApi;
use App\Services\PlatformWhatsApp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;

/**
 * The real deletion Google Play requires: once the restore window of an account
 * its owner closed is over, the account, its business and everything in it go
 * for good. The payments and their receipts stay (the law asks to keep them), and
 * a suspended business is held back: its evidence protects the people it reached.
 */
class EraseClosedAccount
{
    public function __construct(
        private readonly PurgeBusiness $purge,
        private readonly EvolutionApi $evolution,
    ) {}

    /**
     * @return bool whether the account was erased
     *
     * @throws ConnectionException|RequestException when the bridge cannot drop the instance: nothing is erased, tomorrow retries
     */
    public function handle(User $user): bool
    {
        $business = $user->business_id === null ? null : Business::withTrashed()->find($user->business_id);

        if ($business?->suspended_at !== null) {
            return false;
        }

        if ($business !== null) {
            // The linked phone's session and whatever the bridge keeps of its chats go first.
            $instance = $business->whatsapp_instance;

            if ($instance !== null && ! PlatformWhatsApp::owns($instance)) {
                $this->evolution->logout($instance);
                $this->evolution->deleteInstance($instance);
            }

            $this->purge->handle($business, keepMoney: true);

            return true;
        }

        $avatar = $user->getAttributes()['avatar_path'] ?? null;

        DB::transaction(function () use ($user): void {
            $morph = $user->getMorphClass();

            foreach (['login_devices', 'login_activities', 'sessions'] as $table) {
                DB::table($table)->where('user_id', $user->id)->delete();
            }

            DB::table('personal_access_tokens')->where('tokenable_type', $morph)->where('tokenable_id', $user->id)->delete();
            DB::table('model_has_roles')->where('model_type', $morph)->where('model_id', $user->id)->delete();
            DB::table('model_has_permissions')->where('model_type', $morph)->where('model_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            DB::table('activity_log')->where('causer_type', $morph)->where('causer_id', $user->id)->delete();
            DB::table('activity_log')->where('subject_type', $morph)->where('subject_id', $user->id)->delete();
            $user->forceDelete();
        });

        PurgeBusiness::eraseAvatars($avatar === null ? [] : [$avatar]);

        return true;
    }
}
