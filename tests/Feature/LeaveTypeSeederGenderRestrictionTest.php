<?php

use App\Enums\Gender;
use App\Enums\LeaveApplicability;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\StaffProfile;
use App\Models\TeacherProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('restricts Maternity and Paternity leave to the correct gender, leaving Casual and Sick open to all', function () {
    $this->seed();

    expect(LeaveType::where('name', 'Casual Leave')->value('applicable_gender'))->toBe(LeaveApplicability::All)
        ->and(LeaveType::where('name', 'Sick Leave')->value('applicable_gender'))->toBe(LeaveApplicability::All)
        ->and(LeaveType::where('name', 'Maternity Leave')->value('applicable_gender'))->toBe(LeaveApplicability::Female)
        ->and(LeaveType::where('name', 'Paternity Leave')->value('applicable_gender'))->toBe(LeaveApplicability::Male);
});

it('never seeds a leave application pairing the wrong gender with Maternity or Paternity leave', function () {
    $this->seed();

    $maternityId = LeaveType::where('name', 'Maternity Leave')->value('id');
    $paternityId = LeaveType::where('name', 'Paternity Leave')->value('id');

    // applicant_id is polymorphic (paired with applicant_type), so teacher and staff
    // IDs must be checked against their own type — an id collision across the two
    // tables would otherwise produce a false positive or negative here.
    $wrongCount = 0;

    foreach ([TeacherProfile::class, StaffProfile::class] as $applicantType) {
        $maleIds = $applicantType::where('gender', Gender::Male)->pluck('id');
        $femaleIds = $applicantType::where('gender', Gender::Female)->pluck('id');

        $wrongCount += LeaveApplication::where('applicant_type', $applicantType)
            ->where('leave_type_id', $maternityId)
            ->whereIn('applicant_id', $maleIds)
            ->count();

        $wrongCount += LeaveApplication::where('applicant_type', $applicantType)
            ->where('leave_type_id', $paternityId)
            ->whereIn('applicant_id', $femaleIds)
            ->count();
    }

    expect($wrongCount)->toBe(0);
});
