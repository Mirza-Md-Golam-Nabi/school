<?php

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('serves a public install guide inside each app panel path', function (string $panel, string $appLabel) {
    test()->get("/{$panel}/install")
        ->assertOk()
        ->assertSee("{$appLabel} অ্যাপ ইন্সটল করুন")
        ->assertSee("/manifest/{$panel}.json", false)
        ->assertSee('data-pwa-guide', false)
        ->assertSee('data-pwa-install', false)
        ->assertSee('data-pwa-steps="android"', false)
        ->assertSee('data-pwa-steps="ios"', false)
        ->assertSee('data-pwa-steps="desktop"', false)
        ->assertSee('data-pwa-steps="inapp"', false)
        ->assertSee("navigator.serviceWorker.register('/sw.js')", false);
})->with([
    'student' => ['student', 'শিক্ষার্থী'],
    'teacher' => ['teacher', 'শিক্ষক'],
]);

it('has no install guide for the admin panel', function () {
    test()->get('/admin/install')->assertNotFound();
});

it('offers the install button on the student and teacher login pages', function (string $panel) {
    test()->get("/{$panel}/login")
        ->assertOk()
        ->assertSee('class="pwa-login"', false)
        ->assertSee(route('install-guide', ['panel' => $panel]), false)
        ->assertSee("navigator.serviceWorker.register('/sw.js')", false)
        ->assertDontSee('class="pwa-banner"', false);
})->with(['student', 'teacher']);

it('does not offer an app install on the admin login page', function () {
    test()->get('/admin/login')
        ->assertOk()
        ->assertDontSee('data-pwa-install', false);
});

it('shows the topbar button, user menu item and banner inside the student panel', function () {
    $student = makeStudent(['must_change_password' => false]);

    $response = test()->actingAs($student)->get('/student');

    expect(substr_count($response->getContent(), 'data-pwa-install'))
        ->toBeGreaterThanOrEqual(3, 'topbar button, user menu item and banner should all trigger the install');

    $response
        ->assertOk()
        ->assertSee('class="pwa-topbar-btn"', false)
        ->assertSee('class="pwa-banner"', false)
        ->assertSee('data-pwa-dismiss', false)
        ->assertSee(route('install-guide', ['panel' => 'student']), false);
});

it('hides install prompts once installed and lets iPhone users confirm an install', function () {
    $student = makeStudent(['must_change_password' => false]);

    test()->actingAs($student)
        ->get('/student')
        ->assertOk()
        ->assertSee("root.classList.toggle('pwa-installed', isInstalled())", false)
        ->assertSee('html.pwa-installed [data-pwa-hide-when-installed]', false)
        ->assertSee('data-pwa-confirm-installed', false)
        ->assertSee('html[data-pwa-platform="ios"] .pwa-banner__confirm', false);
});

it('lets a browser that remembers an old install reset it from the guide page', function () {
    test()->get('/student/install')
        ->assertOk()
        ->assertSee('data-pwa-reset', false)
        ->assertSee('html.pwa-installed .installed', false);
});

it('shows the topbar button and banner inside the teacher panel', function () {
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    $teacher = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]);
    $teacher->assignRole('teacher');

    test()->actingAs($teacher)
        ->get('/teacher')
        ->assertOk()
        ->assertSee('class="pwa-topbar-btn"', false)
        ->assertSee('class="pwa-banner"', false)
        ->assertSee(route('install-guide', ['panel' => 'teacher']), false);
});

it('links to both app install guides from the home page', function () {
    test()->get(route('home'))
        ->assertOk()
        ->assertSee(route('install-guide', ['panel' => 'student']), false)
        ->assertSee(route('install-guide', ['panel' => 'teacher']), false);
});

it('scopes each app manifest to its own panel so both install separately', function (string $panel) {
    $manifest = json_decode(file_get_contents(public_path("manifest/{$panel}.json")), true);

    expect($manifest)
        ->id->toBe("/{$panel}")
        ->scope->toBe("/{$panel}")
        ->start_url->toBe("/{$panel}")
        ->display->toBe('standalone');
})->with(['student', 'teacher']);
