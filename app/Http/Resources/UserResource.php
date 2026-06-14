<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** JSON-представление пользователя для API. */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'name' => $this->name,
            'email' => $this->email,
            'organization_id' => $this->organization_id,
            'organization_name' => $this->whenLoaded('organization', fn () => $this->organization?->name),
            'birth_date' => $this->birth_date?->toDateString(),
            'phone' => $this->phone,
            'avatar_path' => $this->avatar_path,
            'avatar_url' => $this->avatar_path
                ? Storage::disk('public')->url($this->avatar_path)
                : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'organization' => OrganizationResource::make($this->whenLoaded('organization')),
            'subjects' => SubjectResource::collection($this->whenLoaded('subjects')),
            // Все команды (текущие и прошлые); leave_date != null — пользователь вышел
            'teams' => TeamResource::collection($this->teams),
        ];
    }
}
