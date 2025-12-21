<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Bracket extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'type',
        'event_id',
        'tiebreaker_data',
        'allow_draws'
    ];

    protected $casts = [
        'tiebreaker_data' => 'array'
    ];

    // Relationships
    public function matches(): HasMany
    {
        return $this->hasMany(\App\Models\Matches::class, 'bracket_id', 'id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Event::class, 'event_id', 'id');
    }

    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bracket_manager', 'bracket_id', 'user_id')
                    ->using(BracketManager::class);
    }
}
