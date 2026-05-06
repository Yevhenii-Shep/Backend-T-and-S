<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Document extends Model
{
    use HasFactory;

    protected $table = 'documents';

    protected $fillable = [
        'project_id',
        'name',
        'description',
        'file_path',
    ];

    // Документ принадлежит проекту 1:N
    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
