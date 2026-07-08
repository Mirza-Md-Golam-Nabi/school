<?php

use App\Enums\UserType;
use App\Models\Classes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('prefills the class field when classId is passed as a query parameter', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);

    $response = $this->actingAs($admin)->get("/admin/student-profiles/create?classId={$class->id}");

    $response->assertOk();
    $response->assertSee('&quot;current_class_id&quot;:'.$class->id, false);
});

it('leaves the class field empty when no classId is passed', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);

    $response = $this->actingAs($admin)->get('/admin/student-profiles/create');

    $response->assertOk();
    $response->assertDontSee('&quot;current_class_id&quot;:'.$class->id, false);
});
