<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\NoticeTargetType;
use App\Enums\UserType;
use App\Filament\Teacher\Pages\Notices;
use App\Filament\Teacher\Widgets\TeacherNoticesOverview;
use App\Models\Notice;
use App\Models\NoticeRead;
use App\Models\NoticeTarget;
use App\Models\TeacherProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createNoticeTestTeacher(): TeacherProfile
{
    $user = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]);

    return TeacherProfile::create([
        'user_id' => $user->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);
}

function createTeacherNoticeTestNotice(NoticeTargetType $targetType, ?Carbon $publishedAt = null): Notice
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

it('shows notices targeted at everyone and all teachers', function () {
    $teacher = createNoticeTestTeacher();
    $all = createTeacherNoticeTestNotice(NoticeTargetType::All);
    $teachers = createTeacherNoticeTestNotice(NoticeTargetType::Teachers);
    $students = createTeacherNoticeTestNotice(NoticeTargetType::Students);

    $visible = Notice::query()->visibleToTeacher($teacher)->pluck('id');

    expect($visible)->toContain($all->id)
        ->toContain($teachers->id)
        ->not->toContain($students->id);
});

it('shows an individually-targeted notice only to that teacher', function () {
    $targetTeacher = createNoticeTestTeacher();
    $otherTeacher = createNoticeTestTeacher();

    $notice = createTeacherNoticeTestNotice(NoticeTargetType::IndividualTeacher);
    NoticeTarget::create([
        'notice_id' => $notice->id,
        'targetable_type' => User::class,
        'targetable_id' => $targetTeacher->user_id,
    ]);

    expect(Notice::query()->visibleToTeacher($targetTeacher)->pluck('id'))->toContain($notice->id)
        ->and(Notice::query()->visibleToTeacher($otherTeacher)->pluck('id'))->not->toContain($notice->id);
});

it('excludes notices published in a previous year', function () {
    $teacher = createNoticeTestTeacher();
    $thisYear = createTeacherNoticeTestNotice(NoticeTargetType::All, now()->subDay());
    $lastYear = createTeacherNoticeTestNotice(NoticeTargetType::All, now()->subYear());

    $visible = Notice::query()->visibleToTeacher($teacher)->pluck('id');

    expect($visible)->toContain($thisYear->id)
        ->not->toContain($lastYear->id);
});

it('refuses to view a previous year notice detail even if otherwise visible', function () {
    $teacher = createNoticeTestTeacher();
    $lastYear = createTeacherNoticeTestNotice(NoticeTargetType::All, now()->subYear());

    $this->actingAs($teacher->user);

    expect(fn () => (new TeacherNoticesOverview)->markAsRead($lastYear->id))
        ->toThrow(ModelNotFoundException::class);
});

it('excludes unpublished (draft or scheduled) notices', function () {
    $teacher = createNoticeTestTeacher();
    $draft = createTeacherNoticeTestNotice(NoticeTargetType::All, null);
    $draft->update(['published_at' => null]);
    $scheduled = createTeacherNoticeTestNotice(NoticeTargetType::All, now()->addDay());

    $visible = Notice::query()->published()->visibleToTeacher($teacher)->pluck('id');

    expect($visible)->not->toContain($draft->id)
        ->not->toContain($scheduled->id);
});

it('shows the latest 3 notices on the dashboard widget, most recent first', function () {
    $teacher = createNoticeTestTeacher();
    $old = createTeacherNoticeTestNotice(NoticeTargetType::All, now()->subDays(3));
    $mid = createTeacherNoticeTestNotice(NoticeTargetType::All, now()->subDays(2));
    $new = createTeacherNoticeTestNotice(NoticeTargetType::All, now()->subDay());
    createTeacherNoticeTestNotice(NoticeTargetType::All, now()->subDays(4)); // 4th oldest, should be excluded

    $this->actingAs($teacher->user);

    $data = (new TeacherNoticesOverview)->getViewData();

    expect($data['notices'])->toHaveCount(3)
        ->and($data['notices'][0]->id)->toBe($new->id)
        ->and($data['notices'][1]->id)->toBe($mid->id)
        ->and($data['notices'][2]->id)->toBe($old->id)
        ->and($data['url'])->toBe(Notices::getUrl(panel: 'teacher'));
});

it('marks a notice as read and reflects it in is_read', function () {
    $teacher = createNoticeTestTeacher();
    $notice = createTeacherNoticeTestNotice(NoticeTargetType::All);

    $this->actingAs($teacher->user);

    $widget = new TeacherNoticesOverview;
    expect($widget->getViewData()['notices'][0]->is_read)->toBeFalsy();

    $widget->markAsRead($notice->id);

    expect(NoticeRead::where('notice_id', $notice->id)->where('user_id', $teacher->user_id)->exists())->toBeTrue()
        ->and($widget->getViewData()['notices'][0]->is_read)->toBeTruthy();
});

it('refuses to mark or view a notice not visible to the teacher', function () {
    $targetTeacher = createNoticeTestTeacher();
    $otherTeacher = createNoticeTestTeacher();

    $notice = createTeacherNoticeTestNotice(NoticeTargetType::IndividualTeacher);
    NoticeTarget::create([
        'notice_id' => $notice->id,
        'targetable_type' => User::class,
        'targetable_id' => $targetTeacher->user_id,
    ]);

    $this->actingAs($otherTeacher->user);

    expect(fn () => (new TeacherNoticesOverview)->markAsRead($notice->id))
        ->toThrow(ModelNotFoundException::class);
});

it('paginates all visible notices on the notices page', function () {
    $teacher = createNoticeTestTeacher();

    foreach (range(1, 12) as $i) {
        createTeacherNoticeTestNotice(NoticeTargetType::All, now()->subMinutes($i));
    }

    $this->actingAs($teacher->user);

    $notices = (new Notices)->getNotices();

    expect($notices->total())->toBe(12)
        ->and($notices->perPage())->toBe(10)
        ->and($notices->count())->toBe(10);
});

it('renders the notices widget on the teacher dashboard with bold unread titles', function () {
    $teacher = createNoticeTestTeacher();
    $notice = createTeacherNoticeTestNotice(NoticeTargetType::All);

    $response = $this->actingAs($teacher->user)->get('/teacher');

    $response->assertOk();
    $response->assertSee($notice->title);
    $response->assertSee('font-bold', false);
});

it('renders the full notices page', function () {
    $teacher = createNoticeTestTeacher();
    $notice = createTeacherNoticeTestNotice(NoticeTargetType::All);

    $response = $this->actingAs($teacher->user)->get(Notices::getUrl(panel: 'teacher'));

    $response->assertOk();
    $response->assertSee($notice->title);
});
