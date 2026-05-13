<?php

namespace App\Models;

use App\Enums\ClassLevel;
use App\Models\Group;
use App\Models\Section;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Classes extends Model
{
    use SoftDeletes;

    protected $table = 'classes';

    protected $fillable = ['name', 'level', 'order', 'has_section', 'has_group', 'is_active'];

    protected $casts = [
        'level' => ClassLevel::class,
        'has_section' => 'boolean',
        'has_group' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class, 'class_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'class_groups', 'class_id', 'group_id')
            ->withTimestamps();
    }
}
