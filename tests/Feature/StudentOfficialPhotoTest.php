<?php

use App\Actions\BuildClassStudentIdCardsPdfAction;
use App\Actions\CreateStudentProfileAction;
use App\Actions\UpdateStudentProfileAction;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\StudentProfiles\Pages\CreateStudentProfile;
use App\Filament\Resources\StudentProfiles\Pages\EditStudentProfile;
use App\Filament\Resources\StudentProfiles\StudentProfileResource;
use App\Filament\Student\Pages\MyProfile;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\IdCardPhotoRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    $this->officialPhoto = UploadedFile::fake()->image('official.jpg', 300, 400)->store('student-profiles/photos', 'public');
    $this->ownAvatar = UploadedFile::fake()->image('selfie.jpg', 300, 300)->store('students', 'public');
});

/**
 * A student whose school-uploaded official photo and self-uploaded avatar are different files.
 */
function makeStudentWithBothPhotos(string $officialPhoto, string $ownAvatar): StudentProfile
{
    $user = makeStudent(['name' => 'Photo Test Student', 'avatar' => $ownAvatar, 'must_change_password' => false]);
    $class = Classes::create(['name' => 'Photo Class', 'order' => 1, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'photo' => $officialPhoto,
        'roll_no' => 1,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

it('offers an official photo upload on the student create and edit forms', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $student = makeStudentWithBothPhotos($this->officialPhoto, $this->ownAvatar);

    Livewire::test(CreateStudentProfile::class)->assertFormFieldExists('photo');

    Livewire::test(EditStudentProfile::class, ['record' => $student->id])
        ->assertFormFieldExists('photo')
        ->assertSchemaStateSet(fn (array $state): bool => in_array($this->officialPhoto, (array) $state['photo'], true));
});

it('saves the official photo when a student profile is created', function () {
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
    $class = Classes::create(['name' => 'Photo Create Class', 'order' => 1]);

    $profile = app(CreateStudentProfileAction::class)->handle([
        'name' => 'Created With Photo',
        'photo' => $this->officialPhoto,
        'roll_no' => 4,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => 'male',
        'status' => 'active',
    ]);

    expect($profile->fresh()->photo)->toBe($this->officialPhoto)
        ->and($profile->user->avatar)->toBeNull();
});

it('replaces the official photo on update without touching the avatar the student uploaded', function () {
    $student = makeStudentWithBothPhotos($this->officialPhoto, $this->ownAvatar);
    $newPhoto = UploadedFile::fake()->image('new-official.jpg')->store('student-profiles/photos', 'public');

    app(UpdateStudentProfileAction::class)->handle($student, [
        'name' => 'Photo Test Student',
        'photo' => $newPhoto,
        'roll_no' => 1,
        'current_class_id' => $student->current_class_id,
        'session_year' => $student->session_year,
        'gender' => 'male',
        'status' => 'active',
    ]);

    expect($student->fresh()->photo)->toBe($newPhoto)
        ->and($student->user->fresh()->avatar)->toBe($this->ownAvatar);
});

it('shows the official photo in the admin panel and never the avatar the student uploaded', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $student = makeStudentWithBothPhotos($this->officialPhoto, $this->ownAvatar);

    $this->actingAs($admin)->get(StudentProfileResource::getUrl('view', ['record' => $student]))
        ->assertOk()
        ->assertSee(Storage::disk('public')->url($this->officialPhoto), false)
        ->assertDontSee(basename($this->ownAvatar), false);
});

it('falls back to the initial in the admin panel when only the student avatar exists', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $student = makeStudentWithBothPhotos($this->officialPhoto, $this->ownAvatar);
    $student->update(['photo' => null]);

    $this->actingAs($admin)->get(StudentProfileResource::getUrl('view', ['record' => $student]))
        ->assertOk()
        ->assertDontSee(basename($this->ownAvatar), false);
});

it('shows the official photo as a circle in the class student list, never the avatar the student uploaded', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $student = makeStudentWithBothPhotos($this->officialPhoto, $this->ownAvatar);

    $this->actingAs($admin)->get(StudentProfileResource::getUrl('students-by-class', ['classId' => $student->current_class_id]))
        ->assertOk()
        ->assertSee(Storage::disk('public')->url($this->officialPhoto), false)
        ->assertSee('fi-circular', false)
        ->assertDontSee(basename($this->ownAvatar), false);
});

it('falls back to a generated avatar in the class student list when no official photo exists', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $student = makeStudentWithBothPhotos($this->officialPhoto, $this->ownAvatar);
    $student->update(['photo' => null]);

    $this->actingAs($admin)->get(StudentProfileResource::getUrl('students-by-class', ['classId' => $student->current_class_id]))
        ->assertOk()
        ->assertSee('ui-avatars.com', false)
        ->assertDontSee(basename($this->ownAvatar), false);
});

it('shows the student their own uploaded avatar on their profile page', function () {
    $student = makeStudentWithBothPhotos($this->officialPhoto, $this->ownAvatar);

    $this->actingAs($student->user)->get(MyProfile::getUrl(panel: 'student'))
        ->assertOk()
        ->assertSee(basename($this->ownAvatar), false);
});

it('prints the official photo on the id card, not the avatar the student uploaded', function () {
    $student = makeStudentWithBothPhotos($this->officialPhoto, $this->ownAvatar);

    $renderedPaths = [];
    $realRenderer = new IdCardPhotoRenderer;

    $this->mock(IdCardPhotoRenderer::class)
        ->shouldReceive('render')
        ->andReturnUsing(function (?string $path) use (&$renderedPaths, $realRenderer): string {
            $renderedPaths[] = $path;

            return $realRenderer->render($path);
        });

    $pdf = app(BuildClassStudentIdCardsPdfAction::class)->handle($student->class);

    expect($pdf)->toStartWith('%PDF')
        ->and($renderedPaths)->toBe([$this->officialPhoto]);
});
