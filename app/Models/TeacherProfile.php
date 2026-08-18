<?php

namespace App\Models;

use App\Enums\BloodGroup;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\Religion;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TeacherProfile extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'gender',
        'date_of_birth',
        'blood_group',
        'religion',
        'nationality',
        'designation',
        'department',
        'qualification',
        'joining_date',
        'status',
        'default_school_account_id',
    ];

    protected $casts = [
        'gender' => Gender::class,
        'blood_group' => BloodGroup::class,
        'religion' => Religion::class,
        'status' => EmploymentStatus::class,
        'date_of_birth' => 'date',
        'joining_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::deleting(function (TeacherProfile $profile) {
            if (! $profile->isForceDeleting()) {
                $profile->status = EmploymentStatus::Terminated;
                $profile->saveQuietly();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function attendances(): MorphMany
    {
        return $this->morphMany(Attendance::class, 'attendable');
    }

    public function leaveApplications(): MorphMany
    {
        return $this->morphMany(LeaveApplication::class, 'applicant');
    }

    public function teacherSubjects(): HasMany
    {
        return $this->hasMany(TeacherSubject::class, 'teacher_id');
    }

    public function classesAsClassTeacher(): HasMany
    {
        return $this->hasMany(Classes::class, 'class_teacher_id');
    }

    public function isAssignedToTeach(int $classId, int $subjectId, int $sessionYear): bool
    {
        return $this->teacherSubjects()
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('session_year', $sessionYear)
            ->exists();
    }

    public function defaultSchoolAccount(): BelongsTo
    {
        return $this->belongsTo(SchoolAccount::class, 'default_school_account_id');
    }

    public function salaryStructures(): MorphMany
    {
        return $this->morphMany(SalaryStructure::class, 'profileable');
    }

    public function salaryInvoices(): MorphMany
    {
        return $this->morphMany(SalaryInvoice::class, 'profileable');
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', EmploymentStatus::Active);
    }

    public static function dropdownOptions(): array
    {
        return static::query()
            ->active()
            ->with('user')
            ->get()
            ->pluck('user.name', 'id')
            ->sort()
            ->toArray();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('teacher_profile');
    }
}
