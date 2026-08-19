<?php

use App\Enums\NoticeTargetType;
use App\Enums\UserType;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('logs notice creation with a resolved creator label and target audience', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    test()->actingAs($admin);

    $notice = Notice::create([
        'title' => 'Exam Schedule',
        'body' => 'The exam schedule has been published.',
        'target_type' => NoticeTargetType::Students,
        'send_sms' => false,
        'published_at' => now(),
        'created_by' => $admin->id,
    ]);

    $createdActivity = Activity::where('log_name', 'notice')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->causer_id->toBe($admin->id)
        ->description->toBe('Created notice "Exam Schedule" targeting All Students.');

    expect($createdActivity->properties->get('attributes'))
        ->toMatchArray([
            'created_by' => $admin->id,
            'created_by_label' => $admin->name,
        ]);

    $notice->update(['title' => 'Exam Schedule (Updated)']);

    $updatedActivity = Activity::where('log_name', 'notice')->where('event', 'updated')->first();

    expect($updatedActivity)->not->toBeNull()
        ->description->toBe('Updated notice "Exam Schedule (Updated)".');

    $notice->delete();

    $deletedActivity = Activity::where('log_name', 'notice')->where('event', 'deleted')->first();

    expect($deletedActivity)->not->toBeNull()
        ->description->toBe('Deleted notice "Exam Schedule (Updated)".');
});
