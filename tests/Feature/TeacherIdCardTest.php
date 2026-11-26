<?php

use App\Actions\BuildTeacherIdCardsPdfAction;
use App\Enums\BloodGroup;
use App\Enums\EmploymentStatus;
use App\Enums\StudentIdCardTemplate;
use App\Enums\TeacherIdCardField;
use App\Enums\UserType;
use App\Filament\Pages\TeacherIdCards;
use App\Filament\Teacher\Pages\Auth\EditProfile;
use App\Filament\Teacher\Pages\MyIdCard;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Support\IdCardPhotoPosition;
use App\Support\IdCardPhotoRenderer;
use App\Support\StudentIdCardLayout;
use App\Support\TeacherIdCardLayout;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 * @param  array<string, mixed>  $userAttributes
 */
function makeIdCardTestTeacher(array $attributes = [], array $userAttributes = []): TeacherProfile
{
    $user = User::factory()->create([
        'user_type' => UserType::Teacher,
        'is_active' => true,
        ...$userAttributes,
    ]);

    return TeacherProfile::factory()->create(['user_id' => $user->id, ...$attributes]);
}

function makeIdCardTestAdmin(): User
{
    return grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
}

/**
 * Decode a rendered portrait data URI back into a GD image.
 */
function decodeIdCardPortrait(string $dataUri): GdImage
{
    return imagecreatefromstring(base64_decode(Str::after($dataUri, 'base64,')));
}

it('builds a pdf for every active teacher using their profile photo', function () {
    Storage::fake('public');

    $avatar = UploadedFile::fake()->image('teacher.jpg', 300, 450)->store('teachers', 'public');

    makeIdCardTestTeacher(['designation' => 'Assistant Teacher', 'blood_group' => BloodGroup::OPlus, 'joining_date' => '2019-01-10'], ['avatar' => $avatar]);
    makeIdCardTestTeacher();
    makeIdCardTestTeacher(['status' => EmploymentStatus::Terminated]);

    expect(app(BuildTeacherIdCardsPdfAction::class)->handle())->toStartWith('%PDF');
});

it('refuses to build a pdf when there is no active teacher', function () {
    makeIdCardTestTeacher(['status' => EmploymentStatus::Terminated]);

    expect(fn () => app(BuildTeacherIdCardsPdfAction::class)->handle())
        ->toThrow(NotFoundHttpException::class);
});

it('prints the teacher card sheet with the teacher details and role', function () {
    $teacher = makeIdCardTestTeacher(['designation' => 'Senior Teacher', 'joining_date' => '2015-06-01'], ['name' => 'Rahima Khatun', 'phone' => '01711000000']);

    $html = view('documents.employee-id-card-sheet', [
        'role' => 'TEACHER',
        'school' => ['name' => 'Test School', 'address' => '', 'logo' => null, 'signature' => null],
        'frontBackground' => 'front.svg',
        'backBackground' => 'back.svg',
        'cardWidth' => 54.0,
        'cardHeight' => 85.6,
        'frontFields' => TeacherIdCardLayout::frontFields(),
        'backFields' => TeacherIdCardLayout::backFields(),
        'issueDate' => TeacherIdCardLayout::issueDate(),
        'cards' => collect([['person' => $teacher->load(['user', 'addresses']), 'photo' => 'photo.png', 'x' => 0, 'y' => 0]]),
    ])->render();

    expect($html)
        ->toContain('Rahima Khatun')
        ->toContain('TEACHER ID CARD')
        ->toContain('Senior Teacher')
        ->toContain('01711000000')
        ->toContain('SINCE')
        ->toContain($teacher->idCardNumber());
});

it('gives teachers an id number that cannot clash with a student id', function () {
    $teacher = makeIdCardTestTeacher();

    expect($teacher->idCardNumber())->toStartWith('T')
        ->and(TeacherIdCardField::IdNo->valueFor($teacher))->toBe($teacher->idCardNumber());
});

it('crops the portrait where the saved position asks for', function () {
    Storage::fake('public');

    // Left half red, right half blue: a square cut from the far left is all red,
    // one from the far right all blue.
    $image = imagecreatetruecolor(600, 300);
    imagefilledrectangle($image, 0, 0, 299, 299, imagecolorallocate($image, 255, 0, 0));
    imagefilledrectangle($image, 300, 0, 599, 299, imagecolorallocate($image, 0, 0, 255));
    ob_start();
    imagepng($image);
    Storage::disk('public')->put('teachers/wide.png', ob_get_clean());

    $renderer = app(IdCardPhotoRenderer::class);

    $left = decodeIdCardPortrait($renderer->render('teachers/wide.png', position: new IdCardPhotoPosition(0, 50, 1)));
    $right = decodeIdCardPortrait($renderer->render('teachers/wide.png', position: new IdCardPhotoPosition(100, 50, 1)));

    expect(imagecolorsforindex($left, imagecolorat($left, 150, 150))['red'])->toBe(255)
        ->and(imagecolorsforindex($right, imagecolorat($right, 150, 150))['blue'])->toBe(255);
});

it('clamps an out of range photo position and falls back to the default', function () {
    $position = IdCardPhotoPosition::fromArray(['x' => 150, 'y' => -20, 'zoom' => 9]);

    expect($position->toArray())->toBe(['x' => 100.0, 'y' => 0.0, 'zoom' => 3.0])
        ->and(IdCardPhotoPosition::fromArray(null)->cropFor(300, 450))->toBe(['x' => 0, 'y' => 50, 'edge' => 300])
        ->and((new IdCardPhotoPosition(50, 50, 2))->cropFor(400, 400))->toBe(['x' => 100, 'y' => 100, 'edge' => 200]);
});

it('lets admins download every teacher card and view a single one', function () {
    $teacher = makeIdCardTestTeacher();
    $admin = makeIdCardTestAdmin();

    $this->actingAs($admin)->get(route('teacher-id-cards.download'))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="teacher-id-cards.pdf"');

    $this->actingAs($admin)->get(route('teacher-id-cards.view'))
        ->assertSuccessful()
        ->assertHeader('Content-Disposition', 'inline; filename="teacher-id-cards.pdf"');

    $this->actingAs($admin)->get(route('teacher-id-cards.teacher.view', ['teacher' => $teacher->id]))
        ->assertSuccessful()
        ->assertHeader('Content-Disposition', "inline; filename=\"teacher-id-card-{$teacher->id}.pdf\"");
});

it('lets a teacher open only their own card', function () {
    $teacher = makeIdCardTestTeacher();
    $colleague = makeIdCardTestTeacher();

    $this->actingAs($teacher->user)->get(route('teacher-id-cards.teacher.view', ['teacher' => $teacher->id]))
        ->assertSuccessful();

    $this->actingAs($teacher->user)->get(route('teacher-id-cards.teacher.view', ['teacher' => $colleague->id]))
        ->assertForbidden();

    $this->actingAs($teacher->user)->get(route('teacher-id-cards.download'))
        ->assertForbidden();
});

it('keeps the teacher card design separate from the student card design', function () {
    expect(TeacherIdCardLayout::template())->toBe(StudentIdCardTemplate::Midnight);

    $this->actingAs(makeIdCardTestAdmin());

    Livewire::test(TeacherIdCards::class)
        ->call('selectTemplate', StudentIdCardTemplate::Ocean->value)
        ->call('submitDesign')
        ->assertNotified();

    expect(TeacherIdCardLayout::template())->toBe(StudentIdCardTemplate::Ocean)
        ->and(StudentIdCardLayout::template())->toBe(StudentIdCardTemplate::Royal);
});

it('renders the teacher id card page with each teacher and a link to their card', function () {
    $teacher = makeIdCardTestTeacher([], ['name' => 'Karim Uddin']);
    TeacherIdCardLayout::submitDesign(StudentIdCardTemplate::Midnight, null);

    $this->actingAs(makeIdCardTestAdmin())->get(TeacherIdCards::getUrl())
        ->assertSuccessful()
        ->assertSee('Karim Uddin')
        ->assertSee('Teacher Name')
        ->assertSee(route('teacher-id-cards.teacher.view', ['teacher' => $teacher->id]), false);
});

it('saves the teacher card fields and issue date from the card fields modal', function () {
    $this->actingAs(makeIdCardTestAdmin());

    Livewire::test(TeacherIdCards::class)
        ->mountAction('configureCardFields')
        ->set('mountedActions.0.data.front_fields', ['designation', 'id_no'])
        ->set('mountedActions.0.data.front_order', ['a' => ['field' => 'designation'], 'b' => ['field' => 'id_no']])
        ->set('mountedActions.0.data.back_fields', ['phone'])
        ->set('mountedActions.0.data.back_order', ['a' => ['field' => 'phone']])
        ->set('mountedActions.0.data.issue_date', '2026-01-01')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(TeacherIdCardLayout::frontFields())->toBe([TeacherIdCardField::Designation, TeacherIdCardField::IdNo])
        ->and(TeacherIdCardLayout::backFields())->toBe([TeacherIdCardField::Phone])
        ->and(TeacherIdCardLayout::issueDate()->toDateString())->toBe('2026-01-01');
});

it('shows the teacher a preview of their card and saves the photo position', function () {
    Storage::fake('public');

    $avatar = UploadedFile::fake()->image('teacher.jpg', 300, 450)->store('teachers', 'public');
    $teacher = makeIdCardTestTeacher(['designation' => 'Head Teacher'], ['name' => 'Nasrin Akter', 'avatar' => $avatar]);

    Filament::setCurrentPanel('teacher');
    $this->actingAs($teacher->user);

    Livewire::test(MyIdCard::class)
        ->assertSuccessful()
        ->assertSee('Nasrin Akter')
        ->assertSee('Head Teacher')
        ->assertSee('TEACHER ID CARD')
        ->call('savePhotoPosition', 20, 80, 1.5)
        ->assertNotified();

    expect($teacher->refresh()->id_card_photo_position)->toBe(['x' => 20, 'y' => 80, 'zoom' => 1.5]);

    Livewire::test(MyIdCard::class)->call('resetPhotoPosition');

    expect($teacher->refresh()->id_card_photo_position)->toBeNull();
});

it('hides the my id card page from teacher accounts without a profile', function () {
    Filament::setCurrentPanel('teacher');
    $this->actingAs(User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]));

    expect(MyIdCard::canAccess())->toBeFalse();
});

it('lets a teacher upload their own signature from the profile page', function () {
    Storage::fake('public');

    $teacher = makeIdCardTestTeacher();

    Filament::setCurrentPanel('teacher');
    $this->actingAs($teacher->user);

    Livewire::test(EditProfile::class)
        ->fillForm(['signature' => UploadedFile::fake()->image('signature.png', 300, 100)])
        ->call('save')
        ->assertHasNoFormErrors();

    $signature = $teacher->refresh()->signature;

    expect($signature)->toStartWith('teacher-profiles/signatures/');
    Storage::disk('public')->assertExists($signature);
});

it('resets the saved photo position when the teacher changes their profile photo', function () {
    Storage::fake('public');

    $teacher = makeIdCardTestTeacher(['id_card_photo_position' => ['x' => 10, 'y' => 10, 'zoom' => 2]]);

    Filament::setCurrentPanel('teacher');
    $this->actingAs($teacher->user);

    // Saving without touching the photo keeps the position.
    Livewire::test(EditProfile::class)
        ->fillForm(['name' => 'Updated Name'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($teacher->refresh()->id_card_photo_position)->not->toBeNull();

    Livewire::test(EditProfile::class)
        ->fillForm(['avatar' => UploadedFile::fake()->image('new.jpg', 300, 300)])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($teacher->refresh()->id_card_photo_position)->toBeNull()
        ->and($teacher->user->refresh()->avatar)->toStartWith('teachers/');
});
