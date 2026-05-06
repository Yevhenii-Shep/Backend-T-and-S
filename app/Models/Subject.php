<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\Category;

class Subject extends Model
{
    use HasFactory;

    protected $table = 'subjects';

    protected $fillable = [
        'name',
        'description',
    ];

    // Предмет изучается пользователями(студентами) M:N
    public function users()
    {
        return $this->belongsToMany(User::class, 'student_subject')
            ->withPivot('grade');
    }

    // Предмет относится к категориям M:N(через category_subject, не отдельная модель)
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_subject');
    }
}
