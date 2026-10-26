<?php

namespace App\Models;

use App\Enums\CountMethod;
use App\Enums\ResultRounding;
use Database\Factories\ResultRuleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * How one exam's result is made from several exams: the target exam type
 * (e.g. "Final") plus its components — each an exam type with its share of
 * the result (the target is one of them; all shares add up to 100) and
 * whether it must be passed on its own. Applies to the classes attached to
 * it; a class follows at most one rule. Nothing in code names an exam —
 * the school names and weighs them all here.
 */
class ResultRule extends Model
{
    /** @use HasFactory<ResultRuleFactory> */
    use HasFactory;

    /**
     * School Settings key for the full marks a rule uses when it gives none of its own.
     */
    public const DEFAULT_FULL_MARKS_SETTING = 'result_full_marks';

    public const DEFAULT_FULL_MARKS = 100;

    protected $fillable = [
        'name',
        'target_exam_type_id',
        'full_marks',
        'rounding',
    ];

    protected $casts = [
        'full_marks' => 'float',
        'rounding' => ResultRounding::class,
    ];

    protected static function booted(): void
    {
        // Before the row goes, while its classes and components can still be read.
        static::deleting(fn (ResultRule $rule) => $rule->logActivity('deleted', old: $rule->activitySummary()));
    }

    /**
     * One activity-log row for a change to the rule with its classes and
     * components (they are saved together, so they are logged together).
     *
     * @param  array<string, mixed>|null  $old
     */
    public function logActivity(string $event, ?array $old = null): void
    {
        $properties = $event === 'deleted'
            ? ['old' => $old]
            : array_filter(['attributes' => $this->unsetRelations()->activitySummary(), 'old' => $old]);

        activity('result_rule')
            ->performedOn($this)
            ->event($event)
            ->withProperties($properties)
            ->log(ucfirst($event)." result rule \"{$this->name}\".");
    }

    /**
     * Why these components cannot make a result, or null when they can:
     * every exam type once, shares adding up to exactly 100%, and the
     * target exam type among them.
     *
     * @param  array<array-key, array{exam_type_id?: mixed, weight_percent?: mixed}>  $components
     */
    public static function componentsProblem(array $components, mixed $targetExamTypeId): ?string
    {
        $components = collect($components);
        $examTypeIds = $components->pluck('exam_type_id')->filter()->map(fn (mixed $id): int => (int) $id);

        if ($examTypeIds->duplicates()->isNotEmpty()) {
            return 'একই পরীক্ষা দুইবার দেওয়া যাবে না।';
        }

        $total = round($components->sum(fn (array $component): float => (float) ($component['weight_percent'] ?? 0)), 2);

        if ($total !== 100.0) {
            return "সব অংশ মিলে ১০০% হতে হবে — এখন {$total}%।";
        }

        if (filled($targetExamTypeId) && ! $examTypeIds->contains((int) $targetExamTypeId)) {
            return 'যে পরীক্ষার রেজাল্ট তৈরি হবে, সেটাও একটা অংশ হিসেবে থাকতে হবে।';
        }

        return null;
    }

    /**
     * Names of the given classes that another rule already covers — a class
     * follows one rule only.
     *
     * @param  array<array-key, mixed>  $classIds
     * @return Collection<int, string>
     */
    public static function classesTakenByOtherRules(array $classIds, ?int $exceptRuleId = null): Collection
    {
        return Classes::whereIn('id', $classIds)
            ->whereHas('resultRules', fn (Builder $rules) => $rules->when($exceptRuleId, fn (Builder $rules) => $rules->whereKeyNot($exceptRuleId)))
            ->pluck('name');
    }

    public function targetExamType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class, 'target_exam_type_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(ResultRuleComponent::class)->orderBy('sort_order');
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Classes::class, 'result_rule_classes', 'result_rule_id', 'class_id')->withTimestamps();
    }

    /**
     * The rule that builds this exam's result: one attached to the exam's
     * class whose target is the exam's type. Exams of any other type keep
     * their own marks.
     */
    public static function forExam(Exam $exam): ?self
    {
        return self::forClass($exam->class_id)
            ->where('target_exam_type_id', $exam->exam_type_id)
            ->first();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForClass(Builder $query, int $classId): Builder
    {
        return $query->whereHas('classes', fn (Builder $classes) => $classes->where('classes.id', $classId));
    }

    public function resolvedFullMarks(): float
    {
        return (float) ($this->full_marks ?: SchoolSetting::get(self::DEFAULT_FULL_MARKS_SETTING, self::DEFAULT_FULL_MARKS));
    }

    /**
     * Everything the result calculation needs from this rule, frozen as
     * plain data — kept on the exam (`result_rule_snapshot`), so a published
     * result never changes when the rule or an exam type's count method
     * is edited later.
     *
     * @return array{rule_id: int, name: string, full_marks: float, rounding: string, target_exam_type_id: int, components: list<array{exam_type_id: int, name: string, weight_percent: float, must_pass: bool, count_method: string, best_n_count: ?int}>}
     */
    public function snapshot(): array
    {
        $this->loadMissing('components.examType.examTypeConfig');

        return [
            'rule_id' => $this->id,
            'name' => $this->name,
            'full_marks' => $this->resolvedFullMarks(),
            'rounding' => ($this->rounding ?? ResultRounding::Decimal)->value,
            'target_exam_type_id' => $this->target_exam_type_id,
            'components' => $this->components->map(fn (ResultRuleComponent $component): array => [
                'exam_type_id' => $component->exam_type_id,
                'name' => $component->examType?->name ?? "Exam type #{$component->exam_type_id}",
                'weight_percent' => (float) $component->weight_percent,
                'must_pass' => (bool) $component->must_pass,
                'count_method' => ($component->examType?->examTypeConfig?->count_method ?? CountMethod::All)->value,
                'best_n_count' => $component->examType?->examTypeConfig?->best_n_count,
            ])->values()->all(),
        ];
    }

    /**
     * "1st Semester 25% + 2nd Semester 25% + Final 50%" — how the result is made, in one line.
     */
    public function componentsSummary(): string
    {
        $this->loadMissing('components.examType');

        return $this->components
            ->map(fn (ResultRuleComponent $component): string => "{$component->examType?->name} ".((float) $component->weight_percent + 0).'%')
            ->implode(' + ');
    }

    /**
     * What the activity log keeps of the rule — its classes and components by name.
     *
     * @return array<string, mixed>
     */
    public function activitySummary(): array
    {
        $this->loadMissing('targetExamType', 'classes', 'components.examType');

        return [
            'name' => $this->name,
            'target_exam_type_id' => $this->target_exam_type_id,
            'target_exam_type_id_label' => $this->targetExamType?->name,
            'full_marks' => $this->full_marks,
            'rounding' => $this->rounding?->value,
            'class_id' => $this->classes->pluck('id')->all(),
            'class_id_label' => $this->classes->pluck('name')->all(),
            'components' => $this->components
                ->map(fn (ResultRuleComponent $component): string => "{$component->examType?->name} {$component->weight_percent}%".($component->must_pass ? ' (must pass)' : ''))
                ->all(),
        ];
    }
}
