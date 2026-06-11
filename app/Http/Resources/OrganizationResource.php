<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'website_url' => $this->website_url,
            'phone' => $this->phone,
            'email' => $this->email,
            'sector' => $this->sector,
            'logo_path' => $this->logo_path,
            'ico' => $this->ico,

            'projects' => $this->whenLoaded('projects'),
            'organization_admin' => $this->organization_admin ?? null,
            'employees' => $this->employees ?? null,
        ];
    }
}
