<?php

use App\Actions\ExamNotifications\BuildExamNotificationPreviewAction;
use App\Actions\ExamNotifications\SendExamNotificationAction;
use App\Enums\ExamNotificationChannel;
use App\Enums\ExamNotificationKind;
use App\Enums\Gender;
use App\Enums\Permissions\AcademicPermission;
use App\Enums\SmsEvent;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Filament\Resources\Exams\Pages\EditExam;
use App\Filament\Resources\Exams\RelationManagers\SubjectConfigsRelationManager;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamNotificationBatch;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\Permission;
use App\Models\SchoolSetting;
use App\Models\SmsLog;
use App\Models\SmsSetting;
use App\Models\SmsVendor;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\User;
use App\Notifications\ExamMarksNotification;
use App\Notifications\ExamResultPublishedNotification;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    Http::preventStrayRequests();
    SchoolSetting::set('school_name', 'Ideal School');
    SmsVendor::factory()->create();
    Filament::setCurrentPanel('admin');

    $this->class = Classes::create(['name' => 'Class 8', 'order' => 8, 'is_active' => true]);
    $this->exam = Exam::create([
        'exam_type_id' => ExamType::create(['name' => 'Half Yearly', 'is_active' => true])->id,
        'class_id' => $this->class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => false,
    ]);
});

/**
 * @param  list<SmsEvent>  $events
 */
function enableExamSms(array $events, float $balance = 100): void
{
    $setting = SmsSetting::current();
    $setting->fill(['is_enabled' => true, 'rate_per_sms' => 0.25, 'enabled_events' => array_map(fn (SmsEvent $event) => $event->value, $events)])->save();
    $setting->forceFill(['balance' => $balance])->save();
}

function examNotificationStudent(Classes $class, string $name, ?string $guardianPhone = '01711111111', int $rollNo = 1): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create(['name' => $name, 'user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => $rollNo,
        'current_class_id' => $class->id,
        'guardian_phone' => $guardianPhone,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

function examNotificationSubject(Exam $exam, string $name, int $sortOrder): Subject
{
    $subject = Subject::create(['name' => $name, 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true, 'sort_order' => $sortOrder]);
    $subject->classes()->attach($exam->class_id, ['subject_type' => SubjectType::Compulsory->value]);

    ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'written_total' => 100,
        'total_marks' => 100,
        'pass_mark' => 33,
    ]);

    return $subject;
}

function examNotificationMarks(Exam $exam, Subject $subject, StudentProfile $student, ?float $marks, bool $isAbsent = false): StudentResult
{
    return StudentResult::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'class_id' => $exam->class_id,
        'student_id' => $student->id,
        'written_marks' => $marks,
        'is_absent' => $isAbsent,
    ]);
}

function examNotificationAdmin(bool $canSend = true): User
{
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);

    foreach ([AcademicPermission::VIEW_ACADEMIC, AcademicPermission::EDIT_ACADEMIC, ...($canSend ? [AcademicPermission::SEND_EXAM_NOTIFICATIONS] : [])] as $permission) {
        Permission::firstOrCreate(['name' => $permission->value, 'guard_name' => 'web']);
        $admin->givePermissionTo($permission->value);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $admin;
}

it('puts several subjects in one message per student, in subject order, with absent shown', function () {
    $math = examNotificationSubject($this->exam, 'Math', 2);
    $bangla = examNotificationSubject($this->exam, 'Bangla', 1);
    $rahim = examNotificationStudent($this->class, 'Rahim');
    examNotificationMarks($this->exam, $math, $rahim, 72);
    examNotificationMarks($this->exam, $bangla, $rahim, null, isAbsent: true);

    enableExamSms([SmsEvent::ExamSubjectMarks]);

    app(SendExamNotificationAction::class)->handle(
        $this->exam,
        ExamNotificationKind::SubjectMarks,
        [$math->id, $bangla->id],
        [ExamNotificationChannel::Push, ExamNotificationChannel::Sms],
    );

    $sms = SmsLog::sole();
    expect($sms->phone)->toBe('8801711111111')
        ->and($sms->event)->toBe(SmsEvent::ExamSubjectMarks)
        ->and($sms->message)->toContain('Rahim')
        ->and($sms->message)->toContain('Half Yearly')
        ->and($sms->message)->toContain('Bangla অনুপস্থিত, Math 72/100 (A)');

    Notification::assertSentTo($rahim->user, ExamMarksNotification::class, fn (ExamMarksNotification $notification): bool => $notification->kind === ExamNotificationKind::SubjectMarks
        && $notification->body === 'Half Yearly: Bangla অনুপস্থিত, Math 72/100 (A)');

    expect(SmsSetting::current()->currentBalance())->toBeLessThan(100.0);
});

it('sends push only by default and records the send', function () {
    $math = examNotificationSubject($this->exam, 'Math', 1);
    $student = examNotificationStudent($this->class, 'Rahim');
    examNotificationMarks($this->exam, $math, $student, 50);
    enableExamSms([SmsEvent::ExamSubjectMarks]);

    $admin = examNotificationAdmin();

    ['batch' => $batch] = app(SendExamNotificationAction::class)->handle(
        $this->exam,
        ExamNotificationKind::SubjectMarks,
        [$math->id],
        $this->exam->subjectMarksChannels(),
        $admin,
    );

    expect(SmsLog::count())->toBe(0)
        ->and($batch->subject_ids)->toBe([$math->id])
        ->and($batch->channels)->toBe(['push'])
        ->and($batch->push_count)->toBe(1)
        ->and($batch->sent_by)->toBe($admin->id);

    Notification::assertSentTo($student->user, ExamMarksNotification::class);
});

it('skips students without marks and leaves rows with no marks typed out', function () {
    $math = examNotificationSubject($this->exam, 'Math', 1);
    $rahim = examNotificationStudent($this->class, 'Rahim', rollNo: 1);
    $karim = examNotificationStudent($this->class, 'Karim', rollNo: 2);
    examNotificationStudent($this->class, 'Jamal', rollNo: 3);
    examNotificationMarks($this->exam, $math, $rahim, 40);
    examNotificationMarks($this->exam, $math, $karim, null);

    $preview = app(BuildExamNotificationPreviewAction::class)->handle($this->exam, ExamNotificationKind::SubjectMarks, [$math->id], [ExamNotificationChannel::Push]);

    expect($preview['recipients'])->toBe(1)
        ->and($preview['students_without_marks'])->toBe(2);
});

it('does not text when the SMS event is off in SMS Settings, but still pushes', function () {
    $math = examNotificationSubject($this->exam, 'Math', 1);
    $student = examNotificationStudent($this->class, 'Rahim');
    examNotificationMarks($this->exam, $math, $student, 50);
    enableExamSms([]);

    ['batch' => $batch] = app(SendExamNotificationAction::class)->handle(
        $this->exam,
        ExamNotificationKind::SubjectMarks,
        [$math->id],
        [ExamNotificationChannel::Push, ExamNotificationChannel::Sms],
    );

    expect(SmsLog::count())->toBe(0)
        ->and($batch->sms_status)->toBe('disabled');

    Notification::assertSentTo($student->user, ExamMarksNotification::class);
});

it('sends no SMS at all when the balance cannot cover every message', function () {
    $math = examNotificationSubject($this->exam, 'Math', 1);
    foreach ([1, 2] as $roll) {
        examNotificationMarks($this->exam, $math, examNotificationStudent($this->class, "Student {$roll}", "0171111111{$roll}", $roll), 50);
    }
    enableExamSms([SmsEvent::ExamSubjectMarks], balance: 0.3);

    ['batch' => $batch] = app(SendExamNotificationAction::class)->handle($this->exam, ExamNotificationKind::SubjectMarks, [$math->id], [ExamNotificationChannel::Sms]);

    expect(SmsLog::count())->toBe(0)
        ->and($batch->sms_status)->toBe('insufficient_balance')
        ->and(SmsSetting::current()->currentBalance())->toBe(0.3);
});

it('sends the complete result with GPA, grade, total and position', function () {
    $student = examNotificationStudent($this->class, 'Rahim');
    StudentMeritRanking::create([
        'exam_id' => $this->exam->id,
        'student_id' => $student->id,
        'class_id' => $this->class->id,
        'total_marks' => 650,
        'gpa' => 4.5,
        'class_rank' => 3,
    ]);
    enableExamSms([SmsEvent::ExamResult]);

    app(SendExamNotificationAction::class)->handle($this->exam, ExamNotificationKind::Result, [], $this->exam->resultChannels());

    expect(SmsLog::sole()->message)->toContain('GPA 4.50 (A)')
        ->and(SmsLog::sole()->message)->toContain('মোট নম্বর 650')
        ->and(SmsLog::sole()->message)->toContain('মেধাক্রম 3');

    Notification::assertSentTo($student->user, ExamMarksNotification::class, fn (ExamMarksNotification $notification): bool => $notification->kind === ExamNotificationKind::Result);
});

it('sends and records nothing for the result before rankings are calculated', function () {
    examNotificationStudent($this->class, 'Rahim');

    ['batch' => $batch] = app(SendExamNotificationAction::class)->handle($this->exam, ExamNotificationKind::Result, [], $this->exam->resultChannels());

    expect($batch)->toBeNull()
        ->and(ExamNotificationBatch::count())->toBe(0);
    Notification::assertNothingSent();
});

it('sends each student their result on publish once rankings exist', function () {
    test()->actingAs(grantSuperAdmin(examNotificationAdmin()));
    $student = examNotificationStudent($this->class, 'Rahim');
    StudentMeritRanking::create(['exam_id' => $this->exam->id, 'student_id' => $student->id, 'class_id' => $this->class->id, 'total_marks' => 500, 'gpa' => 4.0, 'class_rank' => 1]);

    Livewire::test(EditExam::class, ['record' => $this->exam->id])
        ->fillForm(['is_published' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    Notification::assertSentTo($student->user, ExamMarksNotification::class);
    Notification::assertNotSentTo($student->user, ExamResultPublishedNotification::class);
    expect(ExamNotificationBatch::sole()->kind)->toBe(ExamNotificationKind::Result);
});

it('sends nothing on publish when the exam says not to', function () {
    test()->actingAs(grantSuperAdmin(examNotificationAdmin()));
    $student = examNotificationStudent($this->class, 'Rahim');

    Livewire::test(EditExam::class, ['record' => $this->exam->id])
        ->fillForm(['is_published' => true, 'notify_result_on_publish' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    Notification::assertNothingSentTo($student->user);
    expect($this->exam->fresh()->notify_result_on_publish)->toBeFalse();
});

it('saves the exam notification channels and shows the defaults for an exam without a choice', function () {
    test()->actingAs(grantSuperAdmin(examNotificationAdmin()));

    Livewire::test(EditExam::class, ['record' => $this->exam->id])
        ->assertSchemaStateSet([
            'subject_marks_channels' => ['push'],
            'result_channels' => ['push', 'sms'],
        ])
        ->fillForm(['subject_marks_channels' => ['push', 'sms'], 'result_channels' => ['sms']])
        ->call('save')
        ->assertHasNoFormErrors();

    $exam = $this->exam->fresh();
    expect($exam->subjectMarksChannels())->toBe([ExamNotificationChannel::Push, ExamNotificationChannel::Sms])
        ->and($exam->resultChannels())->toBe([ExamNotificationChannel::Sms]);
});

it('lets an admin with the permission send one subject\'s marks from the subject list', function () {
    $math = examNotificationSubject($this->exam, 'Math', 1);
    $student = examNotificationStudent($this->class, 'Rahim');
    examNotificationMarks($this->exam, $math, $student, 80);
    test()->actingAs(examNotificationAdmin());

    $config = ExamSubjectConfig::query()->where('subject_id', $math->id)->sole();

    Livewire::test(SubjectConfigsRelationManager::class, ['ownerRecord' => $this->exam, 'pageClass' => EditExam::class])
        ->assertActionVisible(TestAction::make('sendSubjectMarks')->table($config))
        ->callAction(TestAction::make('sendSubjectMarks')->table($config), ['channels' => ['push']])
        ->assertHasNoFormErrors()
        ->assertNotified();

    Notification::assertSentTo($student->user, ExamMarksNotification::class);
    expect(ExamNotificationBatch::sole()->subject_ids)->toBe([$math->id]);
});

it('lets an admin send several subjects together from the subject list', function () {
    $math = examNotificationSubject($this->exam, 'Math', 1);
    $english = examNotificationSubject($this->exam, 'English', 2);
    $student = examNotificationStudent($this->class, 'Rahim');
    examNotificationMarks($this->exam, $math, $student, 80);
    examNotificationMarks($this->exam, $english, $student, 60);
    test()->actingAs(examNotificationAdmin());

    Livewire::test(SubjectConfigsRelationManager::class, ['ownerRecord' => $this->exam, 'pageClass' => EditExam::class])
        ->callAction(TestAction::make('sendSeveralSubjectMarks')->table(), ['subject_ids' => [$math->id, $english->id], 'channels' => ['push']])
        ->assertHasNoFormErrors();

    Notification::assertSentTo($student->user, ExamMarksNotification::class, fn (ExamMarksNotification $notification): bool => $notification->body === 'Half Yearly: Math 80/100 (A+), English 60/100 (A-)');
});

it('hides the send actions from an admin without the permission', function () {
    $math = examNotificationSubject($this->exam, 'Math', 1);
    test()->actingAs(examNotificationAdmin(canSend: false));

    $config = ExamSubjectConfig::query()->where('subject_id', $math->id)->sole();

    Livewire::test(SubjectConfigsRelationManager::class, ['ownerRecord' => $this->exam, 'pageClass' => EditExam::class])
        ->assertActionHidden(TestAction::make('sendSubjectMarks')->table($config))
        ->assertActionHidden(TestAction::make('sendSeveralSubjectMarks')->table());

    Livewire::test(EditExam::class, ['record' => $this->exam->id])
        ->assertActionHidden('sendResult');
});

it('sends the complete result from the exam page', function () {
    $student = examNotificationStudent($this->class, 'Rahim');
    StudentMeritRanking::create(['exam_id' => $this->exam->id, 'student_id' => $student->id, 'class_id' => $this->class->id, 'total_marks' => 500, 'gpa' => 4.0, 'class_rank' => 1]);
    test()->actingAs(examNotificationAdmin());

    Livewire::test(EditExam::class, ['record' => $this->exam->id])
        ->callAction('sendResult', ['channels' => ['push']])
        ->assertHasNoActionErrors()
        ->assertNotified();

    Notification::assertSentTo($student->user, ExamMarksNotification::class);
});
