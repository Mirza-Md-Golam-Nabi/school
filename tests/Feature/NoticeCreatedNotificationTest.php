<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\NoticeTargetType;
use App\Enums\UserType;
use App\Filament\Resources\Notices\Pages\CreateNotice;
use App\Jobs\PublishNoticeJob;
use App\Models\Classes;
use App\Models\Notice;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\NoticeCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// createNoticeTestStudent() and createNoticeTestTeacher() are already
// declared globally in StudentNoticesTest.php / TeacherNoticesTest.php.

function createNoticeTestStaff(): StaffProfile
{
    return StaffProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Staff, 'is_active' => true])->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);
}

it('notifies every active student when a notice targets all students', function () {
    Notification::fake();

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 6', 'order' => 6, 'is_active' => true]);
    $student = createNoticeTestStudent($class);
    $teacher = createNoticeTestTeacher();

    Livewire::test(CreateNotice::class)
        ->fillForm([
            'title' => 'Holiday Notice',
            'body' => 'School will remain closed.',
            'target_type' => NoticeTargetType::Students->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    Notification::assertSentTo($student->user, NoticeCreatedNotification::class);
    Notification::assertNotSentTo($teacher->user, NoticeCreatedNotification::class);
});

it('notifies every active teacher when a notice targets all teachers', function () {
    Notification::fake();

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 6', 'order' => 6, 'is_active' => true]);
    $student = createNoticeTestStudent($class);
    $teacher = createNoticeTestTeacher();

    Livewire::test(CreateNotice::class)
        ->fillForm([
            'title' => 'Staff Meeting',
            'body' => 'Meeting at 4pm.',
            'target_type' => NoticeTargetType::Teachers->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    Notification::assertSentTo($teacher->user, NoticeCreatedNotification::class);
    Notification::assertNotSentTo($student->user, NoticeCreatedNotification::class);
});

it('notifies students, teachers, and staff — but not admins — when a notice targets everyone', function () {
    Notification::fake();

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 6', 'order' => 6, 'is_active' => true]);
    $student = createNoticeTestStudent($class);
    $teacher = createNoticeTestTeacher();
    $staff = createNoticeTestStaff();

    Livewire::test(CreateNotice::class)
        ->fillForm([
            'title' => 'Annual Function',
            'body' => 'Everyone is invited.',
            'target_type' => NoticeTargetType::All->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    Notification::assertSentTo($student->user, NoticeCreatedNotification::class);
    Notification::assertSentTo($teacher->user, NoticeCreatedNotification::class);
    Notification::assertSentTo($staff->user, NoticeCreatedNotification::class);
    Notification::assertNotSentTo($admin, NoticeCreatedNotification::class);
});

it('notifies only students in the targeted class(es) for a class-specific notice', function () {
    Notification::fake();

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 6', 'order' => 6, 'is_active' => true]);
    $otherClass = Classes::create(['name' => 'Class 7', 'order' => 7, 'is_active' => true]);
    $student = createNoticeTestStudent($class);
    $otherStudent = createNoticeTestStudent($otherClass);

    Livewire::test(CreateNotice::class)
        ->fillForm([
            'title' => 'Class Test',
            'body' => 'Test on Monday.',
            'target_type' => NoticeTargetType::ByClass->value,
            'target_ids' => [$class->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    Notification::assertSentTo($student->user, NoticeCreatedNotification::class);
    Notification::assertNotSentTo($otherStudent->user, NoticeCreatedNotification::class);
});

it('notifies only the selected individual student', function () {
    Notification::fake();

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 6', 'order' => 6, 'is_active' => true]);
    $student = createNoticeTestStudent($class);
    $otherStudent = createNoticeTestStudent($class);

    Livewire::test(CreateNotice::class)
        ->fillForm([
            'title' => 'Fee Reminder',
            'body' => 'Please clear your dues.',
            'target_type' => NoticeTargetType::IndividualStudent->value,
            'target_ids' => [$student->user_id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    Notification::assertSentTo($student->user, NoticeCreatedNotification::class);
    Notification::assertNotSentTo($otherStudent->user, NoticeCreatedNotification::class);
});

it('does not notify anyone at creation for a scheduled notice, only once its publish job runs', function () {
    Notification::fake();

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 6', 'order' => 6, 'is_active' => true]);
    $student = createNoticeTestStudent($class);

    Livewire::test(CreateNotice::class)
        ->fillForm([
            'title' => 'Upcoming Event',
            'body' => 'Details soon.',
            'target_type' => NoticeTargetType::Students->value,
            'published_at' => now()->addDay()->toDateTimeString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    Notification::assertNotSentTo($student->user, NoticeCreatedNotification::class);

    $notice = Notice::where('title', 'Upcoming Event')->firstOrFail();

    // Simulate time passing until the scheduled publish moment, then the
    // delayed job actually running.
    $notice->forceFill(['published_at' => now()->subMinute()])->save();
    (new PublishNoticeJob($notice->id))->handle();

    Notification::assertSentTo($student->user, NoticeCreatedNotification::class);
});
