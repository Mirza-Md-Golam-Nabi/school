<?php

namespace App\Support;

use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\FeeDiscount;
use App\Models\FeeType;
use App\Models\Group;
use App\Models\LeaveType;
use App\Models\SalaryComponent;
use App\Models\SchoolAccount;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\TransactionCategory;
use App\Models\User;
use Closure;

/**
 * Request-scoped memo for the human-readable labels written into the activity
 * log. Bulk writes (marks entry, invoice generation, class attendance) log one
 * activity per row, and every row used to re-query the same class / subject /
 * exam / student name — this keeps each lookup to one query per request.
 *
 * Bound as a scoped singleton (see AppServiceProvider), so it never outlives
 * a request, queued job or test. It is also flushed whenever one of the
 * SOURCE_MODELS changes, so a rename is never logged with its old name.
 */
class ActivityLogLabelCache
{
    /**
     * Models whose attributes end up inside a cached label.
     *
     * @var list<class-string>
     */
    public const SOURCE_MODELS = [
        Classes::class,
        Exam::class,
        ExamType::class,
        FeeDiscount::class,
        FeeType::class,
        Group::class,
        LeaveType::class,
        SalaryComponent::class,
        SchoolAccount::class,
        Section::class,
        StaffProfile::class,
        StudentProfile::class,
        Subject::class,
        TeacherProfile::class,
        TransactionCategory::class,
        User::class,
    ];

    /** @var array<string, mixed> */
    private array $labels = [];

    /**
     * Misses (null) are never cached, so a record created later in the same
     * request is still found.
     */
    public function remember(string $key, Closure $resolver): mixed
    {
        if (array_key_exists($key, $this->labels)) {
            return $this->labels[$key];
        }

        $label = $resolver();

        if ($label !== null) {
            $this->labels[$key] = $label;
        }

        return $label;
    }

    public function flush(): void
    {
        $this->labels = [];
    }
}
