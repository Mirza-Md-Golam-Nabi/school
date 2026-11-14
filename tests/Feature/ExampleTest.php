<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

it('shows the school name from config and links to every panel on the home page', function () {
    config(['app.school_name' => 'Sunrise High School']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Sunrise High School')
        ->assertSee(url('/admin'), false)
        ->assertSee('Admin Panel')
        ->assertSee('Teacher Panel')
        ->assertSee('Student Panel');
});
