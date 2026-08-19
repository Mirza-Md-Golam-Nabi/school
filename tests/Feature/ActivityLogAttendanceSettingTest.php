<?php

use App\Enums\AttendanceMode;
use App\Enums\UserType;
use App\Models\AttendanceSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('logs create and update of attendance settings with a readable mode label', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    test()->actingAs($admin);

    $setting = AttendanceSetting::create([
        'attendance_mode' => AttendanceMode::Daily,
        'late_threshold_minutes' => 15,
        'entry_time' => '08:00:00',
        'exit_time' => '14:00:00',
    ]);

    $createdActivity = Activity::where('log_name', 'attendance_setting')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->causer_id->toBe($admin->id)
        ->description->toBe('Created attendance settings.');

    expect($createdActivity->properties->get('attributes'))
        ->toMatchArray([
            'attendance_mode' => 'daily',
            'attendance_mode_label' => 'Daily (Once per day)',
            'late_threshold_minutes' => 15,
        ]);

    $setting->update(['attendance_mode' => AttendanceMode::ClassWise]);

    $updatedActivity = Activity::where('log_name', 'attendance_setting')->where('event', 'updated')->first();

    expect($updatedActivity)->not->toBeNull()
        ->description->toBe('Updated attendance settings.');

    expect($updatedActivity->properties->get('attributes'))
        ->toMatchArray([
            'attendance_mode' => 'class_wise',
            'attendance_mode_label' => 'Class-wise (Per subject)',
        ]);
});
