<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ClassGroup extends Pivot
{
    protected $table = 'class_groups';

    public $timestamps = true;
}
