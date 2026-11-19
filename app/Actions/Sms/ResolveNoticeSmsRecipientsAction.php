<?php

namespace App\Actions\Sms;

use App\Actions\Sms\Concerns\BuildsStudentSms;
use App\Enums\NoticeTargetType;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Who receives a notice's SMS: a student's guardian (or the student's own
 * number), and a teacher's or staff member's own number. Works from the
 * notice's target settings alone so the form can estimate the cost before
 * the notice is saved.
 */
class ResolveNoticeSmsRecipientsAction
{
    use BuildsStudentSms;

    /**
     * @param  array<int|string>  $targetIds  Class ids (ByClass) or user ids (Individual*).
     * @return Collection<int, array{name: string, phone: ?string}>
     */
    public function handle(?NoticeTargetType $targetType, array $targetIds = []): Collection
    {
        $targetIds = array_values(array_filter(array_map('intval', $targetIds)));

        return match ($targetType) {
            NoticeTargetType::All => $this->students()
                ->merge($this->employees(TeacherProfile::query()->active()))
                ->merge($this->employees(StaffProfile::query()->active())),
            NoticeTargetType::Students => $this->students(),
            NoticeTargetType::Teachers => $this->employees(TeacherProfile::query()->active()),
            NoticeTargetType::ByClass => $targetIds === [] ? collect() : $this->students($targetIds),
            NoticeTargetType::IndividualStudent => $targetIds === [] ? collect() : $this->students(userIds: $targetIds),
            NoticeTargetType::IndividualTeacher => $targetIds === [] ? collect() : User::query()
                ->whereIn('id', $targetIds)
                ->get(['id', 'name', 'phone'])
                ->map(fn (User $user): array => ['name' => (string) $user->name, 'phone' => $user->phone]),
            null => collect(),
        };
    }

    /**
     * @param  array<int>|null  $classIds
     * @param  array<int>|null  $userIds
     * @return Collection<int, array{name: string, phone: ?string}>
     */
    private function students(?array $classIds = null, ?array $userIds = null): Collection
    {
        return StudentProfile::query()
            ->active()
            ->when($classIds !== null, fn ($query) => $query->whereIn('current_class_id', $classIds))
            ->when($userIds !== null, fn ($query) => $query->whereIn('user_id', $userIds))
            ->with('user:id,name,phone')
            ->get(['id', 'user_id', 'guardian_phone'])
            ->map(fn (StudentProfile $student): array => ['name' => (string) $student->user?->name, 'phone' => $this->studentPhone($student)]);
    }

    /**
     * @param  Builder<TeacherProfile>|Builder<StaffProfile>  $query
     * @return Collection<int, array{name: string, phone: ?string}>
     */
    private function employees(Builder $query): Collection
    {
        return $query->with('user:id,name,phone')
            ->get(['id', 'user_id'])
            ->map(fn (TeacherProfile|StaffProfile $profile): array => ['name' => (string) $profile->user?->name, 'phone' => $profile->user?->phone]);
    }
}
