<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Team extends Model
{
    use HasFactory;

    protected $table = 'teams';

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Команда имеет много проектов 1:N
    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    // Команда имеет много пользователей (M:N через team_user + pivot модель TeamUser)
    public function users()
    {
        return $this->belongsToMany(User::class)
            ->using(TeamUser::class)
            ->withPivot('join_date', 'leave_date', 'is_leader')
            ->withTimestamps();
    }
}
