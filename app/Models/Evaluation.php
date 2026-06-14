<?php

namespace App\Models;

use App\Models\Concerns\Slug\HasSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Evaluation extends Model
{
    use HasFactory, HasSlug;

    protected $table = 'evaluations';

    protected $fillable = [
        'score',
        'comment',
        'project_id',
        'slug',
        'evaluator_id',
    ];

    // Оценка принадлежит проекту 1:N
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    // Оценку поставил user 1:N
    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    protected function slugBase(): string
    {
        return 'evaluation-'.$this->project_id.'-'.$this->evaluator_id;
    }
}
