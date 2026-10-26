<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ExamType extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = ['name', 'is_active'];

    protected $hidden = ['created_at', 'updated_at'];

    protected static function booted(): void
    {
        static::deleting(function (ExamType $examType): void {
            $examType->update(['is_active' => false]);
            $examType->examTypeConfig()->delete();
        });

        static::restoring(function (ExamType $examType): void {
            $examType->update(['is_active' => true]);
            $examType->examTypeConfig()->withTrashed()->restore();
        });
    }

    public function examTypeConfig(): HasOne
    {
        return $this->hasOne(ExamTypeConfig::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('exam_type')
            ->setDescriptionForEvent(fn (string $eventName): string => ucfirst($eventName)." exam type \"{$this->name}\".");
    }
}
