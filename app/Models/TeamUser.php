<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TeamUser extends Pivot
{
    protected $table = 'team_user';

    protected $fillable = [
        'team_id',
        'user_id',
        'join_date',
        'leave_date',
        'is_leader',
    ];

    protected $casts = [
        'join_date' => 'datetime',
        'leave_date' => 'datetime',
        'is_leader' => 'boolean',
    ];

    // Связь с пользователем
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Связь с командой
    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
