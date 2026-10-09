<?php

namespace App\Models;

use App\Enums\BloodGroup;
use App\Enums\Gender;
use App\Enums\Religion;
use App\Enums\StudentStatus;
use App\Traits\LogsRelationLabels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentProfile extends Model
{
    use LogsActivity;
    use LogsRelationLabels;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'photo',
        'roll_no',
        'registration_no',
        'birth_certificate_no',
        'current_class_id',
        'current_section_id',
        'current_group_id',
        'session_year',
        'gender',
        'date_of_birth',
        'blood_group',
        'religion',
        'nationality',
        'father_name',
        'father_occupation',
        'father_photo',
        'mother_name',
        'mother_occupation',
        'mother_photo',
        'guardian_name',
        'guardian_relation',
        'guardian_occupation',
        'guardian_phone',
        'guardian_photo',
        'admission_date',
        'status',
    ];

    protected $casts = [
        'gender' => Gender::class,
        'blood_group' => BloodGroup::class,
        'religion' => Religion::class,
        'status' => StudentStatus::class,
        'date_of_birth' => 'date',
        'admission_date' => 'date',
        'session_year' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'current_class_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'current_section_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'current_group_id');
    }

    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function attendances(): MorphMany
    {
        return $this->morphMany(Attendance::class, 'attendable');
    }

    /**
     * The enroll IDs this person has on attendance devices.
     */
    public function deviceEnrollments(): MorphMany
    {
        return $this->morphMany(DeviceUser::class, 'enrollable');
    }

    public function feeDiscounts(): HasMany
    {
        return $this->hasMany(StudentFeeDiscount::class, 'student_id');
    }

    public function feeInvoices(): HasMany
    {
        return $this->hasMany(StudentFeeInvoice::class, 'student_id');
    }

    public function feePayments(): HasMany
    {
        return $this->hasMany(FeePayment::class, 'student_id');
    }

    public function classHistories(): HasMany
    {
        return $this->hasMany(StudentClassHistory::class, 'student_id');
    }

    public function optionalSubjects(): HasMany
    {
        return $this->hasMany(StudentOptionalSubject::class, 'student_id');
    }

    /**
     * Public URL of the official photo the school uploaded for this student —
     * not the avatar the student may have set on their own account.
     */
    public function photoUrl(): ?string
    {
        $storage = Storage::disk('public');

        return ($this->photo && $storage->exists($this->photo))
            ? $storage->url($this->photo)
            : null;
    }

    /**
     * The number printed and barcoded on the student's ID card: their
     * registration number, or — when none is recorded — the year they joined
     * plus their profile id. Unlike session_year, neither changes on promotion,
     * so a card stays valid for the student's whole time at the school.
     */
    public function idCardNumber(): string
    {
        if (filled($this->registration_no)) {
            return (string) $this->registration_no;
        }

        return sprintf('%d%04d', ($this->admission_date ?? $this->created_at)->year, $this->id);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', StudentStatus::Active);
    }

    public function scopeFormer(Builder $query): void
    {
        $query->whereIn('status', [
            StudentStatus::Transferred,
            StudentStatus::Dropped,
            StudentStatus::Graduated,
        ]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('student_profile')
            ->setDescriptionForEvent(fn (string $eventName): string => ucfirst($eventName)." student profile \"{$this->displayName()}\".");
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'user_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(User::class, $id),
            'current_class_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(Classes::class, $id),
            'current_section_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(Section::class, $id),
            'current_group_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(Group::class, $id),
        ];
    }

    private function displayName(): string
    {
        $name = trim("{$this->user?->name} (Roll: {$this->roll_no})") ?: "Student #{$this->id}";

        if ($this->current_class_id === null) {
            return $name;
        }

        $classLabel = self::activityNameLabel(Classes::class, $this->current_class_id) ?? "Class #{$this->current_class_id}";

        return "{$name} - {$classLabel}";
    }
}
