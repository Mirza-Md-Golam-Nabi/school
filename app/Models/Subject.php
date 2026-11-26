<?php

namespace App\Models;

use App\Models\Classes;
use App\Models\ClassGroupSubject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'parent_subject_id',
        'sort_order',
        'has_mcq',
        'has_written',
        'has_practical',
        'is_active',
    ];

    protected $casts = [
        'parent_subject_id' => 'integer',
        'sort_order' => 'integer',
        'has_mcq' => 'boolean',
        'has_written' => 'boolean',
        'has_practical' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * The main subject this paper belongs to (Biology 1st Paper → Biology).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'parent_subject_id');
    }

    /**
     * The papers of this main subject (Biology → Biology 1st / 2nd Paper).
     */
    public function papers(): HasMany
    {
        return $this->hasMany(Subject::class, 'parent_subject_id');
    }

    public function isPaper(): bool
    {
        return $this->parent_subject_id !== null;
    }

    /**
     * Subjects a class's exams, merges and teachers can use: every subject in
     * the class syllabus, plus the papers of those subjects (the syllabus
     * lists only the main subject — Biology — not its papers).
     */
    public function scopeTaughtInClass(Builder $query, int $classId): Builder
    {
        $inClass = fn (Builder $query) => $query->where('classes.id', $classId);

        return $query->where(fn (Builder $query) => $query
            ->whereHas('classes', $inClass)
            ->orWhereHas('parent', fn (Builder $query) => $query->whereHas('classes', $inClass)));
    }

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
