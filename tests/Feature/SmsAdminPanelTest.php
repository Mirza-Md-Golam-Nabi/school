<?php

use App\Enums\Gender;
use App\Enums\NoticeTargetType;
use App\Enums\SmsEvent;
use App\Enums\SmsLanguage;
use App\Enums\SmsRechargeRequestStatus;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Pages\SmsSettings;
use App\Filament\Resources\Notices\Pages\CreateNotice;
use App\Filament\Resources\SmsRechargeRequests\Pages\ListSmsRechargeRequests;
use App\Filament\Resources\SmsVendors\Pages\ListSmsVendors;
use App\Filament\Resources\SmsVendors\SmsVendorResource;
use App\Models\Notice;
use App\Models\Permission;
use App\Models\SmsRechargeRequest;
use App\Models\SmsSetting;
use App\Models\SmsVendor;
use App\Models\StudentProfile;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    config(['sms.recharge_request_url' => '']);
});

/**
 * A school admin who manages SMS but is not the super-admin.
 */
function smsSchoolAdmin(): User
{
    $user = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);

    foreach (['manage_sms_settings', 'view_sms_logs', 'create_notices', 'view_notices'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        $user->givePermissionTo($permission);
    }

    app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

it('lets an admin switch events on and edit templates', function () {
    test()->actingAs(smsSchoolAdmin());

    Livewire::test(SmsSettings::class)
        ->fillForm([
            'is_enabled' => true,
            'events.'.SmsEvent::AttendanceAbsent->value => true,
            'templates.'.SmsEvent::AttendanceAbsent->value => '{name} absent today',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $setting = SmsSetting::current();

    expect($setting->is_enabled)->toBeTrue()
        ->and($setting->enabled_events)->toBe([SmsEvent::AttendanceAbsent->value])
        ->and($setting->template(SmsEvent::AttendanceAbsent))->toBe('{name} absent today');
});

it('stores an untouched default template empty so it follows later default changes', function () {
    test()->actingAs(smsSchoolAdmin());

    Livewire::test(SmsSettings::class)
        ->fillForm([
            'is_enabled' => true,
            'events.'.SmsEvent::FeePaymentReceived->value => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $setting = SmsSetting::current();

    expect($setting->templates[SmsEvent::FeePaymentReceived->value])->toBe('')
        ->and($setting->template(SmsEvent::FeePaymentReceived))->toBe(SmsEvent::FeePaymentReceived->defaultTemplate());
});

it('shows the per-SMS charge but lets no one change it from the panel, not even the super-admin', function (Closure $user) {
    SmsSetting::current()->update(['rate_per_sms' => 0.25]);
    test()->actingAs($user());

    Livewire::test(SmsSettings::class)
        ->assertSee('৳0.25')
        ->assertSee('NextFusion')
        ->assertFormFieldDoesNotExist('rate_per_sms')
        ->set('data.rate_per_sms', 0.01)
        ->call('save')
        ->assertHasNoFormErrors();

    expect(SmsSetting::current()->ratePerSms())->toBe(0.25);
})->with([
    'school admin' => [fn () => smsSchoolAdmin()],
    'super-admin' => [fn () => grantSuperAdmin(User::factory()->create(['user_type' => UserType::SuperAdmin, 'is_active' => true]))],
]);

it('keeps the SMS menu away from users without the SMS permission', function () {
    test()->actingAs(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    Livewire::test(SmsSettings::class)->assertForbidden();
});

it('shows the vendors without the API key and lets no one change them, not even the super-admin', function (Closure $user) {
    $vendor = SmsVendor::factory()->create(['api_key' => 'very-secret-key']);
    test()->actingAs($user());

    Livewire::test(ListSmsVendors::class)
        ->assertCanSeeTableRecords([$vendor])
        ->assertDontSee('very-secret-key')
        ->assertSee('NextFusion')
        ->assertActionDoesNotExist(CreateAction::class);

    expect(SmsVendorResource::canCreate())->toBeFalse()
        ->and(SmsVendorResource::canEdit($vendor))->toBeFalse()
        ->and(SmsVendorResource::canDelete($vendor))->toBeFalse()
        ->and(SmsVendorResource::hasPage('edit'))->toBeFalse()
        ->and(SmsVendorResource::hasPage('create'))->toBeFalse();
})->with([
    'school admin' => [fn () => smsSchoolAdmin()],
    'super-admin' => [fn () => grantSuperAdmin(User::factory()->create(['user_type' => UserType::SuperAdmin, 'is_active' => true]))],
]);

it('lets an admin send a recharge request', function () {
    SmsSetting::current()->update(['rate_per_sms' => 0.25]);
    test()->actingAs(smsSchoolAdmin());

    Livewire::test(ListSmsRechargeRequests::class)
        ->callAction('requestRecharge', ['amount' => 200, 'note' => 'bKash 8XYZ'])
        ->assertHasNoActionErrors();

    expect(SmsRechargeRequest::sole())
        ->estimated_sms->toBe(800)
        ->status->toBe(SmsRechargeRequestStatus::Pending)
        ->note->toBe('bKash 8XYZ');
});

it('stops a notice with SMS from publishing when the balance cannot cover it', function () {
    $setting = SmsSetting::current();
    $setting->fill(['is_enabled' => true, 'rate_per_sms' => 1, 'enabled_events' => [SmsEvent::Notice->value]])->save();
    SmsVendor::factory()->create();

    makeStudent(['phone' => '01711111111']);
    $student = User::where('user_type', UserType::Student)->sole();
    StudentProfile::create([
        'user_id' => $student->id,
        'roll_no' => 1,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    test()->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    Livewire::test(CreateNotice::class)
        ->fillForm([
            'title' => 'Exam',
            'body' => '<p>Exam on Sunday</p>',
            'target_type' => NoticeTargetType::Students->value,
            'send_sms' => true,
        ])
        ->call('create')
        ->assertNotified();

    expect(Notice::count())->toBe(0);
});

it('switches untouched templates to English when the SMS language changes, keeping custom ones', function () {
    test()->actingAs(smsSchoolAdmin());

    Livewire::test(SmsSettings::class)
        ->fillForm([
            'is_enabled' => true,
            'events.'.SmsEvent::FeePaymentReceived->value => true,
            'events.'.SmsEvent::AttendanceAbsent->value => true,
            'templates.'.SmsEvent::AttendanceAbsent->value => '{name} absent today',
        ])
        ->fillForm(['language' => SmsLanguage::English->value])
        ->assertSchemaStateSet([
            'templates.'.SmsEvent::FeePaymentReceived->value => SmsEvent::FeePaymentReceived->defaultTemplate(SmsLanguage::English),
            'templates.'.SmsEvent::AttendanceAbsent->value => '{name} absent today',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $setting = SmsSetting::current()->fresh();

    expect($setting->smsLanguage())->toBe(SmsLanguage::English)
        ->and($setting->templates[SmsEvent::FeePaymentReceived->value])->toBe('')
        ->and($setting->template(SmsEvent::FeePaymentReceived))->toBe(SmsEvent::FeePaymentReceived->defaultTemplate(SmsLanguage::English))
        ->and($setting->template(SmsEvent::AttendanceAbsent))->toBe('{name} absent today')
        // An event that is switched off follows the language too.
        ->and($setting->template(SmsEvent::Notice))->toBe(SmsEvent::Notice->defaultTemplate(SmsLanguage::English));
});

it('switches back to the Bangla defaults the same way', function () {
    SmsSetting::current()->forceFill(['language' => SmsLanguage::English])->save();
    test()->actingAs(smsSchoolAdmin());

    Livewire::test(SmsSettings::class)
        ->assertSchemaStateSet(['language' => SmsLanguage::English])
        ->fillForm(['language' => SmsLanguage::Bangla->value])
        ->assertSchemaStateSet([
            'templates.'.SmsEvent::Notice->value => SmsEvent::Notice->defaultTemplate(SmsLanguage::Bangla),
        ]);
});

it('keeps every English default in plain ASCII so it bills at 160 characters per part', function (SmsEvent $event) {
    expect($event->defaultTemplate(SmsLanguage::English))->toMatch('/^[\x20-\x7E]+$/');
})->with(SmsEvent::cases());
