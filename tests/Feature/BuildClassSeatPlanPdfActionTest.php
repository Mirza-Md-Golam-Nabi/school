<?php

use App\Actions\BuildClassSeatPlanPdfAction;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Pages\SeatPlan;
use App\Models\Classes;
use App\Models\Group;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\DocumentLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

function makeSeatPlanTestStudent(int $classId, int $rollNo, StudentStatus $status = StudentStatus::Active, ?int $groupId = null, ?int $sectionId = null): StudentProfile
{
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true, 'name' => "Student Roll {$rollNo}"]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => $rollNo,
        'current_class_id' => $classId,
        'current_group_id' => $groupId,
        'current_section_id' => $sectionId,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => $status,
    ]);
}

it('builds a seat plan pdf listing every active student in the class, sorted by roll no', function () {
    $class = Classes::create(['name' => 'Seat Plan Class', 'order' => 1]);

    makeSeatPlanTestStudent($class->id, 2);
    makeSeatPlanTestStudent($class->id, 1);
    makeSeatPlanTestStudent($class->id, 3);

    // A different, non-active student must be excluded.
    makeSeatPlanTestStudent($class->id, 4, StudentStatus::Graduated);

    $pdf = app(BuildClassSeatPlanPdfAction::class)->handle($class);

    expect($pdf)->toStartWith('%PDF');
});

it('includes group and section only when the student has them', function () {
    $class = Classes::create(['name' => 'Seat Plan Group Class', 'order' => 1]);
    $group = Group::create(['name' => 'Science']);
    $section = Section::create(['class_id' => $class->id, 'name' => 'A']);

    makeSeatPlanTestStudent($class->id, 1, StudentStatus::Active, $group->id, $section->id);
    makeSeatPlanTestStudent($class->id, 2);

    $pdf = app(BuildClassSeatPlanPdfAction::class)->handle($class);

    expect($pdf)->toStartWith('%PDF');
});

it('excludes students belonging to a different class', function () {
    $class = Classes::create(['name' => 'Seat Plan Class A', 'order' => 1]);
    $otherClass = Classes::create(['name' => 'Seat Plan Class B', 'order' => 2]);

    makeSeatPlanTestStudent($class->id, 1);
    makeSeatPlanTestStudent($otherClass->id, 1);

    $pdf = app(BuildClassSeatPlanPdfAction::class)->handle($class);

    expect($pdf)->toStartWith('%PDF');
});

it('aborts with 404 when the class has no active students', function () {
    $class = Classes::create(['name' => 'Seat Plan Empty Class', 'order' => 1]);

    expect(fn () => app(BuildClassSeatPlanPdfAction::class)->handle($class))
        ->toThrow(NotFoundHttpException::class);
});

it('streams the seat plan pdf as a download through the http route', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Seat Plan Route Class', 'order' => 1]);

    makeSeatPlanTestStudent($class->id, 1);

    $response = $this->actingAs($admin)->get(route('seat-plan.class.download', ['class' => $class->id]));

    $response->assertStatus(Response::HTTP_OK);
    $response->assertHeader('Content-Type', 'application/pdf');
});

it('renders the seat plan page listing active classes with a download link', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Seat Plan Page Class', 'order' => 1, 'is_active' => true]);
    $emptyClass = Classes::create(['name' => 'Seat Plan Empty Page Class', 'order' => 2, 'is_active' => true]);

    makeSeatPlanTestStudent($class->id, 1);

    $this->actingAs($admin)->get(SeatPlan::getUrl())
        ->assertOk()
        ->assertSee($class->name)
        ->assertSee($emptyClass->name)
        ->assertSee(e(route('seat-plan.class.download', ['class' => $class->id, 'language' => 'en'])), false);
});

/**
 * Render the seat plan view for one student in the given language.
 */
function renderSeatPlanHtml(StudentProfile $student, string $language): string
{
    $students = StudentProfile::with(['user', 'class', 'group', 'section'])->whereKey($student->id)->get();

    return DocumentLocale::using($language, fn (): string => view('documents.seat-plan', ['students' => $students])->render());
}

it('renders the seat plan labels, names and digits in bangla when the language is bn', function () {
    $class = Classes::create(['name' => 'Six', 'name_bn' => 'ষষ্ঠ', 'order' => 6]);
    $group = Group::create(['name' => 'Science', 'name_bn' => 'বিজ্ঞান']);
    $student = makeSeatPlanTestStudent($class->id, 7, StudentStatus::Active, $group->id);
    $student->update(['name_bn' => 'করিম']);

    expect(renderSeatPlanHtml($student, 'bn'))
        ->toContain(__('Seat Plan', [], 'bn'))
        ->toContain(__('Roll', [], 'bn'))
        ->toContain('০৭')
        ->toContain('করিম')
        ->toContain('ষষ্ঠ')
        ->toContain('বিজ্ঞান')
        ->not->toContain('Student Roll 7');
});

it('keeps the seat plan in english by default', function () {
    $class = Classes::create(['name' => 'Six', 'name_bn' => 'ষষ্ঠ', 'order' => 6]);
    $student = makeSeatPlanTestStudent($class->id, 7);
    $student->update(['name_bn' => 'করিম']);

    expect(renderSeatPlanHtml($student, 'en'))
        ->toContain('Seat Plan')
        ->toContain('Student Roll 7')
        ->toContain('07')
        ->not->toContain('করিম');
});

it('falls back to the english name in a bangla seat plan when name_bn is empty', function () {
    $class = Classes::create(['name' => 'Six', 'order' => 6]);
    $student = makeSeatPlanTestStudent($class->id, 3);

    expect(renderSeatPlanHtml($student, 'bn'))->toContain('Student Roll 3');
});

it('downloads a bangla seat plan pdf through the http route', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Seat Plan Bangla Class', 'order' => 1]);

    makeSeatPlanTestStudent($class->id, 1);

    $this->actingAs($admin)
        ->get(route('seat-plan.class.download', ['class' => $class->id, 'language' => 'bn']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', "attachment; filename=\"seat-plan-{$class->id}-bn.pdf\"");

    expect(app()->getLocale())->toBe('en');
});

it('treats an unknown seat plan language as english', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Seat Plan Unknown Lang Class', 'order' => 1]);

    makeSeatPlanTestStudent($class->id, 1);

    $this->actingAs($admin)
        ->get(route('seat-plan.class.download', ['class' => $class->id, 'language' => 'fr']))
        ->assertOk()
        ->assertHeader('Content-Disposition', "attachment; filename=\"seat-plan-{$class->id}.pdf\"");
});

it('switches the seat plan download links to bangla on the page', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Seat Plan Switch Class', 'order' => 1, 'is_active' => true]);

    makeSeatPlanTestStudent($class->id, 1);

    $this->actingAs($admin);

    Livewire::test(SeatPlan::class)
        ->assertSee(e(route('seat-plan.class.download', ['class' => $class->id, 'language' => 'en'])), false)
        ->set('language', 'bn')
        ->assertSee(e(route('seat-plan.class.download', ['class' => $class->id, 'language' => 'bn'])), false)
        ->assertSee('Download PDF')
        ->set('language', 'xx')
        ->assertSet('language', 'en');
});

it('prints the বাংলা school name, address and EIIN digits in a বাংলা seat plan', function () {
    SchoolSetting::set('school_name', 'Test School');
    SchoolSetting::set('school_address', 'Dhaka');
    SchoolSetting::set(SchoolSetting::SCHOOL_NAME_BN_SETTING, 'টেস্ট স্কুল');
    SchoolSetting::set(SchoolSetting::SCHOOL_ADDRESS_BN_SETTING, 'ঢাকা');
    SchoolSetting::set(SchoolSetting::EIIN_SETTING, '123456');

    $class = Classes::create(['name' => 'Six', 'order' => 6]);
    $student = makeSeatPlanTestStudent($class->id, 1);

    expect(renderSeatPlanHtml($student, 'bn'))
        ->toContain('টেস্ট স্কুল')
        ->toContain('ঢাকা')
        ->toContain('১২৩৪৫৬')
        ->not->toContain('Test School');

    expect(renderSeatPlanHtml($student, 'en'))
        ->toContain('Test School')
        ->toContain('Dhaka')
        ->toContain('123456')
        ->not->toContain('টেস্ট স্কুল');
});
