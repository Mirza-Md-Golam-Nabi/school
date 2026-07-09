<?php

use App\Enums\Gender;
use App\Enums\NoticeTargetType;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Student\Pages\Notices;
use App\Filament\Student\Widgets\StudentNoticesOverview;
use App\Models\Classes;
use App\Models\Notice;
use App\Models\NoticeRead;
use App\Models\NoticeTarget;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createNoticeTestStudent(?Classes $class = null): StudentProfile
{
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 1,
        'current_class_id' => $class?->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

function createNoticeTestNotice(NoticeTargetType $targetType, ?Carbon $publishedAt = null): Notice
{
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);

    return Notice::create([
        'title' => 'Notice '.str()->random(6),
        'body' => '<p>Body content</p>',
        'target_type' => $targetType,
        'send_sms' => false,
        'published_at' => $publishedAt ?? now()->subHour(),
        'created_by' => $admin->id,
    ]);
}

it('shows notices targeted at everyone and all students', function () {
    $student = createNoticeTestStudent();
    $all = createNoticeTestNotice(NoticeTargetType::All);
    $students = createNoticeTestNotice(NoticeTargetType::Students);
    $teachers = createNoticeTestNotice(NoticeTargetType::Teachers);

    $visible = Notice::query()->visibleToStudent($student)->pluck('id');

    expect($visible)->toContain($all->id)
        ->toContain($students->id)
        ->not->toContain($teachers->id);
});

it('shows a class-targeted notice only to students in that class', function () {
    $classA = Classes::create(['name' => 'Class 1', 'order' => 1]);
    $classB = Classes::create(['name' => 'Class 2', 'order' => 2]);

    $studentInA = createNoticeTestStudent($classA);
    $studentInB = createNoticeTestStudent($classB);

    $notice = createNoticeTestNotice(NoticeTargetType::ByClass);
    NoticeTarget::create([
        'notice_id' => $notice->id,
        'targetable_type' => Classes::class,
        'targetable_id' => $classA->id,
    ]);

    expect(Notice::query()->visibleToStudent($studentInA)->pluck('id'))->toContain($notice->id)
        ->and(Notice::query()->visibleToStudent($studentInB)->pluck('id'))->not->toContain($notice->id);
});

it('shows an individually-targeted notice only to that student', function () {
    $targetStudent = createNoticeTestStudent();
    $otherStudent = createNoticeTestStudent();

    $notice = createNoticeTestNotice(NoticeTargetType::IndividualStudent);
    NoticeTarget::create([
        'notice_id' => $notice->id,
        'targetable_type' => User::class,
        'targetable_id' => $targetStudent->user_id,
    ]);

    expect(Notice::query()->visibleToStudent($targetStudent)->pluck('id'))->toContain($notice->id)
        ->and(Notice::query()->visibleToStudent($otherStudent)->pluck('id'))->not->toContain($notice->id);
});

it('excludes unpublished (draft or scheduled) notices', function () {
    $student = createNoticeTestStudent();
    $draft = createNoticeTestNotice(NoticeTargetType::All, null);
    $draft->update(['published_at' => null]);
    $scheduled = createNoticeTestNotice(NoticeTargetType::All, now()->addDay());

    $visible = Notice::query()->published()->visibleToStudent($student)->pluck('id');

    expect($visible)->not->toContain($draft->id)
        ->not->toContain($scheduled->id);
});

it('shows the latest 3 notices on the dashboard widget, most recent first', function () {
    $student = createNoticeTestStudent();
    $old = createNoticeTestNotice(NoticeTargetType::All, now()->subDays(3));
    $mid = createNoticeTestNotice(NoticeTargetType::All, now()->subDays(2));
    $new = createNoticeTestNotice(NoticeTargetType::All, now()->subDay());
    createNoticeTestNotice(NoticeTargetType::All, now()->subDays(4)); // 4th oldest, should be excluded

    $this->actingAs($student->user);

    $data = (new StudentNoticesOverview)->getViewData();

    expect($data['notices'])->toHaveCount(3)
        ->and($data['notices'][0]->id)->toBe($new->id)
        ->and($data['notices'][1]->id)->toBe($mid->id)
        ->and($data['notices'][2]->id)->toBe($old->id)
        ->and($data['url'])->toBe(Notices::getUrl(panel: 'student'));
});

it('marks a notice as read and reflects it in is_read', function () {
    $student = createNoticeTestStudent();
    $notice = createNoticeTestNotice(NoticeTargetType::All);

    $this->actingAs($student->user);

    $widget = new StudentNoticesOverview;
    expect($widget->getViewData()['notices'][0]->is_read)->toBeFalsy();

    $widget->markAsRead($notice->id);

    expect(NoticeRead::where('notice_id', $notice->id)->where('user_id', $student->user_id)->exists())->toBeTrue()
        ->and($widget->getViewData()['notices'][0]->is_read)->toBeTruthy();
});

it('refuses to mark or view a notice not visible to the student', function () {
    $classA = Classes::create(['name' => 'Class 3', 'order' => 3]);
    $classB = Classes::create(['name' => 'Class 4', 'order' => 4]);

    $studentInB = createNoticeTestStudent($classB);

    $notice = createNoticeTestNotice(NoticeTargetType::ByClass);
    NoticeTarget::create([
        'notice_id' => $notice->id,
        'targetable_type' => Classes::class,
        'targetable_id' => $classA->id,
    ]);

    $this->actingAs($studentInB->user);

    expect(fn () => (new StudentNoticesOverview)->markAsRead($notice->id))
        ->toThrow(ModelNotFoundException::class);
});

it('paginates all visible notices on the notices page', function () {
    $student = createNoticeTestStudent();

    foreach (range(1, 12) as $i) {
        createNoticeTestNotice(NoticeTargetType::All, now()->subMinutes($i));
    }

    $this->actingAs($student->user);

    $notices = (new Notices)->getNotices();

    expect($notices->total())->toBe(12)
        ->and($notices->perPage())->toBe(10)
        ->and($notices->count())->toBe(10);
});

it('renders the notices widget on the student dashboard with bold unread titles', function () {
    $student = createNoticeTestStudent();
    $notice = createNoticeTestNotice(NoticeTargetType::All);

    $response = $this->actingAs($student->user)->get('/student');

    $response->assertOk();
    $response->assertSee($notice->title);
    $response->assertSee('font-bold', false);
});

it('renders the full notices page', function () {
    $student = createNoticeTestStudent();
    $notice = createNoticeTestNotice(NoticeTargetType::All);

    $response = $this->actingAs($student->user)->get(Notices::getUrl(panel: 'student'));

    $response->assertOk();
    $response->assertSee($notice->title);
});
