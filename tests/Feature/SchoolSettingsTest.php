<?php

use App\Enums\UserType;
use App\Filament\Pages\SchoolSettings;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('saves the school established year and marksheet document settings', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    Livewire::test(SchoolSettings::class)
        ->fillForm([
            'school_name' => 'Test School',
            'school_established_year' => 2000,
            'marksheet_use_logo' => true,
            'marksheet_use_watermark' => true,
            'marksheet_watermark_text' => 'ORIGINAL',
            'marksheet_footer_text' => 'Principal',
        ])
        ->call('save')
        ->assertNotified();

    expect(SchoolSetting::get('school_established_year'))->toBe('2000')
        ->and(SchoolSetting::get('marksheet_use_logo'))->toBe('1')
        ->and(SchoolSetting::get('marksheet_use_watermark'))->toBe('1')
        ->and(SchoolSetting::get('marksheet_watermark_text'))->toBe('ORIGINAL')
        ->and(SchoolSetting::get('marksheet_footer_text'))->toBe('Principal');
});

it('loads previously saved settings back into the form', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    SchoolSetting::set('school_established_year', '1998');
    SchoolSetting::set('marksheet_footer_text', 'Head Teacher');

    Livewire::test(SchoolSettings::class)
        ->assertSchemaStateSet([
            'school_established_year' => '1998',
            'marksheet_footer_text' => 'Head Teacher',
        ]);
});
