<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditParticipant extends Model
{
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
