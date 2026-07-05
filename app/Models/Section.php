<?php

namespace App\Models;

use App\Models\Classes;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Section extends Model
{
    use SoftDeletes;

    protected $fillable = ['class_id', 'name', 'capacity', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public static function dropdownOptionsByClass(int $classId): Collection
    {
        return static::query()
            ->active()
            ->where('class_id', $classId)
            ->pluck('name', 'id');
    }
}
