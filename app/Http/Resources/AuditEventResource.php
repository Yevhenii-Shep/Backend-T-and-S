<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** JSON-представление аудита проекта. */
class AuditEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'project_name' => $this->whenLoaded('project', fn () => $this->project?->name),
            'result' => $this->result,
            'main_auditor_id' => $this->main_auditor,
            'main_auditor_name' => $this->whenLoaded('mainAuditor', fn () => $this->mainAuditor?->name),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'project' => ProjectResource::make($this->whenLoaded('project')),
            'main_auditor' => UserResource::make($this->whenLoaded('mainAuditor')),
            'participants' => AuditParticipantResource::collection($this->whenLoaded('participants')),
        ];
    }
}
