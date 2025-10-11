<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Matches extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'round',
        'match_number',
        'winner_id',
        'loser_id',
        'status',
        'date',
        'time',
        'venue',
        'bracket_id',
        'bracket_type',
        'is_tie'
    ];

    protected $casts = [
        'round' => 'integer',
        'match_number' => 'integer',
        'is_tie' => 'boolean',
        'date' => 'date',
    ];

    // Relationships
    public function bracket()
    {
        return $this->belongsTo(\App\Models\Bracket::class, 'bracket_id', 'id');
    }

    public function matchPlayers(): HasMany
    {
        return $this->hasMany(\App\Models\MatchPlayer::class, 'match_id', 'id');
    }

    // Convenience accessor to get the MatchPlayer row for the winner (if any)
    public function getWinnerPlayerAttribute()
    {
        if (! $this->winner_id) {
            return null;
        }

        return $this->matchPlayers()->where('player_id', $this->winner_id)->first();
    }

    public function getLoserPlayerAttribute()
    {
        if (! $this->loser_id) {
            return null;
        }

        return $this->matchPlayers()->where('player_id', $this->loser_id)->first();
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
