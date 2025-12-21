<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\User;

class Task extends Model
{
    use HasFactory;

    protected $table = 'emport.tasks';

    protected $fillable = ['description', 'event_id', 'committee_id'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'emport.task_employee')->using(TaskEmployee::class);
    }

    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'emport.task_manager');
    }
}
