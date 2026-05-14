<?php

namespace App\Models;

use App\Enums\SubjectType;
use App\Models\Classes;
use App\Models\Group;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ClassGroupSubject extends Pivot
{
    // protected $table = 'class_group_subject';

    protected $fillable = [
        'class_id',
        'group_id',
        'subject_id',
        'subject_type',
    ];

    protected $casts = [
        'group_id' => 'integer',
        'subject_type' => SubjectType::class,
    ];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    /**
     * group_id = NULL means for all groups
     */
    public function isForAllGroups(): bool
    {
        return is_null($this->group_id);
    }

    public function isCompulsory(): bool
    {
        return $this->subject_type === SubjectType::Compulsory;
    }

    public function isMainOptional(): bool
    {
        return $this->subject_type === SubjectType::MainOptional;
    }

    public function isExtraOptional(): bool
    {
        return $this->subject_type === SubjectType::ExtraOptional;
    }
}
