<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'user_id',
        'message',
        'image',
        'timestamp',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
    ];


     //withDefault() ensures that therelationship is optional, allowing for general announcements.

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withDefault();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
