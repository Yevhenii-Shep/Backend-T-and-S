<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Slug\HasSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Organization extends Model
{
    use HasFactory;
    use HasSlug;

    protected $table = 'organizations';

    protected $fillable = [
        'name',
        'slug',
        'logo_path',
        'description',
        'website_url',
        'ico',
        'phone',
        'email',
        'sector',
    ];

    // Связь с юзерами(работник имеет конкретную организацию, а организация много работников) 1:N
    public function users()
    {
        return $this->hasMany(User::class);
    }

    // Связь с проектами(проект имеет конкретную организацию, а организация много проектов) 1:N
    public function projects()
    {
        return $this->hasMany(Project::class);
    }
}
