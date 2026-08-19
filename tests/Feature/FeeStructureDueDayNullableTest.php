<?php

use App\Enums\UserType;
use App\Filament\Resources\FeeStructures\FeeStructureResource;
use App\Filament\Resources\FeeStructures\Pages\CreateFeeStructure;
use App\Models\Classes;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

/**
 * Regression test: the `due_day` column was NOT NULL with no default, but the
 * form field has never had ->required() — so submitting the form without a
 * due day (which the UI allows) crashed with a SQLite/MySQL NOT NULL
 * constraint violation instead of saving.
 */
it('creates a fee structure without a due_day, since the field is optional in the form', function () {
    Filament::setCurrentPanel('admin');

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 8', 'order' => 8, 'is_active' => true]);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);

    Livewire::test(CreateFeeStructure::class)
        ->fillForm([
            'class_id' => $class->id,
            'fee_type_id' => $feeType->id,
            'amount' => 500,
            'due_day' => null,
            'session_year' => now()->year,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(FeeStructure::class, [
        'class_id' => $class->id,
        'fee_type_id' => $feeType->id,
        'due_day' => null,
    ]);
});

it('renders the fee structures table when a row has no due_day', function () {
    Filament::setCurrentPanel('admin');

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 9', 'order' => 9, 'is_active' => true]);
    $feeType = FeeType::create(['name' => 'Admission Fee', 'is_monthly' => false, 'is_active' => true]);

    FeeStructure::create([
        'class_id' => $class->id,
        'fee_type_id' => $feeType->id,
        'amount' => 1000,
        'session_year' => now()->year,
        'is_active' => true,
    ]);

    test()->get(FeeStructureResource::getUrl('index'))->assertOk();
});
