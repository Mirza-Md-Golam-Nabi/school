<?php

namespace App\Models;

use App\Models\Classes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Group extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = ['name', 'name_bn', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Locale-aware group name — বাংলা locale-এ name_bn (fallback: name), অন্যথায় name।
     */
    public function getDisplayNameAttribute(): string
    {
        if (app()->getLocale() === 'bn' && filled($this->name_bn)) {
            return $this->name_bn;
        }

        return $this->name;
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Classes::class, 'class_groups', 'group_id', 'class_id')
            ->using(ClassGroup::class)
            ->withTimestamps();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('group')
            ->setDescriptionForEvent(fn (string $eventName): string => ucfirst($eventName)." group \"{$this->name}\".");
    }
}
