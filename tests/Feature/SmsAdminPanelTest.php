<?php

use App\Enums\Gender;
use App\Enums\NoticeTargetType;
use App\Enums\SmsEvent;
use App\Enums\SmsRechargeRequestStatus;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Pages\SmsSettings;
use App\Filament\Resources\Notices\Pages\CreateNotice;
use App\Filament\Resources\SmsRechargeRequests\Pages\ListSmsRechargeRequests;
use App\Filament\Resources\SmsVendorResponseCodes\Pages\ManageSmsVendorResponseCodes;
use App\Filament\Resources\SmsVendorResponseCodes\SmsVendorResponseCodeResource;
use App\Filament\Resources\SmsVendors\Pages\CreateSmsVendor;
use App\Filament\Resources\SmsVendors\Pages\ListSmsVendors;
use App\Filament\Resources\SmsVendors\SmsVendorResource;
use App\Models\Notice;
use App\Models\Permission;
use App\Models\SmsRechargeRequest;
use App\Models\SmsSetting;
use App\Models\SmsVendor;
use App\Models\SmsVendorResponseCode;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\SmsVendorResponseCodeSeeder;
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

it('does not let a school admin change the per-SMS charge', function () {
    SmsSetting::current()->update(['rate_per_sms' => 0.25]);
    test()->actingAs(smsSchoolAdmin());

    Livewire::test(SmsSettings::class)
        ->assertFormFieldIsDisabled('rate_per_sms')
        ->fillForm(['rate_per_sms' => 0.01])
        ->call('save');

    expect(SmsSetting::current()->ratePerSms())->toBe(0.25);
});

it('lets the super-admin set the per-SMS charge', function () {
    test()->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::SuperAdmin, 'is_active' => true])));

    Livewire::test(SmsSettings::class)
        ->fillForm(['rate_per_sms' => 0.35])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(SmsSetting::current()->ratePerSms())->toBe(0.35);
});

it('keeps the SMS menu away from users without the SMS permission', function () {
    test()->actingAs(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    Livewire::test(SmsSettings::class)->assertForbidden();
});

it('lets an admin switch a vendor off but not add or edit vendors', function () {
    $vendor = SmsVendor::factory()->create(['api_key' => 'very-secret-key']);
    test()->actingAs(smsSchoolAdmin());

    Livewire::test(ListSmsVendors::class)
        ->assertCanSeeTableRecords([$vendor])
        ->assertDontSee('very-secret-key')
        ->assertActionHidden(CreateAction::class)
        ->assertActionHidden(TestAction::make(EditAction::class)->table($vendor))
        ->assertActionHidden(TestAction::make(DeleteAction::class)->table($vendor))
        ->call('updateTableColumnState', 'is_active', (string) $vendor->id, false);

    expect($vendor->refresh()->is_active)->toBeFalse()
        ->and(SmsVendorResource::canCreate())->toBeFalse()
        ->and(SmsVendorResource::canEdit($vendor))->toBeFalse();

    $this->get(SmsVendorResource::getUrl('edit', ['record' => $vendor]))->assertForbidden();
    $this->get(SmsVendorResource::getUrl('create'))->assertForbidden();
});

it('lets the super-admin add a vendor with an encrypted API key', function () {
    test()->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::SuperAdmin, 'is_active' => true])));

    Livewire::test(CreateSmsVendor::class)
        ->fillForm([
            'name' => 'BulkSMSBD',
            'driver' => 'bulksmsbd',
            'sender_id' => '8809617613451',
            'api_key' => 'plain-api-key',
            'priority' => 1,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $vendor = SmsVendor::sole();

    expect($vendor->api_key)->toBe('plain-api-key')
        ->and($vendor->getRawOriginal('api_key'))->not->toBe('plain-api-key')
        ->and($vendor->sender_id)->toBe('8809617613451');
});

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

it('shows the provider response codes to an admin without letting them change them', function () {
    $this->seed(SmsVendorResponseCodeSeeder::class);
    test()->actingAs(smsSchoolAdmin());

    Livewire::test(ManageSmsVendorResponseCodes::class)
        ->assertCanSeeTableRecords(SmsVendorResponseCode::all())
        ->assertSee('ip Not whitelisted')
        ->assertActionHidden(CreateAction::class);

    expect(SmsVendorResponseCodeResource::canEdit(SmsVendorResponseCode::first()))->toBeFalse();
});

it('lets the super-admin add a response code but not a duplicate one', function () {
    $this->seed(SmsVendorResponseCodeSeeder::class);
    test()->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::SuperAdmin, 'is_active' => true])));

    Livewire::test(ManageSmsVendorResponseCodes::class)
        ->callAction(CreateAction::class, ['driver' => 'bulksmsbd', 'code' => '1040', 'message' => 'New provider error', 'is_success' => false])
        ->assertHasNoActionErrors()
        ->callAction(CreateAction::class, ['driver' => 'bulksmsbd', 'code' => '1007', 'message' => 'Duplicate', 'is_success' => false])
        ->assertHasActionErrors(['code' => 'unique']);

    expect(SmsVendorResponseCode::where('code', '1040')->sole()->message)->toBe('New provider error')
        ->and(SmsVendorResponseCode::where('code', '1007')->sole()->message)->toBe('Balance Insufficient');
});
