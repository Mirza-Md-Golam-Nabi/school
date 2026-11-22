<?php

use App\Actions\BuildTeacherListPdfAction;
use App\Enums\AddressType;
use App\Enums\TeacherListColumn;
use App\Enums\UserType;
use App\Filament\Resources\TeacherProfiles\Pages\ListTeacherProfiles;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

function makeTeacherListTestTeacher(string $name, array $attributes = []): TeacherProfile
{
    $user = User::factory()->create([
        'user_type' => UserType::Teacher,
        'is_active' => true,
        'name' => $name,
        'phone' => '01711000000',
    ]);

    return TeacherProfile::factory()->create(['user_id' => $user->id, ...$attributes]);
}

it('builds a pdf listing every teacher', function () {
    makeTeacherListTestTeacher('Teacher B');
    makeTeacherListTestTeacher('Teacher A');

    $pdf = app(BuildTeacherListPdfAction::class)->handle();

    expect($pdf)->toStartWith('%PDF');
});

it('builds a landscape pdf with every column selected', function () {
    $teacher = makeTeacherListTestTeacher('Teacher Landscape', [
        'designation' => 'Assistant Teacher',
        'joining_date' => '2020-01-15',
    ]);
    $teacher->addresses()->create(['type' => AddressType::Present, 'address' => 'Dhaka']);

    $pdf = app(BuildTeacherListPdfAction::class)->handle(array_keys(TeacherListColumn::options()), 'L');

    expect($pdf)->toStartWith('%PDF');
});

it('aborts with 404 when there are no teachers', function () {
    expect(fn () => app(BuildTeacherListPdfAction::class)->handle())
        ->toThrow(NotFoundHttpException::class);
});

it('renders the selected columns in the teacher list view', function () {
    $rendered = view('documents.teacher-list', [
        'columns' => collect([TeacherListColumn::Designation, TeacherListColumn::PresentAddress]),
        'rows' => [
            [
                'serial' => '01',
                'name' => 'Teacher Rendered',
                'values' => ['Head Teacher', 'Mirpur, Dhaka'],
            ],
        ],
    ])->render();

    expect($rendered)
        ->toContain('Teacher List')
        ->toContain('Designation')
        ->toContain('Present Address')
        ->toContain('Teacher Rendered')
        ->toContain('Head Teacher')
        ->toContain('Mirpur, Dhaka')
        ->not->toContain('Joining Date');
});

it('streams the teacher list pdf as a download for an admin', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    makeTeacherListTestTeacher('Teacher Route');

    $this->actingAs($admin)
        ->get(route('teacher-list.download', ['columns' => ['email'], 'orientation' => 'L']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('forbids non-admin users from downloading the teacher list', function () {
    $teacher = makeTeacherListTestTeacher('Teacher Forbidden');

    $this->actingAs($teacher->user)
        ->get(route('teacher-list.download'))
        ->assertForbidden();
});

it('sends the chosen columns and orientation from the download modal to the pdf route', function (string $orientation) {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    Livewire::test(ListTeacherProfiles::class)
        ->callAction('downloadTeacherList', [
            'columns' => TeacherListColumn::defaults(),
            'orientation' => $orientation,
        ])
        ->assertHasNoActionErrors()
        ->assertRedirect(route('teacher-list.download', [
            'columns' => TeacherListColumn::defaults(),
            'orientation' => $orientation,
        ]));
})->with(['P', 'L']);
