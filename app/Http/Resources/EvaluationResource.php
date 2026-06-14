<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** JSON-представление оценки проекта (score 0–100). */
class EvaluationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'project_id' => $this->project_id,
            'project_name' => $this->whenLoaded('project', fn () => $this->project?->name),
            'evaluator_id' => $this->evaluator_id,
            'evaluator_name' => $this->whenLoaded('evaluator', fn () => $this->evaluator?->name),
            'score' => $this->score,
            'comment' => $this->comment,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'project' => ProjectResource::make($this->whenLoaded('project')),
            'evaluator' => UserResource::make($this->whenLoaded('evaluator')),
        ];
    }
}
