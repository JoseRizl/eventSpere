<?php

// app/Models/Event.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'title', 'description', 'category_id',
        'startDate', 'endDate', 'startTime', 'endTime',
        'image', 'archived'
    ];

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }
}

