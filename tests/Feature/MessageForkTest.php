<?php

use App\Models\AiModel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Construit un faux corps de réponse au format SSE d'OpenRouter, pour ne
 * pas dépendre d'un vrai appel réseau pendant les tests.
 */
function fakeSseResponse(string $content): string
{
    $delta = json_encode(['choices' => [['delta' => ['content' => $content]]]]);
    $usage = json_encode(['usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5]]);

    return "data: {$delta}\n\ndata: {$usage}\n\ndata: [DONE]\n\n";
}

test('regenerating a response creates a sibling message instead of replacing the original', function () {
    Http::fake([
        'openrouter.ai/*' => Http::response(fakeSseResponse('Nouvelle réponse'), 200),
    ]);

    $user = User::factory()->create();
    $model = AiModel::create([
        'slug' => 'test/model',
        'display_name' => 'Test Model',
        'provider' => 'anthropic',
        'input_price_per_mtok' => 1,
        'output_price_per_mtok' => 1,
    ]);

    $conversation = $user->conversations()->create(['title' => 'Test']);

    $userMessage = Message::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'Question',
    ]);

    $originalAnswer = Message::create([
        'conversation_id' => $conversation->id,
        'parent_message_id' => $userMessage->id,
        'model_id' => $model->id,
        'role' => 'assistant',
        'content' => 'Ancienne réponse',
    ]);

    $conversation->update(['active_leaf_message_id' => $originalAnswer->id]);

    $response = $this->actingAs($user)->post(
        route('conversations.messages.regenerate', [$conversation, $originalAnswer]),
        ['model_id' => $model->id]
    );

    $response->assertOk();
    // response()->stream() n'exécute son callback (l'appel LLM + la
    // sauvegarde en base) que lorsque le contenu est réellement lu —
    // pas juste en vérifiant le status HTTP.
    $response->streamedContent();

    // L'ancienne réponse doit TOUJOURS exister en base, inchangée.
    expect(Message::find($originalAnswer->id)->content)->toBe('Ancienne réponse');

    // Une nouvelle réponse a été créée, frère de l'ancienne (même parent).
    $newAnswer = Message::where('id', '!=', $originalAnswer->id)
        ->where('parent_message_id', $userMessage->id)
        ->first();

    expect($newAnswer)->not->toBeNull();
    expect($newAnswer->content)->toBe('Nouvelle réponse');
    expect($newAnswer->input_tokens)->toBe(10);
    expect($newAnswer->output_tokens)->toBe(5);

    // La conversation pointe maintenant sur la nouvelle branche.
    expect($conversation->fresh()->active_leaf_message_id)->toBe($newAnswer->id);
});

test('a user cannot regenerate a message in someone elses conversation', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $model = AiModel::create([
        'slug' => 'test/model',
        'display_name' => 'Test Model',
        'provider' => 'anthropic',
        'input_price_per_mtok' => 1,
        'output_price_per_mtok' => 1,
    ]);

    $conversation = $owner->conversations()->create(['title' => 'Privé']);
    $message = Message::create([
        'conversation_id' => $conversation->id,
        'model_id' => $model->id,
        'role' => 'assistant',
        'content' => 'Réponse',
    ]);

    $response = $this->actingAs($intruder)->post(
        route('conversations.messages.regenerate', [$conversation, $message]),
        ['model_id' => $model->id]
    );

    $response->assertForbidden();
});
