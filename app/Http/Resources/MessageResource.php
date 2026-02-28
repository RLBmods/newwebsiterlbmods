<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'message' => $this->message,
            'created_at' => $this->created_at,
            // Add user relationship fields if necessary, e.g. role or profile_picture
            // For now, we'll stick to the basic fields from the messages table
        ];
    }
}
