<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TaskEmployee extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'task_employee';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;
}
