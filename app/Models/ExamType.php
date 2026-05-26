<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExamType extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'is_active'];

    protected $hidden = ['created_at', 'updated_at'];

    protected static function booted(): void
    {
        static::deleting(function (ExamType $examType): void {
            $examType->update(['is_active' => false]);
            $examType->examTypeConfig()->delete();
            $examType->contributeRules()->delete();
        });

        static::restoring(function (ExamType $examType): void {
            $examType->update(['is_active' => true]);
            $examType->examTypeConfig()->withTrashed()->restore();
            $examType->contributeRules()->withTrashed()->restore();
        });
    }

    public function examTypeConfig(): HasOne
    {
        return $this->hasOne(ExamTypeConfig::class);
    }

    public function contributeRules(): HasMany
    {
        return $this->hasMany(ExamContributeRule::class, 'source_exam_type_id');
    }
}
