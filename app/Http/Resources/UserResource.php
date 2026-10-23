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
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'business' => $this->business?->name,
            // What the phone needs to dial the socket: the public app key (it
            // ships in the web bundle too) and the one channel it may ask for.
            'realtime' => $this->business_id === null ? null : [
                'key' => config('broadcasting.connections.reverb.key'),
                'channel' => 'business.'.$this->business_id,
            ],
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
        ];
    }
}
