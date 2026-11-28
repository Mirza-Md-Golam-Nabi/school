<?php

use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Filament\Resources\Classes\Pages\EditClasses;
use App\Filament\Resources\Classes\RelationManagers\SubjectsRelationManager;
use App\Models\Classes;
use App\Models\Subject;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $this->class = Classes::create(['name' => 'Class Five', 'order' => 5]);
    $this->subject = Subject::create(['name' => 'Physics', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $this->class->subjects()->attach($this->subject->id, ['subject_type' => SubjectType::Compulsory]);
});

it('offers no bulk delete on a class subject list, so the subject itself cannot be deleted from here', function () {
    Livewire::test(SubjectsRelationManager::class, ['ownerRecord' => $this->class, 'pageClass' => EditClasses::class])
        ->assertActionDoesNotExist(TestAction::make('delete')->table()->bulk());
});

it('bulk remove only detaches the subject from the class', function () {
    Livewire::test(SubjectsRelationManager::class, ['ownerRecord' => $this->class, 'pageClass' => EditClasses::class])
        ->selectTableRecords([$this->subject->id])
        ->callAction(TestAction::make('detach')->table()->bulk());

    expect($this->class->subjects()->count())->toBe(0)
        ->and(Subject::find($this->subject->id))->not->toBeNull();
});
