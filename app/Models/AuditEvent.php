<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AuditEvent extends Model
{
    use HasFactory;

    const RESULT_ACCEPTED = 1;
    const RESULT_DECLINED = 2;

    protected $table = 'audit_events';

    protected $fillable = [
        'project_id',
        'result',
        'main_auditor',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    // Аудит относиться к конкретному проекту 1:N
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    // Главный аудитор (1:N к User)
    public function mainAuditor()
    {
        return $this->belongsTo(User::class, 'main_auditor');
    }

    // Участники аудита (M:N через audit_participants + отдельная модель AuditParticipant)
    public function participants()
    {
        return $this->hasMany(AuditParticipant::class);
    }
}
