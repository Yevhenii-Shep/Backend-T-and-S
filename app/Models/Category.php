<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\Slug\HasSlug;

class Category extends Model
{
    use HasFactory;
    use HasSlug;

    protected $table = 'categories';

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    // Категория имеет много проектов 1:N
    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    // Категории связаны с предметами (M:N через category_subject, не отдельная модель)
    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'category_subject');
    }
}
