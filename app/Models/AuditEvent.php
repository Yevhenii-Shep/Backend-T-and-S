<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    // Аудит относится к конкретному проекту
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    // Главный аудитор
    public function mainAuditor()
    {
        return $this->belongsTo(User::class, 'main_auditor');
    }

    // Участники аудита
    public function participants()
    {
        return $this->hasMany(AuditParticipant::class);
    }
}
