<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditParticipant extends Model
{
    const ROLE_AUDITOR = 1;
    const ROLE_CONTRIBUTOR = 2;
    protected $table = 'audit_participants';

    protected $fillable = [
        'user_id',
        'audit_event_id',
        'role',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auditEvent()
    {
        return $this->belongsTo(AuditEvent::class);
    }
}
