<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'role',
        'name',
        'email',
        'organization_id',
        'birth_date',
        'password',
        'phone',
        'avatar_path',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    // Пользователь принадлежит организации(может и не приналдежать, то есть быть null) 1:N
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    // Пользователи состоят в командах (M:N через team_user + pivot модель TeamUser)
    public function teams()
    {
        return $this->belongsToMany(Team::class)
            ->using(TeamUser::class)
            ->withPivot('join_date', 'leave_date', 'is_leader')
            ->withTimestamps();
    }

    // Пользователь участвует в проектах как mentor_from_NTI(может и не учавствовать, то есть быть null) 1:N
    public function ntiProjects()
    {
        return $this->hasMany(Project::class, 'mentor_from_nti');
    }

    // Пользователь участвует в проектах как mentor_from_organization(может и не учавствовать, то есть быть null) 1:N
    public function organizationProjects()
    {
        return $this->hasMany(Project::class, 'mentor_from_organization');
    }

    // Пользователь оставляет оценки 1:N
    public function evaluations()
    {
        return $this->hasMany(Evaluation::class, 'evaluator_id');
    }

    // Пользователь участвует в аудитах (M:N через audit_partipicans + отдельная модель AuditParticipant)
    public function auditParticipants()
    {
        return $this->hasMany(AuditParticipant::class);
    }

    // Пользователь изучает предметы (M:N через student_subject)
    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'student_subject')
            ->withPivot('grade');
    }
}
