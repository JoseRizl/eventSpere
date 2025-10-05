<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventTag extends Model
{
    use HasFactory;

    protected $table = 'event_tag';

    protected $fillable = ['event_id', 'tag_id'];
}
