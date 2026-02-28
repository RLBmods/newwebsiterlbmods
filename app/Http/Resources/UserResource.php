<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'discord_id' => $this->discord_id,
            'role' => $this->role,
            'balance' => (float) $this->balance,
            'avatar' => $this->avatar ?? '/images/avatars/default.png',
            'last_login_at' => $this->last_login_at,
            'last_activity_at' => $this->last_activity_at,
            'created_at' => $this->created_at,
        ];
    }
}
