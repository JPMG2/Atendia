<?php

declare(strict_types=1);

namespace App\Http\Resources;

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
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
        ];
    }
}
