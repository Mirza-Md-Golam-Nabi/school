<?php

namespace App\Models;

use App\Models\ExamTypeConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExamType extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected $hidden = ['created_at', 'updated_at'];

    public function examTypeConfig(): HasOne
    {
        return $this->hasOne(ExamTypeConfig::class);
    }
}
