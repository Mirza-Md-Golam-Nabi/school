<?php

namespace App\Models;

use App\Enums\NoticeTargetType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notice extends Model
{
    protected $fillable = [
        'title',
        'body',
        'target_type',
        'send_sms',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'target_type' => NoticeTargetType::class,
        'send_sms' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(NoticeTarget::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(NoticeRead::class);
    }

    public function smsLogs(): HasMany
    {
        return $this->hasMany(SmsLog::class, 'source_id')->where('source_type', 'notice');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '>', now());
    }

    public function scopeCurrentYear(Builder $query): Builder
    {
        return $query->whereYear('published_at', now()->year);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }

    public function isScheduled(): bool
    {
        return $this->published_at !== null && $this->published_at->isFuture();
    }

    /**
     * Scope notices visible to a student: everyone, all students, their
     * current class, or them individually.
     */
    public function scopeVisibleToStudent(Builder $query, StudentProfile $student): Builder
    {
        return $query->currentYear()->where(function (Builder $query) use ($student) {
            $query->whereIn('target_type', [NoticeTargetType::All, NoticeTargetType::Students])
                ->when($student->current_class_id, function (Builder $query) use ($student) {
                    $query->orWhere(function (Builder $query) use ($student) {
                        $query->where('target_type', NoticeTargetType::ByClass)
                            ->whereHas('targets', function (Builder $query) use ($student) {
                                $query->where('targetable_type', Classes::class)
                                    ->where('targetable_id', $student->current_class_id);
                            });
                    });
                })
                ->orWhere(function (Builder $query) use ($student) {
                    $query->where('target_type', NoticeTargetType::IndividualStudent)
                        ->whereHas('targets', function (Builder $query) use ($student) {
                            $query->where('targetable_type', User::class)
                                ->where('targetable_id', $student->user_id);
                        });
                });
        });
    }

    /**
     * Scope notices visible to a teacher: everyone, all teachers, or them
     * individually.
     */
    public function scopeVisibleToTeacher(Builder $query, TeacherProfile $teacher): Builder
    {
        return $query->currentYear()->where(function (Builder $query) use ($teacher) {
            $query->whereIn('target_type', [NoticeTargetType::All, NoticeTargetType::Teachers])
                ->orWhere(function (Builder $query) use ($teacher) {
                    $query->where('target_type', NoticeTargetType::IndividualTeacher)
                        ->whereHas('targets', function (Builder $query) use ($teacher) {
                            $query->where('targetable_type', User::class)
                                ->where('targetable_id', $teacher->user_id);
                        });
                });
        });
    }

    public function isReadBy(User $user): bool
    {
        if ($this->relationLoaded('reads')) {
            return $this->reads->contains('user_id', $user->id);
        }

        return $this->reads()->where('user_id', $user->id)->exists();
    }
}
