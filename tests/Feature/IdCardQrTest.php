<?php

use App\Actions\BuildClassStudentIdCardsPdfAction;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\StudentIdCardTemplate;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Pages\StudentIdCards;
use App\Filament\Resources\StaffProfiles\Pages\EditStaffProfile;
use App\Filament\Resources\StudentProfiles\Pages\EditStudentProfile;
use App\Filament\Resources\TeacherProfiles\Pages\EditTeacherProfile;
use App\Models\Classes;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Support\StudentIdCardLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeQrTestStudent(StudentStatus $status = StudentStatus::Active): StudentProfile
{
    $class = Classes::firstOrCreate(['name' => 'Class Seven'], ['order' => 7]);
    $user = User::factory()->create(['name' => 'Sadia Islam', 'user_type' => UserType::Student, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 9,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Female,
        'status' => $status,
    ]);
}

function makeQrTestTeacher(array $attributes = []): TeacherProfile
{
    $user = User::factory()->create(['name' => 'Kamal Hossain', 'user_type' => UserType::Teacher, 'is_active' => true]);

    return TeacherProfile::factory()->create(['user_id' => $user->id, 'designation' => 'Senior Teacher', ...$attributes]);
}

function makeQrTestStaff(array $attributes = []): StaffProfile
{
    $user = User::factory()->create(['name' => 'Jamal Mia', 'user_type' => UserType::Staff, 'is_active' => true]);

    return StaffProfile::factory()->create(['user_id' => $user->id, 'designation' => 'Office Assistant', ...$attributes]);
}

function makeQrTestAdmin(): User
{
    return grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
}

it('gives every new student, teacher and staff profile its own id card token', function () {
    $profiles = [makeQrTestStudent(), makeQrTestTeacher(), makeQrTestStaff()];

    foreach ($profiles as $profile) {
        expect($profile->id_card_token)->toHaveLength(16)
            ->and($profile->idCardVerificationUrl())->toBe(route('id-cards.verify', ['token' => $profile->id_card_token]));
    }

    expect(collect($profiles)->pluck('id_card_token')->unique())->toHaveCount(3);
});

it('shows an active student with their current class on the public page', function () {
    $student = makeQrTestStudent();

    $this->get($student->idCardVerificationUrl())
        ->assertOk()
        ->assertSee('status status--ok', false)
        ->assertSee('Sadia Islam')
        ->assertSee($student->idCardNumber())
        ->assertSee('Class Seven')
        ->assertSee('noindex', false);
});

it('flags the card of a student who has left and hides their old class', function () {
    $student = makeQrTestStudent(StudentStatus::Transferred);

    $this->get($student->idCardVerificationUrl())
        ->assertOk()
        ->assertSee('status status--bad', false)
        ->assertDontSee('status status--ok', false)
        ->assertSee('Sadia Islam')
        ->assertDontSee('Class Seven');
});

it('shows an active teacher and staff member with their post', function () {
    $this->get(makeQrTestTeacher()->idCardVerificationUrl())
        ->assertOk()
        ->assertSee('status status--ok', false)
        ->assertSee('Kamal Hossain')
        ->assertSee('Senior Teacher');

    $this->get(makeQrTestStaff()->idCardVerificationUrl())
        ->assertOk()
        ->assertSee('status status--ok', false)
        ->assertSee('Jamal Mia')
        ->assertSee('Office Assistant');
});

it('flags the card of a teacher who was removed rather than calling it forged', function () {
    $teacher = makeQrTestTeacher();
    $url = $teacher->idCardVerificationUrl();

    $teacher->delete();

    $this->get($url)
        ->assertOk()
        ->assertSee('status status--bad', false)
        ->assertSee('Kamal Hossain');
});

it('flags the card of a resigned staff member', function () {
    $staff = makeQrTestStaff(['status' => EmploymentStatus::Resigned]);

    $this->get($staff->idCardVerificationUrl())
        ->assertOk()
        ->assertSee('status status--bad', false);
});

it('answers not found for an unknown token and never shows contact details', function () {
    $this->get(route('id-cards.verify', ['token' => 'NoSuchToken12345']))
        ->assertNotFound()
        ->assertSee('status status--bad', false);

    $teacher = makeQrTestTeacher();
    $teacher->user->update(['phone' => '01799887766']);

    $this->get($teacher->idCardVerificationUrl())
        ->assertOk()
        ->assertDontSee('01799887766');
});

it('voids a lost card when the qr is reissued from the profile', function (string $page, Closure $makeProfile) {
    $profile = $makeProfile();
    $oldUrl = $profile->idCardVerificationUrl();

    $this->actingAs(makeQrTestAdmin());

    Livewire::test($page, ['record' => $profile->getRouteKey()])
        ->callAction('reissueIdCardQr')
        ->assertNotified();

    $profile->refresh();

    expect($profile->idCardVerificationUrl())->not->toBe($oldUrl);

    $this->get($oldUrl)->assertNotFound();
    $this->get($profile->idCardVerificationUrl())->assertOk();
})->with([
    'student' => [EditStudentProfile::class, fn (): StudentProfile => makeQrTestStudent()],
    'teacher' => [EditTeacherProfile::class, fn (): TeacherProfile => makeQrTestTeacher()],
    'staff' => [EditStaffProfile::class, fn (): StaffProfile => makeQrTestStaff()],
]);

it('prints the verification qr instead of a barcode on the student card', function () {
    $student = makeQrTestStudent()->load(['user', 'class', 'section', 'group', 'addresses']);

    $render = fn (bool $showQrCode): string => view('documents.student-id-card-sheet', [
        'school' => ['name' => 'Test School', 'address' => '', 'logo' => null, 'signature' => null],
        'frontBackground' => 'front.svg',
        'backBackground' => 'back.svg',
        'cardWidth' => 54.0,
        'cardHeight' => 85.6,
        'frontFields' => StudentIdCardLayout::frontFields(),
        'backFields' => StudentIdCardLayout::backFields(),
        'validity' => StudentIdCardLayout::validity(),
        'issueDate' => StudentIdCardLayout::issueDate(),
        'showQrCode' => $showQrCode,
        'cards' => collect([['student' => $student, 'photo' => 'photo.png', 'x' => 0, 'y' => 0]]),
    ])->render();

    expect($render(true))
        ->toContain('type="QR"')
        ->toContain(e($student->idCardVerificationUrl()))
        ->toContain($student->idCardNumber())
        ->not->toContain('C128B');

    expect($render(false))
        ->not->toContain('<barcode')
        ->toContain($student->idCardNumber());
});

it('builds the student card pdf with the qr code', function () {
    $student = makeQrTestStudent();

    expect(app(BuildClassStudentIdCardsPdfAction::class)->handle($student->class))->toStartWith('%PDF');
});

it('turns the qr off and on from the student card fields modal', function () {
    expect(StudentIdCardLayout::showsQrCode())->toBeTrue();

    $this->actingAs(makeQrTestAdmin());
    StudentIdCardLayout::submitDesign(StudentIdCardTemplate::Royal, null);

    Livewire::test(StudentIdCards::class)
        ->mountAction('configureCardFields')
        ->set('mountedActions.0.data.show_qr_code', false)
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect(StudentIdCardLayout::showsQrCode())->toBeFalse();
});
