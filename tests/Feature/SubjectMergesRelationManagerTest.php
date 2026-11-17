<?php

use App\Actions\CopySubjectMergesToExamAction;
use App\Enums\MergedAbsentRule;
use App\Enums\MergedComponentPassRule;
use App\Enums\MergedPassRule;
use App\Enums\ResultRounding;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Filament\Resources\Classes\Pages\EditClasses;
use App\Filament\Resources\Classes\RelationManagers\SubjectMergesRelationManager as ClassSubjectMergesRelationManager;
use App\Filament\Resources\Exams\Pages\EditExam;
use App\Filament\Resources\Exams\RelationManagers\SubjectMergesRelationManager as ExamSubjectMergesRelationManager;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamSubjectMerge;
use App\Models\ExamType;
use App\Models\Subject;
use App\Models\SubjectMerge;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $this->class = Classes::create(['name' => 'Class 6', 'order' => 6, 'is_active' => true]);

    $makeSubject = function (string $name, SubjectType $type = SubjectType::Compulsory): Subject {
        $subject = Subject::create(['name' => $name, 'has_written' => true, 'is_active' => true]);
        $subject->classes()->attach($this->class->id, ['subject_type' => $type->value]);

        return $subject;
    };

    $this->banglaFirst = $makeSubject('Bangla 1st Paper');
    $this->banglaSecond = $makeSubject('Bangla 2nd Paper');
    $this->agriculture = $makeSubject('Agriculture', SubjectType::Optional);
});

function subjectMergeTestExam(Classes $class, bool $isPublished = false): Exam
{
    return Exam::create([
        'exam_type_id' => ExamType::create(['name' => 'Final', 'is_active' => true])->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => $isPublished,
    ]);
}

it('merges two subjects of a class, leaving empty rules to School Settings', function () {
    Livewire::test(ClassSubjectMergesRelationManager::class, ['ownerRecord' => $this->class, 'pageClass' => EditClasses::class])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'name' => 'Bangla',
            'name_bn' => 'বাংলা',
            'first_subject_id' => $this->banglaFirst->id,
            'second_subject_id' => $this->banglaSecond->id,
            'absent_rule' => MergedAbsentRule::CountAsZero->value,
        ])
        ->assertHasNoFormErrors();

    assertDatabaseHas(SubjectMerge::class, [
        'class_id' => $this->class->id,
        'name' => 'Bangla',
        'first_subject_id' => $this->banglaFirst->id,
        'second_subject_id' => $this->banglaSecond->id,
        'pass_rule' => null,
        'absent_rule' => MergedAbsentRule::CountAsZero->value,
    ]);
});

it('rejects merging a subject with itself, with a different subject type, or one already merged', function (string $case) {
    $other = match ($case) {
        'itself' => $this->banglaFirst,
        'type' => $this->agriculture,
        'taken' => $this->banglaSecond,
    };

    if ($case === 'taken') {
        SubjectMerge::factory()->create([
            'class_id' => $this->class->id,
            'first_subject_id' => $this->banglaSecond->id,
            'second_subject_id' => Subject::create(['name' => 'Other', 'is_active' => true])->id,
        ]);
    }

    Livewire::test(ClassSubjectMergesRelationManager::class, ['ownerRecord' => $this->class, 'pageClass' => EditClasses::class])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'name' => 'Bangla',
            'first_subject_id' => $this->banglaFirst->id,
            'second_subject_id' => $other->id,
        ])
        ->assertHasFormErrors(['first_subject_id']);

    assertDatabaseMissing(SubjectMerge::class, ['name' => 'Bangla']);
})->with(['itself', 'type', 'taken']);

it('replaces an exam\'s merges with the class default', function () {
    SubjectMerge::factory()->create([
        'class_id' => $this->class->id,
        'name' => 'Bangla',
        'first_subject_id' => $this->banglaFirst->id,
        'second_subject_id' => $this->banglaSecond->id,
    ]);
    $exam = subjectMergeTestExam($this->class);
    $stale = ExamSubjectMerge::factory()->create(['exam_id' => $exam->id, 'name' => 'Stale']);

    Livewire::test(ExamSubjectMergesRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->callAction(TestAction::make('applyClassDefault')->table())
        ->assertNotified();

    assertDatabaseMissing(ExamSubjectMerge::class, ['id' => $stale->id]);
    assertDatabaseHas(ExamSubjectMerge::class, [
        'exam_id' => $exam->id,
        'name' => 'Bangla',
        'pass_rule' => MergedPassRule::Combined->value,
        'component_pass_rule' => MergedComponentPassRule::Combined->value,
        'absent_rule' => MergedAbsentRule::Fail->value,
        'rounding' => ResultRounding::Decimal->value,
    ]);
});

it('adds a merge to one exam with every rule filled in', function () {
    $exam = subjectMergeTestExam($this->class);

    Livewire::test(ExamSubjectMergesRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'name' => 'Bangla',
            'first_subject_id' => $this->banglaFirst->id,
            'second_subject_id' => $this->banglaSecond->id,
            'pass_rule' => MergedPassRule::EachPaper->value,
            'component_pass_rule' => MergedComponentPassRule::EachPaper->value,
            'absent_rule' => MergedAbsentRule::Fail->value,
            'rounding' => ResultRounding::Down->value,
        ])
        ->assertHasNoFormErrors();

    assertDatabaseHas(ExamSubjectMerge::class, [
        'exam_id' => $exam->id,
        'pass_rule' => MergedPassRule::EachPaper->value,
        'rounding' => ResultRounding::Down->value,
    ]);
});

it('hides already merged subjects from both paper dropdowns', function () {
    $exam = subjectMergeTestExam($this->class);
    ExamSubjectMerge::factory()->create([
        'exam_id' => $exam->id,
        'first_subject_id' => $this->banglaFirst->id,
        'second_subject_id' => $this->banglaSecond->id,
    ]);

    $hasOnlyAgriculture = fn ($select): bool => array_keys($select->getOptions()) === [$this->agriculture->id];

    Livewire::test(ExamSubjectMergesRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->mountAction(TestAction::make(CreateAction::class)->table())
        ->assertSchemaComponentExists('first_subject_id', checkComponentUsing: $hasOnlyAgriculture)
        ->assertSchemaComponentExists('second_subject_id', checkComponentUsing: $hasOnlyAgriculture);
});

it('keeps the edited merge\'s own papers selectable, minus the paper picked in the other dropdown', function () {
    $exam = subjectMergeTestExam($this->class);
    $merge = ExamSubjectMerge::factory()->create([
        'exam_id' => $exam->id,
        'first_subject_id' => $this->banglaFirst->id,
        'second_subject_id' => $this->banglaSecond->id,
    ]);

    Livewire::test(ExamSubjectMergesRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->mountAction(TestAction::make(EditAction::class)->table($merge))
        ->assertSchemaComponentExists('first_subject_id', checkComponentUsing: fn ($select): bool => ! array_key_exists($this->banglaSecond->id, $select->getOptions())
            && array_key_exists($this->banglaFirst->id, $select->getOptions()))
        ->assertSchemaComponentExists('second_subject_id', checkComponentUsing: fn ($select): bool => ! array_key_exists($this->banglaFirst->id, $select->getOptions())
            && array_key_exists($this->banglaSecond->id, $select->getOptions()));
});

it('locks an exam\'s merges once its result is published', function () {
    $exam = subjectMergeTestExam($this->class, isPublished: true);

    Livewire::test(ExamSubjectMergesRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->assertActionHidden(TestAction::make(CreateAction::class)->table())
        ->assertActionHidden(TestAction::make('applyClassDefault')->table());
});

it('brings a class default change to its unpublished exams that follow it', function () {
    $following = subjectMergeTestExam($this->class);
    $published = subjectMergeTestExam($this->class, isPublished: true);
    $custom = subjectMergeTestExam($this->class);
    $custom->update(['has_custom_subject_merges' => true]);
    $ownMerge = ExamSubjectMerge::factory()->create(['exam_id' => $custom->id, 'name' => 'Own']);

    $merge = SubjectMerge::factory()->create([
        'class_id' => $this->class->id,
        'name' => 'Bangla',
        'first_subject_id' => $this->banglaFirst->id,
        'second_subject_id' => $this->banglaSecond->id,
    ]);

    assertDatabaseHas(ExamSubjectMerge::class, ['exam_id' => $following->id, 'name' => 'Bangla']);
    assertDatabaseMissing(ExamSubjectMerge::class, ['exam_id' => $published->id]);
    expect($custom->subjectMerges()->pluck('id')->all())->toBe([$ownMerge->id]);

    $merge->update(['name' => 'বাংলা মোট', 'rounding' => ResultRounding::Down]);

    assertDatabaseHas(ExamSubjectMerge::class, ['exam_id' => $following->id, 'name' => 'বাংলা মোট', 'rounding' => ResultRounding::Down->value]);
    expect($following->subjectMerges()->count())->toBe(1);

    $merge->delete();

    assertDatabaseMissing(ExamSubjectMerge::class, ['exam_id' => $following->id]);
    assertDatabaseHas(ExamSubjectMerge::class, ['id' => $ownMerge->id]);
});

it('keeps an exam\'s own merges once changed on the exam, until the class default is applied again', function () {
    $exam = subjectMergeTestExam($this->class);

    Livewire::test(ExamSubjectMergesRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'name' => 'Bangla',
            'first_subject_id' => $this->banglaFirst->id,
            'second_subject_id' => $this->banglaSecond->id,
            'pass_rule' => MergedPassRule::EachPaper->value,
            'component_pass_rule' => MergedComponentPassRule::EachPaper->value,
            'absent_rule' => MergedAbsentRule::Fail->value,
            'rounding' => ResultRounding::Down->value,
        ])
        ->assertHasNoFormErrors();

    expect($exam->refresh()->has_custom_subject_merges)->toBeTrue();

    SubjectMerge::factory()->create([
        'class_id' => $this->class->id,
        'name' => 'Class Bangla',
        'first_subject_id' => $this->banglaFirst->id,
        'second_subject_id' => $this->banglaSecond->id,
    ]);

    assertDatabaseMissing(ExamSubjectMerge::class, ['exam_id' => $exam->id, 'name' => 'Class Bangla']);

    Livewire::test(ExamSubjectMergesRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->callAction(TestAction::make('applyClassDefault')->table());

    expect($exam->refresh()->has_custom_subject_merges)->toBeFalse()
        ->and($exam->subjectMerges()->pluck('name')->all())->toBe(['Class Bangla']);
});

it('leaves merges already matching the class default untouched', function () {
    SubjectMerge::factory()->create([
        'class_id' => $this->class->id,
        'first_subject_id' => $this->banglaFirst->id,
        'second_subject_id' => $this->banglaSecond->id,
    ]);
    $exam = subjectMergeTestExam($this->class);
    app(CopySubjectMergesToExamAction::class)->handle($exam);
    $mergeId = $exam->subjectMerges()->value('id');

    app(CopySubjectMergesToExamAction::class)->handle($exam);

    expect($exam->subjectMerges()->pluck('id')->all())->toBe([$mergeId]);
});

it('swaps in the new class\'s default merges when an exam moves to another class', function () {
    $exam = subjectMergeTestExam($this->class);
    $exam->update(['has_custom_subject_merges' => true]);
    ExamSubjectMerge::factory()->create(['exam_id' => $exam->id, 'name' => 'Old Class Merge']);

    $otherClass = Classes::create(['name' => 'Class 7', 'order' => 7, 'is_active' => true]);
    SubjectMerge::factory()->create(['class_id' => $otherClass->id, 'name' => 'Class 7 Bangla']);

    Livewire::test(EditExam::class, ['record' => $exam->id])
        ->fillForm(['class_id' => $otherClass->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($exam->refresh()->has_custom_subject_merges)->toBeFalse()
        ->and($exam->subjectMerges()->pluck('name')->all())->toBe(['Class 7 Bangla']);
});
