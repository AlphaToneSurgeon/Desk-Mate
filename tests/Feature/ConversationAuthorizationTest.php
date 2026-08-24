<?php

use App\Models\User;

test('a user cannot view another users conversation', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $conversation = $owner->conversations()->create(['title' => 'Privé']);

    $response = $this->actingAs($intruder)->get(route('conversations.show', $conversation));

    $response->assertForbidden();
});

test('a user can view their own conversation', function () {
    $user = User::factory()->create();
    $conversation = $user->conversations()->create(['title' => 'À moi']);

    $response = $this->actingAs($user)->get(route('conversations.show', $conversation));

    $response->assertOk();
});

test('a guest is redirected to login when trying to view a conversation', function () {
    $user = User::factory()->create();
    $conversation = $user->conversations()->create(['title' => 'Test']);

    $response = $this->get(route('conversations.show', $conversation));

    $response->assertRedirect(route('login'));
});
