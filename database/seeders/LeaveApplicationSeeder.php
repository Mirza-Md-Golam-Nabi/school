<?php

namespace Database\Seeders;

use App\Actions\Leave\ApproveLeaveApplicationAction;
use App\Actions\Leave\RejectLeaveApplicationAction;
use App\Enums\LeaveApplicationStatus;
use App\Enums\UserType;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\StaffProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\WorkingDaysCalculator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LeaveApplicationSeeder extends Seeder
{
    /** @var array<int, string> */
    private array $reasons = [
        'Family emergency.',
        'Not feeling well.',
        'Attending a family function.',
        'Personal work.',
        'Doctor appointment.',
        'Traveling to hometown.',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $leaveTypes = LeaveType::all();

        if ($leaveTypes->isEmpty()) {
            return;
        }

        $adminId = User::where('user_type', UserType::Admin)->value('id')
            ?? User::query()->value('id');

        if (! $adminId) {
            return;
        }

        $admin = User::findOrFail($adminId);
        $calculator = new WorkingDaysCalculator;
        $approveAction = new ApproveLeaveApplicationAction($calculator);
        $rejectAction = new RejectLeaveApplicationAction;

        $applicants = TeacherProfile::with('user')->active()->get()
            ->concat(StaffProfile::with('user')->active()->get());

        foreach ($applicants as $applicant) {
            if (! $applicant->user) {
                continue;
            }

            $count = fake()->numberBetween(1, 3);

            for ($i = 0; $i < $count; $i++) {
                $this->createLeaveApplication(
                    $applicant,
                    $applicant->user,
                    $leaveTypes,
                    $admin,
                    $calculator,
                    $approveAction,
                    $rejectAction
                );
            }
        }
    }

    /**
     * @param  TeacherProfile|StaffProfile  $applicant
     * @param  Collection<int, LeaveType>  $leaveTypes
     */
    private function createLeaveApplication(
        $applicant,
        User $applicantUser,
        Collection $leaveTypes,
        User $admin,
        WorkingDaysCalculator $calculator,
        ApproveLeaveApplicationAction $approveAction,
        RejectLeaveApplicationAction $rejectAction
    ): void {
        $leaveType = $leaveTypes->random();

        $fromDate = Carbon::yesterday()->subDays(fake()->numberBetween(5, 180));
        $toDate = $fromDate->copy()->addDays(fake()->numberBetween(1, 4));

        if ($toDate->greaterThan(Carbon::yesterday())) {
            $toDate = Carbon::yesterday()->copy();
        }

        $totalDays = max(1, $calculator->count($fromDate, $toDate));

        $application = LeaveApplication::create([
            'applicant_type' => $applicant::class,
            'applicant_id' => $applicant->id,
            'leave_type_id' => $leaveType->id,
            'from_date' => $fromDate->toDateString(),
            'to_date' => $toDate->toDateString(),
            'total_days' => $totalDays,
            'reason' => fake()->randomElement($this->reasons),
            'status' => LeaveApplicationStatus::Pending,
            'applied_by' => $applicantUser->id,
        ]);

        $outcome = fake()->randomElement(['approved', 'approved', 'approved', 'rejected', 'pending']);

        match ($outcome) {
            'approved' => $approveAction->handle($application, $admin, 'Approved.'),
            'rejected' => $rejectAction->handle($application, $admin, 'Not enough notice.'),
            default => null,
        };
    }
}
