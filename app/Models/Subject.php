<?php

namespace App\Models;

use App\Models\Classes;
use App\Models\ClassGroupSubject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'has_mcq',
        'has_written',
        'has_practical',
        'is_active',
    ];

    protected $casts = [
        'has_mcq' => 'boolean',
        'has_written' => 'boolean',
        'has_practical' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Pivot entries that assign this subject to classes/groups.
     */
    public function classGroupSubjects(): HasMany
    {
        return $this->hasMany(ClassGroupSubject::class);
    }

    /**
     * Classes this subject is assigned to (via pivot).
     */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Classes::class, 'class_group_subjects')
            ->using(ClassGroupSubject::class)
            ->withPivot('group_id', 'subject_type')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query)
    {
        return $query->where('is_active', true);
    }
}
