<?php

use App\Actions\BuildStudentImportTemplateAction;
use App\Actions\ImportStudentsFromSpreadsheetAction;
use App\Enums\AddressType;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Filament\Resources\StudentProfiles\Pages\ListStudentProfiles;
use App\Filament\Resources\StudentProfiles\Pages\StudentsByClass;
use App\Models\Classes;
use App\Models\Group;
use App\Models\Section;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('student');
});

/**
 * @param  array<int, array<int, mixed>>  $rows  first row is the header
 */
function makeStudentImportTestFile(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'student-import-test').'.xlsx';

    $writer = new Writer;
    $writer->openToFile($path);

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    return $path;
}

/**
 * @return array<int, array<int, mixed>>
 */
function readStudentImportTestSheets(string $contents): array
{
    $path = tempnam(sys_get_temp_dir(), 'student-import-read').'.xlsx';
    file_put_contents($path, $contents);

    $reader = new Reader;
    $reader->open($path);

    $sheets = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        $sheets[$sheet->getName()] = array_map(fn (Row $row): array => $row->toArray(), iterator_to_array($sheet->getRowIterator(), false));
    }

    $reader->close();

    return $sheets;
}

it('builds a demo file whose header row lists every import column and whose example lives on a separate sheet', function () {
    $class = Classes::create(['name' => 'Import Class 6', 'order' => 6]);

    $sheets = readStudentImportTestSheets(app(BuildStudentImportTemplateAction::class)->handle($class));

    $headers = array_map(fn (string $header): string => rtrim($header, '*'), $sheets['Students'][0]);

    expect($sheets['Students'])->toHaveCount(1)
        ->and($headers)->toBe(array_keys(ImportStudentsFromSpreadsheetAction::COLUMNS))
        ->and($sheets['Students'][0])->toContain('name*', 'roll_no*', 'gender*', 'section')
        ->and(collect($sheets['Instructions'])->firstWhere(0, 'class')[2])->toBe('Import Class 6');
});

it('lists the real class, section, group and optional subject names on the allowed values sheet', function () {
    $classSix = Classes::create(['name' => 'Import Class 6', 'order' => 6]);
    $classNine = Classes::create(['name' => 'Import Class 9', 'order' => 9, 'has_group' => true]);
    Section::create(['class_id' => $classSix->id, 'name' => 'Padma']);
    $science = Group::create(['name' => 'Science', 'is_active' => true]);
    $classNine->groups()->attach($science->id);
    $biology = Subject::create(['name' => 'Biology', 'has_written' => true, 'is_active' => true]);
    $biology->classes()->attach($classNine->id, ['group_id' => $science->id, 'subject_type' => SubjectType::Optional->value]);

    $rows = collect(readStudentImportTestSheets(app(BuildStudentImportTemplateAction::class)->handle())['Allowed Values']);

    expect($rows->contains(['gender', '', '', 'male']))->toBeTrue()
        ->and($rows->contains(['blood_group', '', '', 'AB+']))->toBeTrue()
        ->and($rows->contains(['religion', '', '', 'muslim']))->toBeTrue()
        ->and($rows->contains(['class', '', '', 'Import Class 6']))->toBeTrue()
        ->and($rows->contains(['class', '', '', 'Import Class 9']))->toBeTrue()
        ->and($rows->contains(['section', 'Import Class 6', '', 'Padma']))->toBeTrue()
        ->and($rows->contains(['group', 'Import Class 9', '', 'Science']))->toBeTrue()
        ->and($rows->contains(fn (array $row): bool => array_slice($row, 0, 3) === ['main_optional_subject', 'Import Class 9', 'Science'] && str_contains($row[3], 'Biology')))->toBeTrue();

    $classOnlyRows = collect(readStudentImportTestSheets(app(BuildStudentImportTemplateAction::class)->handle($classSix))['Allowed Values']);

    expect($classOnlyRows->contains(['class', '', '', 'Import Class 6']))->toBeTrue()
        ->and($classOnlyRows->contains(['class', '', '', 'Import Class 9']))->toBeFalse()
        ->and($classOnlyRows->contains(['group', 'Import Class 9', '', 'Science']))->toBeFalse();
});

it('imports every row of a filled-in demo file as a student with a login account', function () {
    $class = Classes::create(['name' => 'Import Class 6', 'order' => 6]);
    $section = Section::create(['class_id' => $class->id, 'name' => 'A']);

    $path = makeStudentImportTestFile([
        ['name*', 'roll_no*', 'class*', 'section', 'session_year', 'gender*', 'date_of_birth', 'blood_group', 'guardian_phone', 'present_address', 'permanent_address'],
        ['Abdur Rahman', 1, 'import class 6', 'a', 2026, 'Male', '21/03/2014', 'b+', 1712345678, 'Mirpur, Dhaka', 'Cumilla'],
        ['Fatema Akter', 2, 'Import Class 6', null, null, 'female', null, null, null, null, null],
    ]);

    $result = app(ImportStudentsFromSpreadsheetAction::class)->handle($path);

    expect($result)->toBe(['imported' => 2, 'errors' => []]);

    $first = StudentProfile::where('roll_no', 1)->sole();
    $second = StudentProfile::where('roll_no', 2)->sole();

    expect($first->user->name)->toBe('Abdur Rahman')
        ->and($first->user->email)->toBe(sprintf('std%05d@school.com', $first->id))
        ->and($first->user->hasRole('student'))->toBeTrue()
        ->and($first->current_class_id)->toBe($class->id)
        ->and($first->current_section_id)->toBe($section->id)
        ->and($first->session_year)->toBe(2026)
        ->and($first->gender)->toBe(Gender::Male)
        ->and($first->date_of_birth->toDateString())->toBe('2014-03-21')
        ->and($first->blood_group->value)->toBe('B+')
        ->and($first->guardian_phone)->toBe('01712345678')
        ->and($first->status)->toBe(StudentStatus::Active)
        ->and($first->addresses()->where('type', AddressType::Present)->value('address'))->toBe('Mirpur, Dhaka')
        ->and($first->addresses()->where('type', AddressType::Permanent)->value('address'))->toBe('Cumilla')
        ->and($second->current_section_id)->toBeNull()
        ->and($second->session_year)->toBe(now()->year)
        ->and($second->nationality)->toBe('Bangladeshi');
});

it('imports nothing and reports each bad row when the file has errors', function () {
    $class = Classes::create(['name' => 'Import Class 6', 'order' => 6]);

    $path = makeStudentImportTestFile([
        ['name', 'roll_no', 'class', 'section', 'gender', 'date_of_birth'],
        ['Valid Student', 1, 'Import Class 6', null, 'male', null],
        [null, 2, 'Import Class 6', null, 'male', null],
        ['Wrong Class', 3, 'Class 99', null, 'male', null],
        ['Wrong Values', 'abc', 'Import Class 6', 'Z', 'boy', '2014-13-45'],
        ['Same Roll', 1, 'Import Class 6', null, 'female', null],
    ]);

    $result = app(ImportStudentsFromSpreadsheetAction::class)->handle($path);
    $errors = implode(' | ', $result['errors']);

    expect($result['imported'])->toBe(0)
        ->and(StudentProfile::count())->toBe(0)
        ->and(User::where('user_type', UserType::Student)->count())->toBe(0)
        ->and($errors)->toContain('Row 3: name')
        ->and($errors)->toContain('Row 4: class "Class 99"')
        ->and($errors)->toContain('Row 5: roll_no')
        ->and($errors)->toContain('Row 5: section "Z"')
        ->and($errors)->toContain('Row 5: gender "boy"')
        ->and($errors)->toContain('Row 5: date_of_birth "2014-13-45"')
        ->and($errors)->toContain('Row 6: roll_no 1')
        ->and($errors)->not->toContain('Row 2:');
});

it('rejects a file that is missing a required column', function () {
    Classes::create(['name' => 'Import Class 6', 'order' => 6]);

    $path = makeStudentImportTestFile([
        ['name', 'class', 'gender'],
        ['No Roll', 'Import Class 6', 'male'],
    ]);

    $result = app(ImportStudentsFromSpreadsheetAction::class)->handle($path);

    expect($result['imported'])->toBe(0)
        ->and($result['errors'][0])->toContain('roll_no')
        ->and(StudentProfile::count())->toBe(0);
});

it('refuses to import the same file twice instead of creating duplicate students', function () {
    Classes::create(['name' => 'Import Class 6', 'order' => 6]);

    $path = makeStudentImportTestFile([
        ['name', 'roll_no', 'class', 'gender'],
        ['Abdur Rahman', 1, 'Import Class 6', 'male'],
    ]);

    $action = app(ImportStudentsFromSpreadsheetAction::class);

    expect($action->handle($path)['imported'])->toBe(1);

    $second = $action->handle($path);

    expect($second['imported'])->toBe(0)
        ->and($second['errors'][0])->toContain('Row 2: roll_no 1')
        ->and(StudentProfile::count())->toBe(1);
});

it("uses the page's class for rows that leave the class column blank", function () {
    $class = Classes::create(['name' => 'Import Class 6', 'order' => 6]);

    $path = makeStudentImportTestFile([
        ['name', 'roll_no', 'class', 'gender'],
        ['Abdur Rahman', 1, null, 'male'],
    ]);

    expect(app(ImportStudentsFromSpreadsheetAction::class)->handle($path)['errors'][0])->toContain('Row 2: class');

    $result = app(ImportStudentsFromSpreadsheetAction::class)->handle($path, $class->id);

    expect($result['imported'])->toBe(1)
        ->and(StudentProfile::sole()->current_class_id)->toBe($class->id);
});

it('imports students from the upload action on the class page', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $class = Classes::create(['name' => 'Import Class 6', 'order' => 6]);

    $path = makeStudentImportTestFile([
        ['name', 'roll_no', 'class', 'gender'],
        ['Abdur Rahman', 1, null, 'male'],
        ['Fatema Akter', 2, null, 'female'],
    ]);

    Livewire::withQueryParams(['classId' => $class->id])
        ->test(StudentsByClass::class)
        ->assertActionExists('downloadStudentImportTemplate')
        ->callAction('importStudents', [
            'file' => UploadedFile::fake()->createWithContent('students.xlsx', file_get_contents($path)),
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('2 জন student import হয়েছে');

    expect(StudentProfile::where('current_class_id', $class->id)->count())->toBe(2);
});

it('offers the demo file download and the upload action on the all-classes page', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    Livewire::test(ListStudentProfiles::class)
        ->assertActionExists('downloadStudentImportTemplate')
        ->assertActionExists('importStudents')
        ->callAction('downloadStudentImportTemplate')
        ->assertFileDownloaded('student-import-demo.xlsx');
});
