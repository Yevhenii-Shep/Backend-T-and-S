<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** JSON-представление проекта для API. */
class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'team_id' => $this->team_id,
            'team_name' => $this->whenLoaded('team', fn () => $this->team?->name),
            'organization_id' => $this->organization_id,
            'organization_name' => $this->whenLoaded('organization', fn () => $this->organization?->name),
            'program_type' => $this->program_type,
            'mentor_from_nti' => $this->mentor_from_nti,
            'mentor_from_nti_name' => $this->whenLoaded('ntiMentor', fn () => $this->ntiMentor?->name),
            'mentor_from_organization' => $this->mentor_from_organization,
            'mentor_from_organization_name' => $this->whenLoaded('organizationMentor', fn () => $this->organizationMentor?->name),
            'category_id' => $this->category_id,
            'category_name' => $this->whenLoaded('category', fn () => $this->category?->name),
            'status' => $this->status,
            'description' => $this->description,
            'deadline' => $this->deadline,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'team' => TeamResource::make($this->whenLoaded('team')),
            'organization' => OrganizationResource::make($this->whenLoaded('organization')),
            'category' => $this->whenLoaded('category'),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
            'milestones' => MilestoneResource::collection($this->whenLoaded('milestones')),
            'audit_events' => AuditEventResource::collection($this->whenLoaded('auditEvents')),
            'evaluations' => EvaluationResource::collection($this->whenLoaded('evaluations')),
        ];
    }
}
