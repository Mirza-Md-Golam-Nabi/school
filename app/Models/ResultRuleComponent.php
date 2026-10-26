<?php

namespace App\Models;

use Database\Factories\ResultRuleComponentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One part of a ResultRule: an exam type, its share of the result (%), and
 * whether a student must pass it on its own.
 */
class ResultRuleComponent extends Model
{
    /** @use HasFactory<ResultRuleComponentFactory> */
    use HasFactory;

    protected $fillable = [
        'result_rule_id',
        'exam_type_id',
        'weight_percent',
        'must_pass',
        'sort_order',
    ];

    protected $casts = [
        'weight_percent' => 'float',
        'must_pass' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function resultRule(): BelongsTo
    {
        return $this->belongsTo(ResultRule::class);
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }
}
