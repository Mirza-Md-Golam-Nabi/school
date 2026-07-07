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

class TeacherProfile extends Model
{
    use HasFactory;
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
            ->toArray();
    }
}
