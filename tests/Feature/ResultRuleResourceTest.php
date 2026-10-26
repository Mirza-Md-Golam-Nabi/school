<?php

use App\Enums\ResultRounding;
use App\Enums\UserType;
use App\Filament\Resources\ResultRules\Pages\CreateResultRule;
use App\Filament\Resources\ResultRules\Pages\EditResultRule;
use App\Filament\Resources\ResultRules\Pages\ListResultRules;
use App\Models\Classes;
use App\Models\ExamType;
use App\Models\ResultRule;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $this->undoRepeaterFake = Repeater::fake();

    $this->classSix = Classes::create(['name' => 'Class 6', 'order' => 6, 'is_active' => true]);
    $this->classSeven = Classes::create(['name' => 'Class 7', 'order' => 7, 'is_active' => true]);
    $this->firstSemester = ExamType::create(['name' => '1st Semester', 'is_active' => true]);
    $this->secondSemester = ExamType::create(['name' => '2nd Semester', 'is_active' => true]);
    $this->final = ExamType::create(['name' => 'Final', 'is_active' => true]);
});

afterEach(function () {
    ($this->undoRepeaterFake)();
});

/**
 * @return array<string, mixed>
 */
function semesterRuleFormData(object $test, array $overrides = []): array
{
    return [
        'name' => 'Semester system',
        'classes' => [$test->classSix->id, $test->classSeven->id],
        'target_exam_type_id' => $test->final->id,
        'full_marks' => null,
        'rounding' => ResultRounding::Nearest->value,
        'components' => [
            ['exam_type_id' => $test->firstSemester->id, 'weight_percent' => 25, 'must_pass' => true],
            ['exam_type_id' => $test->secondSemester->id, 'weight_percent' => 25, 'must_pass' => false],
            ['exam_type_id' => $test->final->id, 'weight_percent' => 50, 'must_pass' => true],
        ],
        ...$overrides,
    ];
}

it('creates a result rule with its classes and components, and logs it once', function () {
    Livewire::test(CreateResultRule::class)
        ->fillForm(semesterRuleFormData($this))
        ->call('create')
        ->assertHasNoFormErrors();

    $rule = ResultRule::with('classes', 'components')->sole();

    expect($rule->target_exam_type_id)->toBe($this->final->id)
        ->and($rule->rounding)->toBe(ResultRounding::Nearest)
        ->and($rule->classes->pluck('name')->sort()->values()->all())->toBe(['Class 6', 'Class 7'])
        ->and($rule->components->map(fn ($component) => [$component->exam_type_id, $component->weight_percent, $component->must_pass])->all())->toBe([
            [$this->firstSemester->id, 25.0, true],
            [$this->secondSemester->id, 25.0, false],
            [$this->final->id, 50.0, true],
        ])
        ->and($rule->componentsSummary())->toBe('1st Semester 25% + 2nd Semester 25% + Final 50%');

    $activity = Activity::where('log_name', 'result_rule')->sole();

    expect($activity->event)->toBe('created')
        ->and($activity->properties->get('attributes')['class_id_label'])->toBe(['Class 6', 'Class 7']);
});

it('refuses components that do not add up to 100%', function () {
    Livewire::test(CreateResultRule::class)
        ->fillForm(semesterRuleFormData($this, ['components' => [
            ['exam_type_id' => $this->firstSemester->id, 'weight_percent' => 25, 'must_pass' => false],
            ['exam_type_id' => $this->final->id, 'weight_percent' => 50, 'must_pass' => false],
        ]]))
        ->call('create')
        ->assertHasFormErrors(['components']);

    expect(ResultRule::count())->toBe(0);
});

it('refuses a rule whose own exam is not one of its components', function () {
    Livewire::test(CreateResultRule::class)
        ->fillForm(semesterRuleFormData($this, ['components' => [
            ['exam_type_id' => $this->firstSemester->id, 'weight_percent' => 50, 'must_pass' => false],
            ['exam_type_id' => $this->secondSemester->id, 'weight_percent' => 50, 'must_pass' => false],
        ]]))
        ->call('create')
        ->assertHasFormErrors(['components']);
});

it('refuses a class that another rule already covers', function () {
    makeResultRule($this->classSix, $this->final, [[$this->final, 100]]);

    Livewire::test(CreateResultRule::class)
        ->fillForm(semesterRuleFormData($this))
        ->call('create')
        ->assertHasFormErrors(['classes']);
});

it('lets a rule keep its own classes when it is edited, and logs old and new', function () {
    $rule = makeResultRule($this->classSix, $this->final, [[$this->firstSemester, 25], [$this->secondSemester, 25], [$this->final, 50]]);

    Livewire::test(EditResultRule::class, ['record' => $rule->id])
        ->fillForm(semesterRuleFormData($this, [
            'classes' => [$this->classSix->id],
            'components' => [
                ['exam_type_id' => $this->firstSemester->id, 'weight_percent' => 20, 'must_pass' => false],
                ['exam_type_id' => $this->secondSemester->id, 'weight_percent' => 30, 'must_pass' => false],
                ['exam_type_id' => $this->final->id, 'weight_percent' => 50, 'must_pass' => false],
            ],
        ]))
        ->call('save')
        ->assertHasNoFormErrors();

    expect($rule->refresh()->componentsSummary())->toBe('1st Semester 20% + 2nd Semester 30% + Final 50%');

    $activity = Activity::where('log_name', 'result_rule')->where('event', 'updated')->sole();

    expect($activity->properties->get('old')['components'])->toBe(['1st Semester 25%', '2nd Semester 25%', 'Final 50%'])
        ->and($activity->properties->get('attributes')['components'])->toBe(['1st Semester 20%', '2nd Semester 30%', 'Final 50%']);
});

it('lists the rules with how each result is made', function () {
    $rule = makeResultRule($this->classSix, $this->final, [[$this->firstSemester, 25], [$this->secondSemester, 25], [$this->final, 50]]);

    Livewire::test(ListResultRules::class)
        ->assertCanSeeTableRecords([$rule])
        ->assertSee('1st Semester 25% + 2nd Semester 25% + Final 50%');
});
