<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class BracketManager extends Pivot
{
    protected $table = 'bracket_manager';

    public $timestamps = false;
    
    protected $fillable = [
        'bracket_id',
        'user_id',
    ];
}
