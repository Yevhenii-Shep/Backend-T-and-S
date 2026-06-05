<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Project extends Model
{
    use HasFactory;

    // просто константы для статусов проекта, если тебе по логике будут нужны будут еще статусы или ты захочешь эти поменять просто допиши или поменяй
    // на датабазу это никак не повлияет
    const STATUS_PENGING = 0; // Типо в ожидании аудита
    const STATUS_ACTIVE = 1;
    const STATUS_DONE = 2;
    const STATUS_INACTIVE = 3;
    const PROGRAM_TYPE_A = 1;
    const PROGRAM_TYPE_B = 2;

    protected $table = 'projects';

    protected $fillable = [
        'name',
        'slug',
        'team_id',
        'organization_id',
        'program_type',
        'mentor_from_nti',
        'category_id',
        'mentor_from_organization',
        'status',
        'description',
        'deadline',
    ];

    protected $casts = [
        'deadline' => 'datetime',
    ];

    // Проект принадлежит команде(может и не принадлежать, то есть быть null) 1:N
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    // Проект принадлежит организации(может и не принадлежать, то есть быть null) 1:N
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    // Категория проекта 1:N
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // Ментор от NTI 1:N
    public function ntiMentor()
    {
        return $this->belongsTo(User::class, 'mentor_from_nti');
    }

    // Ментор от организации(может и не быть, то есть быть null) 1:N
    public function organizationMentor()
    {
        return $this->belongsTo(User::class, 'mentor_from_organization');
    }

    // Документы проекта 1:N
    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    // Оценки проекта 1:N
    public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
    }

    // Этапы проекта 1:N
    public function milestones()
    {
        return $this->hasMany(Milestone::class);
    }

    // Аудиты проекта 1:N
    public function auditEvents()
    {
        return $this->hasMany(AuditEvent::class);
    }
}
