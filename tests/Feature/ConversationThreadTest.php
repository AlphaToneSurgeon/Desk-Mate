<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

test('threadEndingAt reconstructs the correct branch from a leaf, ignoring sibling branches', function () {
    $user = User::factory()->create();
    $conversation = $user->conversations()->create(['title' => 'Test']);

    $root = Message::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'Racine',
    ]);

    $branchA = Message::create([
        'conversation_id' => $conversation->id,
        'parent_message_id' => $root->id,
        'role' => 'assistant',
        'content' => 'Branche A',
    ]);

    // Un deuxième message avec le MÊME parent que $branchA : un frère,
    // qui ne doit JAMAIS apparaître dans le fil reconstruit à partir de $branchA.
    Message::create([
        'conversation_id' => $conversation->id,
        'parent_message_id' => $root->id,
        'role' => 'assistant',
        'content' => 'Branche B (ne doit pas apparaître)',
    ]);

    $leaf = Message::create([
        'conversation_id' => $conversation->id,
        'parent_message_id' => $branchA->id,
        'role' => 'user',
        'content' => 'Suite de la branche A',
    ]);

    $thread = $conversation->threadEndingAt($leaf->id);

    expect($thread)->toHaveCount(3);
    expect(array_map(fn ($m) => $m->content, $thread))->toBe([
        'Racine',
        'Branche A',
        'Suite de la branche A',
    ]);
});

test('activeThread returns an empty array for a conversation with no messages', function () {
    $user = User::factory()->create();
    $conversation = $user->conversations()->create(['title' => null]);

    expect($conversation->activeThread())->toBe([]);
});
