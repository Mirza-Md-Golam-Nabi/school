<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\LeaveApplicability;
use Database\Factories\LeaveTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LeaveType extends Model
{
    /** @use HasFactory<LeaveTypeFactory> */
    use HasFactory;

    use LogsActivity;

    protected $fillable = ['name', 'allowed_days_per_year', 'applicable_gender', 'is_active'];

    protected $casts = [
        'applicable_gender' => LeaveApplicability::class,
        'is_active' => 'boolean',
    ];

    public function leaveApplications(): HasMany
    {
        return $this->hasMany(LeaveApplication::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(LeaveTypeAssignment::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function applicableTo(Builder $query, Gender $gender): void
    {
        $query->whereIn('applicable_gender', [LeaveApplicability::All->value, $gender->value]);
    }

    /**
     * Male/female-only leave types are only usable by an applicant once an
     * admin has explicitly assigned that leave type to them.
     */
    #[Scope]
    protected function availableFor(Builder $query, Model $applicant): void
    {
        $applicantType = $applicant::class;
        $applicantId = $applicant->getKey();

        $query->where(function (Builder $query) use ($applicantType, $applicantId) {
            $query->where('applicable_gender', LeaveApplicability::All->value)
                ->orWhereHas('assignments', function (Builder $query) use ($applicantType, $applicantId) {
                    $query->where('assignable_type', $applicantType)
                        ->where('assignable_id', $applicantId);
                });
        });
    }

    public function requiresAssignment(): bool
    {
        return $this->applicable_gender !== LeaveApplicability::All;
    }

    public function isAssignedTo(Model $applicant): bool
    {
        return $this->assignments()
            ->where('assignable_type', $applicant::class)
            ->where('assignable_id', $applicant->getKey())
            ->exists();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('leave_type')
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    private function activityLogDescription(string $eventName): string
    {
        if ($eventName === 'created') {
            $genderLabel = $this->applicable_gender?->getLabel() ?? (string) $this->applicable_gender;

            return "Created leave type \"{$this->name}\" ({$this->allowed_days_per_year} days/year, {$genderLabel}).";
        }

        return ucfirst($eventName)." leave type \"{$this->name}\".";
    }
}
