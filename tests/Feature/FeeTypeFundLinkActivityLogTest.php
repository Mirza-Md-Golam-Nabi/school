<?php

use App\Enums\UserType;
use App\Filament\Resources\SchoolAccounts\Pages\EditSchoolAccount;
use App\Models\FeeType;
use App\Models\SchoolAccount;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('describes linking a fee type to a fund from the school account edit page, instead of a bare "updated"', function () {
    Filament::setCurrentPanel('admin');

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $account = SchoolAccount::create(['name' => 'Main Account', 'current_balance' => 0]);
    $feeType = FeeType::create(['name' => 'Exam Fee - Annual', 'is_monthly' => false, 'is_active' => true]);

    Livewire::test(EditSchoolAccount::class, ['record' => $account->id])
        ->fillForm(['fee_type_ids' => [$feeType->id], 'resync_mode' => 'none'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($feeType->fresh()->school_account_id)->toBe($account->id);

    $activity = Activity::where('log_name', 'fee_type')
        ->where('event', 'updated')
        ->where('subject_id', $feeType->id)
        ->first();

    expect($activity)->not->toBeNull()
        ->description->toBe('Linked fee type "Exam Fee - Annual" to fund "Main Account".');
});

it('describes unlinking a fee type from a fund from the school account edit page', function () {
    Filament::setCurrentPanel('admin');

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $account = SchoolAccount::create(['name' => 'Main Account', 'current_balance' => 0]);
    $feeType = FeeType::create([
        'name' => 'Exam Fee - Annual',
        'is_monthly' => false,
        'is_active' => true,
        'school_account_id' => $account->id,
    ]);

    Livewire::test(EditSchoolAccount::class, ['record' => $account->id])
        ->fillForm(['fee_type_ids' => [], 'resync_mode' => 'none'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($feeType->fresh()->school_account_id)->toBeNull();

    $activity = Activity::where('log_name', 'fee_type')
        ->where('event', 'updated')
        ->where('subject_id', $feeType->id)
        ->first();

    expect($activity)->not->toBeNull()
        ->description->toBe('Unlinked fee type "Exam Fee - Annual" from its fund.');
});
