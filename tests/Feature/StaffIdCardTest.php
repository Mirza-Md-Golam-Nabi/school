<?php

use App\Actions\BuildStaffIdCardsPdfAction;
use App\Enums\EmploymentStatus;
use App\Enums\StaffIdCardField;
use App\Enums\StudentIdCardTemplate;
use App\Enums\UserType;
use App\Filament\Pages\StaffIdCards;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\StaffIdCardLayout;
use App\Support\TeacherIdCardLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 * @param  array<string, mixed>  $userAttributes
 */
function makeIdCardTestStaff(array $attributes = [], array $userAttributes = []): StaffProfile
{
    $user = User::factory()->create([
        'user_type' => UserType::Staff,
        'is_active' => true,
        ...$userAttributes,
    ]);

    return StaffProfile::factory()->create(['user_id' => $user->id, ...$attributes]);
}

function makeStaffCardTestAdmin(): User
{
    return grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
}

it('builds a pdf for every active staff member', function () {
    makeIdCardTestStaff(['designation' => 'Office Assistant', 'joining_date' => '2020-01-01']);
    makeIdCardTestStaff(['status' => EmploymentStatus::Terminated]);

    expect(app(BuildStaffIdCardsPdfAction::class)->handle())->toStartWith('%PDF');
});

it('refuses to build a pdf when there is no active staff member', function () {
    makeIdCardTestStaff(['status' => EmploymentStatus::Resigned]);

    expect(fn () => app(BuildStaffIdCardsPdfAction::class)->handle())
        ->toThrow(NotFoundHttpException::class);
});

it('prints the staff card sheet with the staff role, details and qr', function () {
    $staff = makeIdCardTestStaff(['designation' => 'Accountant', 'joining_date' => '2018-03-01'], ['name' => 'Nasir Ahmed', 'phone' => '01811000000']);

    $html = view('documents.employee-id-card-sheet', [
        'role' => 'STAFF',
        'school' => ['name' => 'Test School', 'address' => '', 'logo' => null, 'signature' => null],
        'frontBackground' => 'front.svg',
        'backBackground' => 'back.svg',
        'cardWidth' => 54.0,
        'cardHeight' => 85.6,
        'frontFields' => StaffIdCardLayout::frontFields(),
        'backFields' => StaffIdCardLayout::backFields(),
        'issueDate' => StaffIdCardLayout::issueDate(),
        'cards' => collect([['person' => $staff->load(['user', 'addresses']), 'photo' => 'photo.png', 'x' => 0, 'y' => 0]]),
    ])->render();

    expect($html)
        ->toContain('Nasir Ahmed')
        ->toContain('STAFF ID CARD')
        ->toContain('Accountant')
        ->toContain('01811000000')
        ->toContain('SINCE')
        ->toContain($staff->idCardNumber())
        ->toContain('type="QR"');
});

it('gives staff an id number that cannot clash with a student or teacher id', function () {
    $staff = makeIdCardTestStaff();

    expect($staff->idCardNumber())->toStartWith('S')
        ->and(StaffIdCardField::IdNo->valueFor($staff))->toBe($staff->idCardNumber());
});

it('lets admins download every staff card and view a single one', function () {
    $staff = makeIdCardTestStaff();
    $admin = makeStaffCardTestAdmin();

    $this->actingAs($admin)->get(route('staff-id-cards.download'))
        ->assertSuccessful()
        ->assertHeader('Content-Disposition', 'attachment; filename="staff-id-cards.pdf"');

    $this->actingAs($admin)->get(route('staff-id-cards.view'))
        ->assertSuccessful()
        ->assertHeader('Content-Disposition', 'inline; filename="staff-id-cards.pdf"');

    $this->actingAs($admin)->get(route('staff-id-cards.staff.view', ['staff' => $staff->id]))
        ->assertSuccessful()
        ->assertHeader('Content-Disposition', "inline; filename=\"staff-id-card-{$staff->id}.pdf\"");
});

it('keeps staff cards away from people outside the admin panel', function () {
    $staff = makeIdCardTestStaff();
    $teacher = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]);

    $this->actingAs($teacher)->get(route('staff-id-cards.staff.view', ['staff' => $staff->id]))
        ->assertForbidden();

    $this->actingAs($teacher)->get(route('staff-id-cards.download'))
        ->assertForbidden();
});

it('keeps the staff card design separate from the teacher card design', function () {
    expect(StaffIdCardLayout::template())->toBe(StudentIdCardTemplate::Emerald);

    $this->actingAs(makeStaffCardTestAdmin());

    Livewire::test(StaffIdCards::class)
        ->call('selectTemplate', StudentIdCardTemplate::Sunset->value)
        ->call('submitDesign')
        ->assertNotified();

    expect(StaffIdCardLayout::template())->toBe(StudentIdCardTemplate::Sunset)
        ->and(TeacherIdCardLayout::template())->toBe(StudentIdCardTemplate::Midnight);
});

it('renders the staff id card page with each staff member and a link to their card', function () {
    $staff = makeIdCardTestStaff([], ['name' => 'Rafiq Mia']);
    StaffIdCardLayout::submitDesign(StudentIdCardTemplate::Emerald, null);

    $this->actingAs(makeStaffCardTestAdmin())->get(StaffIdCards::getUrl())
        ->assertSuccessful()
        ->assertSee('Rafiq Mia')
        ->assertSee('Staff Name')
        ->assertSee(route('staff-id-cards.staff.view', ['staff' => $staff->id]), false);
});

it('saves the staff card fields, issue date and qr setting from the card fields modal', function () {
    $this->actingAs(makeStaffCardTestAdmin());

    Livewire::test(StaffIdCards::class)
        ->mountAction('configureCardFields')
        ->set('mountedActions.0.data.front_fields', ['designation', 'id_no'])
        ->set('mountedActions.0.data.front_order', ['a' => ['field' => 'designation'], 'b' => ['field' => 'id_no']])
        ->set('mountedActions.0.data.back_fields', ['phone'])
        ->set('mountedActions.0.data.back_order', ['c' => ['field' => 'phone']])
        ->set('mountedActions.0.data.issue_date', '2026-01-01')
        ->set('mountedActions.0.data.show_qr_code', false)
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(StaffIdCardLayout::frontFields())->toBe([StaffIdCardField::Designation, StaffIdCardField::IdNo])
        ->and(StaffIdCardLayout::backFields())->toBe([StaffIdCardField::Phone])
        ->and(StaffIdCardLayout::issueDate()->toDateString())->toBe('2026-01-01')
        ->and(StaffIdCardLayout::showsQrCode())->toBeFalse();
});
