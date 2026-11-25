<?php

use App\Actions\BuildSubjectImportTemplateAction;
use App\Actions\ImportSubjectsFromSpreadsheetAction;
use App\Enums\UserType;
use App\Filament\Resources\Subjects\Pages\ListSubjects;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

/**
 * @param  array<int, array<int, mixed>>  $rows  first row is the header
 */
function makeSubjectImportTestFile(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'subject-import-test').'.xlsx';

    $writer = new Writer;
    $writer->openToFile($path);

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    return $path;
}

it('builds a demo file whose header row lists every subject import column with yes/no dropdowns', function () {
    $path = tempnam(sys_get_temp_dir(), 'subject-import-read').'.xlsx';
    file_put_contents($path, app(BuildSubjectImportTemplateAction::class)->handle());

    $reader = new Reader;
    $reader->open($path);
    $sheets = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        $sheets[$sheet->getName()] = array_map(fn (Row $row): array => $row->toArray(), iterator_to_array($sheet->getRowIterator(), false));
    }

    $reader->close();

    $headers = array_map(fn (string $header): string => rtrim($header, '*'), $sheets['Subjects'][0]);

    expect($headers)->toBe(array_keys(ImportSubjectsFromSpreadsheetAction::COLUMNS))
        ->and($sheets['Subjects'][0])->toContain('name*', 'code')
        ->and(collect($sheets['Instructions'])->firstWhere(0, 'name')[1])->toBe('Yes');

    $subjects = IOFactory::load($path)->getSheetByName('Subjects');
    $mcqColumn = Coordinate::stringFromColumnIndex(array_search('has_mcq', $headers) + 1);
    $nameColumn = Coordinate::stringFromColumnIndex(array_search('name', $headers) + 1);

    expect($subjects->getCell("{$mcqColumn}2")->getDataValidation()->getType())->toBe(DataValidation::TYPE_LIST)
        ->and($subjects->getCell("{$nameColumn}2")->getDataValidation()->getType())->toBe(DataValidation::TYPE_NONE);
});

it('imports every row as a subject in file order with defaults for blank yes/no cells', function () {
    Subject::create(['name' => 'Existing', 'code' => 'EX1']);

    $path = makeSubjectImportTestFile([
        ['name*', 'name_bn', 'code', 'has_written', 'has_mcq', 'has_practical', 'is_active'],
        ['Mathematics', 'গণিত', 109, 'yes', 'Yes', 'no', ''],
        ['Physics', null, 'phy', null, null, 'yes', 'no'],
        [null, null, null, null, null, null, null],
    ]);

    $result = app(ImportSubjectsFromSpreadsheetAction::class)->handle($path);

    expect($result)->toBe(['imported' => 2, 'errors' => []]);

    $math = Subject::where('name', 'Mathematics')->sole();
    $physics = Subject::where('name', 'Physics')->sole();

    expect($math->only(['name_bn', 'code', 'has_written', 'has_mcq', 'has_practical', 'is_active']))->toBe([
        'name_bn' => 'গণিত',
        'code' => '109',
        'has_written' => true,
        'has_mcq' => true,
        'has_practical' => false,
        'is_active' => true,
    ])
        ->and($physics->only(['code', 'has_written', 'has_mcq', 'has_practical', 'is_active']))->toBe([
            'code' => 'PHY',
            'has_written' => true,
            'has_mcq' => false,
            'has_practical' => true,
            'is_active' => false,
        ])
        ->and(Subject::ordered()->pluck('name')->all())->toBe(['Existing', 'Mathematics', 'Physics']);
});

it('imports nothing and reports each bad subject row when the file has errors', function () {
    $trashed = Subject::create(['name' => 'Old', 'code' => 'OLD']);
    $trashed->delete();

    $path = makeSubjectImportTestFile([
        ['name', 'code', 'has_mcq'],
        ['Bangla', 'BAN', 'maybe'],
        [null, 'ENG', null],
        ['English', 'ban', null],
        ['History', 'old', null],
    ]);

    $result = app(ImportSubjectsFromSpreadsheetAction::class)->handle($path);

    expect($result['imported'])->toBe(0)
        ->and($result['errors'])->toHaveCount(4)
        ->and($result['errors'][0])->toStartWith('Row 2:')->toContain('has_mcq')
        ->and($result['errors'][1])->toStartWith('Row 3:')->toContain('name')
        ->and($result['errors'][2])->toStartWith('Row 4:')->toContain('Row 2')
        ->and($result['errors'][3])->toStartWith('Row 5:')->toContain('OLD')
        ->and(Subject::count())->toBe(0);
});

it('rejects a file without the name column', function () {
    $path = makeSubjectImportTestFile([
        ['title', 'code'],
        ['Mathematics', '109'],
    ]);

    $result = app(ImportSubjectsFromSpreadsheetAction::class)->handle($path);

    expect($result['imported'])->toBe(0)
        ->and($result['errors'][0])->toContain('name');
});

it('offers the demo file download and imports subjects from the upload action on the subject list page', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $path = makeSubjectImportTestFile([
        ['name', 'code'],
        ['Mathematics', '109'],
        ['Physics', '174'],
    ]);

    Livewire::test(ListSubjects::class)
        ->callAction('downloadSubjectImportTemplate')
        ->assertFileDownloaded('subject-import-demo.xlsx')
        ->callAction('importSubjects', [
            'file' => UploadedFile::fake()->createWithContent('subjects.xlsx', file_get_contents($path)),
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('2টা subject import হয়েছে');

    expect(Subject::count())->toBe(2);
});
