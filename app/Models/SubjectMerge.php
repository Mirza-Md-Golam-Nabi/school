<?php

namespace App\Models;

use App\Actions\SyncClassSubjectMergesAction;
use App\Enums\MergedAbsentRule;
use App\Enums\MergedComponentPassRule;
use App\Enums\MergedPassRule;
use App\Enums\ResultRounding;
use App\Traits\LogsRelationLabels;
use App\Traits\MergesTwoSubjects;
use Database\Factories\SubjectMergeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A class's default subject merge (e.g. Bangla 1st + 2nd paper = Bangla).
 * Copied onto each exam of the class (ExamSubjectMerge) and kept in step
 * until the exam's merges are changed on the exam itself or its result is
 * published. A rule left empty follows School Settings.
 */
class SubjectMerge extends Model
{
    /** @use HasFactory<SubjectMergeFactory> */
    use HasFactory;

    use LogsActivity;
    use LogsRelationLabels;
    use MergesTwoSubjects;

    public const PASS_RULE_SETTING = 'merged_pass_rule';

    public const COMPONENT_PASS_RULE_SETTING = 'merged_component_pass_rule';

    public const ABSENT_RULE_SETTING = 'merged_absent_rule';

    public const ROUNDING_SETTING = 'merged_rounding';

    protected $fillable = [
        'class_id',
        'name',
        'name_bn',
        'first_subject_id',
        'second_subject_id',
        'pass_rule',
        'component_pass_rule',
        'absent_rule',
        'rounding',
    ];

    /**
     * Every change to a class default reaches the class's exams that still
     * follow it (unpublished, not changed on the exam itself).
     */
    protected static function booted(): void
    {
        $syncClassExams = fn (SubjectMerge $merge) => app(SyncClassSubjectMergesAction::class)->handle($merge->class_id);

        static::saved($syncClassExams);
        static::deleted($syncClassExams);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    /**
     * The school's default rules for merged subjects (School Settings).
     *
     * @return array{pass_rule: MergedPassRule, component_pass_rule: MergedComponentPassRule, absent_rule: MergedAbsentRule, rounding: ResultRounding}
     */
    public static function defaultRules(): array
    {
        return [
            'pass_rule' => MergedPassRule::tryFrom((string) SchoolSetting::get(self::PASS_RULE_SETTING)) ?? MergedPassRule::Combined,
            'component_pass_rule' => MergedComponentPassRule::tryFrom((string) SchoolSetting::get(self::COMPONENT_PASS_RULE_SETTING)) ?? MergedComponentPassRule::Combined,
            'absent_rule' => MergedAbsentRule::tryFrom((string) SchoolSetting::get(self::ABSENT_RULE_SETTING)) ?? MergedAbsentRule::Fail,
            'rounding' => ResultRounding::tryFrom((string) SchoolSetting::get(self::ROUNDING_SETTING)) ?? ResultRounding::Decimal,
        ];
    }

    /**
     * This merge's rules, with School Settings filling any left empty.
     *
     * @return array{pass_rule: MergedPassRule, component_pass_rule: MergedComponentPassRule, absent_rule: MergedAbsentRule, rounding: ResultRounding}
     */
    public function resolvedRules(): array
    {
        $defaults = self::defaultRules();

        return [
            'pass_rule' => $this->pass_rule ?? $defaults['pass_rule'],
            'component_pass_rule' => $this->component_pass_rule ?? $defaults['component_pass_rule'],
            'absent_rule' => $this->absent_rule ?? $defaults['absent_rule'],
            'rounding' => $this->rounding ?? $defaults['rounding'],
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('subject_merge')
            ->setDescriptionForEvent(function (string $eventName): string {
                $classLabel = self::activityNameLabel(Classes::class, $this->class_id) ?? "Class #{$this->class_id}";

                return ucfirst($eventName)." subject merge \"{$this->name}\" in \"{$classLabel}\".";
            });
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'class_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(Classes::class, $id),
            'first_subject_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(Subject::class, $id),
            'second_subject_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(Subject::class, $id),
        ];
    }
}
