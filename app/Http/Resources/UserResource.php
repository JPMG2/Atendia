<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Classes\Main\AskDesk;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // The platform's team has no business on the phone, even an account that also owns one.
        $staff = $this->isStaff();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'business' => $staff ? null : $this->business?->name,
            // Which side of the app this person is on: the platform's team gets the Admin, a customer gets the business.
            'staff' => $staff,
            // What the phone needs to dial the socket: the public app key (it
            // ships in the web bundle too) and the one channel it may ask for.
            'realtime' => $staff || $this->business_id === null ? null : [
                'key' => config('broadcasting.connections.reverb.key'),
                'channel' => 'business.'.$this->business_id,
            ],
            // The bar's last tab, with its word, only for the owner of a plan that opens it: Emprende draws no icon.
            'ask' => ! $staff && $this->askDesk()->mayAsk && $this->askDesk()->plan->allowsAsk ? ['tab' => __('ask.tab')] : null,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            // Settings > Profile as the web says it: its labels, and the date on the business's own clock.
            'profile' => [
                'name_label' => __('settings.profile.name'),
                'member_since_label' => __('settings.profile.member_since'),
                'member_since' => $this->created_at?->toImmutable()->setTimezone($this->business?->localTimezone() ?? Company::localTimezone())->format('d/m/Y'),
                // The web's Inicio head: the greeting never changes with the hour, and the sub says whether a first thread has come.
                'home' => [
                    'greeting' => __('client.home.greeting', ['name' => $this->name]),
                    'sub' => ($this->business?->conversations()->exists() ?? false) ? __('client.home.sub_active') : __('client.home.sub_new'),
                    'disconnected' => __('client.home.disconnected'),
                ],
                'password' => [
                    'title' => __('settings.password.title'),
                    'sub' => __('settings.password.sub'),
                    'current' => __('settings.current_password'),
                    'new' => __('settings.password.new'),
                    'confirm' => __('settings.password.confirm'),
                    'logout_others' => __('settings.password.logout_others'),
                    'notice' => __('settings.password.notice'),
                    'submit' => __('settings.password.submit'),
                ],
            ],
        ];
    }

    private ?AskDesk $desk = null;

    private function askDesk(): AskDesk
    {
        return $this->desk ??= new AskDesk($this->resource->business, $this->resource);
    }
}
