<?php

use App\Enums\UserType;
use App\Filament\Pages\SchoolSettings;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Support\AcademicSession;
use App\Support\DocumentLocale;
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

it('saves the school EIIN number and prints it as an EIIN line', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    expect(SchoolSetting::eiinLine())->toBe('');

    Livewire::test(SchoolSettings::class)
        ->fillForm([
            'school_name' => 'Test School',
            SchoolSetting::EIIN_SETTING => ' 123456 ',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(SchoolSetting::get(SchoolSetting::EIIN_SETTING))->toBe('123456')
        ->and(SchoolSetting::eiinLine())->toBe('EIIN: 123456');

    Livewire::test(SchoolSettings::class)
        ->assertSchemaStateSet([SchoolSetting::EIIN_SETTING => '123456']);
});

it('defaults the college session start month to July and saves a new one', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    Livewire::test(SchoolSettings::class)
        ->assertSchemaStateSet([AcademicSession::COLLEGE_START_MONTH_SETTING => 7])
        ->fillForm([
            'school_name' => 'Test School',
            AcademicSession::COLLEGE_START_MONTH_SETTING => 6,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(SchoolSetting::get(AcademicSession::COLLEGE_START_MONTH_SETTING))->toBe('6')
        ->and(AcademicSession::collegeStartMonth())->toBe(6);
});

it('rejects an EIIN number that is not digits only', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    Livewire::test(SchoolSettings::class)
        ->fillForm([
            'school_name' => 'Test School',
            SchoolSetting::EIIN_SETTING => '12AB',
        ])
        ->call('save')
        ->assertHasFormErrors([SchoolSetting::EIIN_SETTING => 'regex'])
        ->assertNotNotified();
});

it('saves the বাংলা school name and address and prints them only in বাংলা documents', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    Livewire::test(SchoolSettings::class)
        ->fillForm([
            'school_name' => 'Test School',
            'school_address' => 'Dhaka',
            SchoolSetting::SCHOOL_NAME_BN_SETTING => ' টেস্ট স্কুল ',
            SchoolSetting::SCHOOL_ADDRESS_BN_SETTING => 'ঢাকা',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(SchoolSetting::get(SchoolSetting::SCHOOL_NAME_BN_SETTING))->toBe('টেস্ট স্কুল')
        ->and(SchoolSetting::schoolName())->toBe('Test School')
        ->and(SchoolSetting::schoolAddress())->toBe('Dhaka')
        ->and(DocumentLocale::using('bn', fn (): string => SchoolSetting::schoolName()))->toBe('টেস্ট স্কুল')
        ->and(DocumentLocale::using('bn', fn (): string => SchoolSetting::schoolAddress()))->toBe('ঢাকা');

    Livewire::test(SchoolSettings::class)
        ->assertSchemaStateSet([
            SchoolSetting::SCHOOL_NAME_BN_SETTING => 'টেস্ট স্কুল',
            SchoolSetting::SCHOOL_ADDRESS_BN_SETTING => 'ঢাকা',
        ]);
});

it('falls back to the english school name and address in বাংলা when no বাংলা ones are set', function () {
    SchoolSetting::set('school_name', 'Test School');
    SchoolSetting::set('school_address', 'Dhaka');

    expect(DocumentLocale::using('bn', fn (): string => SchoolSetting::schoolName()))->toBe('Test School')
        ->and(DocumentLocale::using('bn', fn (): string => SchoolSetting::schoolAddress()))->toBe('Dhaka');
});
