<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** JSON-представление предмета; grade — из pivot student_subject при привязке к студенту. */
class SubjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'grade' => $this->when(isset($this->pivot), fn () => $this->pivot?->grade),
        ];
    }
}
