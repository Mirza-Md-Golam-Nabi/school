<?php

use App\Enums\UserType;
use App\Filament\Resources\FeeStructures\Pages\ListFeeStructures;
use App\Filament\Resources\FeeStructures\Pages\ManageClassFeeStructures;
use App\Models\Classes;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeSessionYearCopyTestStructure(Classes $class, FeeType $feeType, int $sessionYear, float $amount = 500, bool $isActive = true): FeeStructure
{
    return FeeStructure::create([
        'class_id' => $class->id,
        'fee_type_id' => $feeType->id,
        'amount' => $amount,
        'due_day' => 10,
        'session_year' => $sessionYear,
        'is_active' => $isActive,
    ]);
}

it("copies a class's active fee structures to the new session and keeps the old session intact", function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $class = Classes::create(['name' => 'Year Copy Class 6', 'order' => 6]);
    $otherClass = Classes::create(['name' => 'Year Copy Class 7', 'order' => 7]);
    $tuition = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    $session = FeeType::create(['name' => 'Session Charge', 'is_monthly' => false, 'is_active' => true]);
    $retired = FeeType::create(['name' => 'Old Lab Fee', 'is_monthly' => false, 'is_active' => true]);

    $oldTuition = makeSessionYearCopyTestStructure($class, $tuition, 2026, 500);
    makeSessionYearCopyTestStructure($class, $session, 2026, 1000);
    makeSessionYearCopyTestStructure($class, $retired, 2026, 300, isActive: false);
    makeSessionYearCopyTestStructure($otherClass, $tuition, 2026);

    Livewire::withQueryParams(['class' => $class->id])
        ->test(ManageClassFeeStructures::class)
        ->mountAction('copyToNewSession')
        ->assertSchemaStateSet(['from_year' => 2026, 'to_year' => 2027])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Fee structures copied');

    $copies = FeeStructure::where('session_year', 2027)->get();

    expect($oldTuition->fresh()->session_year)->toBe(2026)
        ->and(FeeStructure::where('session_year', 2026)->count())->toBe(4)
        ->and($copies)->toHaveCount(2)
        ->and($copies->pluck('class_id')->unique()->all())->toBe([$class->id])
        ->and((float) $copies->firstWhere('fee_type_id', $tuition->id)->amount)->toBe(500.0)
        ->and((float) $copies->firstWhere('fee_type_id', $session->id)->amount)->toBe(1000.0)
        ->and($copies->firstWhere('fee_type_id', $tuition->id)->due_day)->toBe(10);
});

it('does not overwrite a fee structure that already exists in the new session', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $class = Classes::create(['name' => 'Year Copy Class 6', 'order' => 6]);
    $tuition = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    $session = FeeType::create(['name' => 'Session Charge', 'is_monthly' => false, 'is_active' => true]);

    makeSessionYearCopyTestStructure($class, $tuition, 2026, 500);
    makeSessionYearCopyTestStructure($class, $session, 2026, 1000);
    $alreadyRevised = makeSessionYearCopyTestStructure($class, $tuition, 2027, 600);

    Livewire::withQueryParams(['class' => $class->id])
        ->test(ManageClassFeeStructures::class)
        ->callAction('copyToNewSession', ['from_year' => 2026, 'to_year' => 2027])
        ->assertHasNoActionErrors();

    expect(FeeStructure::where('session_year', 2027)->count())->toBe(2)
        ->and((float) $alreadyRevised->fresh()->amount)->toBe(600.0);
});

it('requires the new session year to differ from the source one', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $class = Classes::create(['name' => 'Year Copy Class 6', 'order' => 6]);
    $tuition = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    makeSessionYearCopyTestStructure($class, $tuition, 2026);

    Livewire::withQueryParams(['class' => $class->id])
        ->test(ManageClassFeeStructures::class)
        ->callAction('copyToNewSession', ['from_year' => 2026, 'to_year' => 2026])
        ->assertHasActionErrors(['to_year']);

    expect(FeeStructure::count())->toBe(1);
});

it('copies fee structures for every class from the all-classes page', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $classSix = Classes::create(['name' => 'Year Copy Class 6', 'order' => 6]);
    $classSeven = Classes::create(['name' => 'Year Copy Class 7', 'order' => 7]);
    $tuition = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    $session = FeeType::create(['name' => 'Session Charge', 'is_monthly' => false, 'is_active' => true]);

    makeSessionYearCopyTestStructure($classSix, $tuition, 2026);
    makeSessionYearCopyTestStructure($classSix, $session, 2026);
    makeSessionYearCopyTestStructure($classSeven, $tuition, 2026);
    makeSessionYearCopyTestStructure($classSeven, $session, 2025);

    Livewire::test(ListFeeStructures::class)
        ->mountAction('copyToNewSession')
        ->assertSchemaStateSet(['from_year' => 2026, 'to_year' => 2027])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Fee structures copied');

    expect(FeeStructure::where('session_year', 2027)->where('class_id', $classSix->id)->count())->toBe(2)
        ->and(FeeStructure::where('session_year', 2027)->where('class_id', $classSeven->id)->count())->toBe(1)
        ->and(FeeStructure::where('session_year', 2026)->count())->toBe(3)
        ->and(FeeStructure::where('session_year', 2025)->count())->toBe(1);
});

it("shows only each class's latest session fees on the all-classes cards", function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $class = Classes::create(['name' => 'Year Copy Class 6', 'order' => 6]);
    $tuition = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);

    makeSessionYearCopyTestStructure($class, $tuition, 2026, 500);
    makeSessionYearCopyTestStructure($class, $tuition, 2027, 650);

    Livewire::test(ListFeeStructures::class)
        ->assertSee('৳650')
        ->assertDontSee('৳500')
        ->assertSee('2027')
        ->assertDontSee('2026');
});

it('shows the current and next session together once the next one has fee structures', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $class = Classes::create(['name' => 'Year Copy Class 6', 'order' => 6]);
    $tuition = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    $year = now()->year;

    $past = makeSessionYearCopyTestStructure($class, $tuition, $year - 1);
    $current = makeSessionYearCopyTestStructure($class, $tuition, $year);
    $next = makeSessionYearCopyTestStructure($class, $tuition, $year + 1);

    $component = Livewire::withQueryParams(['class' => $class->id])
        ->test(ManageClassFeeStructures::class)
        ->assertCanSeeTableRecords([$next, $current], inOrder: true)
        ->assertCanNotSeeTableRecords([$past])
        ->filterTable('session_year', [$year])
        ->assertCanSeeTableRecords([$current])
        ->assertCanNotSeeTableRecords([$next, $past]);

    expect(array_keys($component->instance()->getTable()->getFilter('session_year')->getOptions()))
        ->toBe([$year + 1, $year]);
});

it('shows the copied session right after copying to a new session', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $class = Classes::create(['name' => 'Year Copy Class 6', 'order' => 6]);
    $tuition = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    $year = now()->year;
    $current = makeSessionYearCopyTestStructure($class, $tuition, $year);

    Livewire::withQueryParams(['class' => $class->id])
        ->test(ManageClassFeeStructures::class)
        ->callAction('copyToNewSession', ['from_year' => $year, 'to_year' => $year + 1])
        ->assertHasNoActionErrors();

    $copy = FeeStructure::where('session_year', $year + 1)->sole();

    Livewire::withQueryParams(['class' => $class->id])
        ->test(ManageClassFeeStructures::class)
        ->assertCanSeeTableRecords([$copy, $current], inOrder: true);
});

it('shows only the current session when the next one has no fee structures yet', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $class = Classes::create(['name' => 'Year Copy Class 6', 'order' => 6]);
    $otherClass = Classes::create(['name' => 'Year Copy Class 7', 'order' => 7]);
    $tuition = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    $year = now()->year;

    $past = makeSessionYearCopyTestStructure($class, $tuition, $year - 1);
    $current = makeSessionYearCopyTestStructure($class, $tuition, $year);
    // Another class's next-session structure must not change this class's view.
    makeSessionYearCopyTestStructure($otherClass, $tuition, $year + 1);

    $component = Livewire::withQueryParams(['class' => $class->id])
        ->test(ManageClassFeeStructures::class)
        ->assertCanSeeTableRecords([$current])
        ->assertCanNotSeeTableRecords([$past])
        ->filterTable('session_year', [$year - 1])
        ->assertCanSeeTableRecords([$past])
        ->assertCanNotSeeTableRecords([$current]);

    expect(array_keys($component->instance()->getTable()->getFilter('session_year')->getOptions()))
        ->toBe([$year, $year - 1]);
});

it('falls back to the previous session when the class only has fee structures there', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $class = Classes::create(['name' => 'Year Copy Class 6', 'order' => 6]);
    $tuition = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    $past = makeSessionYearCopyTestStructure($class, $tuition, now()->year - 1);

    Livewire::withQueryParams(['class' => $class->id])
        ->test(ManageClassFeeStructures::class)
        ->assertCanSeeTableRecords([$past]);
});
