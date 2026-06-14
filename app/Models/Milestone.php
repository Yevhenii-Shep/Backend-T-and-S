<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Milestone extends Model
{
    use HasFactory;

    // просто константы для статусов проекта, если тебе по логике будут нужны будут еще статусы или ты захочешь эти поменять просто допиши или поменяй
    // на датабазу это никак не повлияет
    const STATUS_PENDING = 0; // Типо к нему еще не дошли
    const STATUS_IN_PROGRESS = 1;
    const STATUS_DONE = 2;
    const STATUS_FAILED = 3;

    protected $table = 'milestones';

    protected $fillable = [
        'project_id',
        'name',
        'status',
        'deadline',
        'description',
    ];

    protected $casts = [
        'deadline' => 'datetime',
    ];

    // Этап принадлежит проекту 1:N
    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
