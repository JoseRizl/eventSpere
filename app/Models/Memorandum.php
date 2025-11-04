<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Memorandum extends Model
{
    use HasFactory;

    protected $table = 'memorandums';

    protected $fillable = [
        'event_id',
        'type',
        'content',
        'filename',
    ];

    /**
     * Get the event that owns the memorandum.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
