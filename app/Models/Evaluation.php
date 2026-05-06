<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Evaluation extends Model
{
    use HasFactory;

    protected $table = 'evaluations';

    protected $fillable = [
        'score',
        'comment',
        'project_id',
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
}
