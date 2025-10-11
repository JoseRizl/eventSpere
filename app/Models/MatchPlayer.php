<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchPlayer extends Model
{
    use HasFactory;

    protected $table = 'match_players';

    // Use default incrementing integer id
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'match_id',
        'player_id',
        'name',
        'slot',
        'score',
        'completed',
    ];

    public $timestamps = false;

    protected $casts = [
        'slot' => 'integer',
        'score' => 'integer',
        'completed' => 'boolean',
    ];

    // A MatchPlayer belongs to a match (match_players.match_id -> matches.id)
    public function match(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Matches::class, 'match_id', 'id');
    }
}
