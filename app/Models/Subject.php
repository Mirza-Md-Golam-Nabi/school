<?php

namespace App\Models;

use App\Models\Classes;
use App\Models\ClassGroupSubject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Subject extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'name_bn',
        'code',
        'sort_order',
        'has_mcq',
        'has_written',
        'has_practical',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
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
        return $this->belongsToMany(Classes::class, 'class_group_subject', 'subject_id', 'class_id')
            ->using(ClassGroupSubject::class)
            ->withPivot('group_id', 'subject_type')
            ->withTimestamps();
    }

    /**
     * A new subject without an explicit position goes after every existing one.
     */
    protected static function booted(): void
    {
        static::creating(function (Subject $subject): void {
            if (! $subject->sort_order) {
                $subject->sort_order = (int) static::withTrashed()->max('sort_order') + 1;
            }
        });
    }

    public function scopeActive(Builder $query)
    {
        return $query->where('is_active', true);
    }

    /**
     * The school-wide subject order (marksheets, results) — the single place
     * that defines it. Models that hold a subject_id sort by it through the
     * OrdersBySubject trait, which reads the same columns.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Locale-aware subject name — বাংলা locale-এ name_bn (fallback: name), অন্যথায় name।
     */
    public function getDisplayNameAttribute(): string
    {
        if (app()->getLocale() === 'bn' && filled($this->name_bn)) {
            return $this->name_bn;
        }

        return $this->name;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('subject')
            ->setDescriptionForEvent(fn (string $eventName): string => ucfirst($eventName)." subject \"{$this->name}\".");
    }
}
