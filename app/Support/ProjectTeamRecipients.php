<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Support\Collection;

/** Участники команды проекта — получатели project-related уведомлений. */
class ProjectTeamRecipients
{
    public static function forProject(Project $project): Collection
    {
        $project->loadMissing('team.users');

        if (!$project->team) {
            return collect();
        }

        return $project->team->users;
    }
}
