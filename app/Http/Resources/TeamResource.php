<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON-представление команды.
 * join_date / leave_date / is_leader — из pivot team_user (при выводе через UserResource).
 */
class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_active' => $this->is_active,

            'join_date' => $this->when($this->pivot, fn () => $this->pivot->join_date),
            'leave_date' => $this->when($this->pivot, fn () => $this->pivot->leave_date),
            'is_leader' => $this->when($this->pivot, fn () => (bool) $this->pivot->is_leader),

            'users' => $this->whenLoaded('users'),
            'projects' => $this->whenLoaded('projects'),
        ];
    }
}
