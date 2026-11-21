<?php

use App\Models\User;

test('guests are redirected to the filament login page', function () {
    $this->get('/admin')
        ->assertRedirect('/admin/login');
});

test('verified administrators can access the filament panel', function () {
    $administrator = User::factory()->admin()->create();

    $this->actingAs($administrator)
        ->get('/admin')
        ->assertOk();
});

test('non administrators cannot access the filament panel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

test('unverified administrators cannot access the filament panel', function () {
    $administrator = User::factory()->admin()->unverified()->create();

    $this->actingAs($administrator)
        ->get('/admin')
        ->assertForbidden();
});
