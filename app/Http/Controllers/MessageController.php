<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\OpenRouterClient;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageController extends Controller
{
    public function store(Request $request, Conversation $conversation)
    {
        [$userMessage, $model, $apiMessages] = $this->prepare($request, $conversation);

        $response = (new OpenRouterClient())->sendMessage($model->slug, $apiMessages);

        $assistantMessage = Message::create([
            'conversation_id' => $conversation->id,
            'parent_message_id' => $userMessage->id,
            'model_id' => $model->id,
            'role' => 'assistant',
            'content' => $response['choices'][0]['message']['content'],
            'input_tokens' => $response['usage']['prompt_tokens'],
            'output_tokens' => $response['usage']['completion_tokens'],
        ]);

        $conversation->update(['active_leaf_message_id' => $assistantMessage->id]);

        return ['thread' => $conversation->activeThread()];
    }

    public function stream(Request $request, Conversation $conversation): StreamedResponse
    {
        [$userMessage, $model, $apiMessages] = $this->prepare($request, $conversation);

        return response()->stream(function () use ($model, $apiMessages, $conversation, $userMessage) {
            $result = (new OpenRouterClient())->streamMessage($model->slug, $apiMessages, function (string $delta) {
                // Un événement SSE pour CE morceau de texte, envoyé tout de
                // suite au navigateur (pas de buffering côté Laravel).
                echo 'data: '.json_encode(['content' => $delta])."\n\n";
                ob_flush();
                flush();
            });

            // Une fois le flux terminé, on enregistre le message complet
            // en base — on ne pouvait pas le faire avant, on ne connaît le
            // texte entier qu'à la fin du streaming.
            $assistantMessage = Message::create([
                'conversation_id' => $conversation->id,
                'parent_message_id' => $userMessage->id,
                'model_id' => $model->id,
                'role' => 'assistant',
                'content' => $result['content'],
                'input_tokens' => $result['usage']['prompt_tokens'] ?? null,
                'output_tokens' => $result['usage']['completion_tokens'] ?? null,
            ]);

            $conversation->update(['active_leaf_message_id' => $assistantMessage->id]);

            // Dernier événement : on annonce au front que c'est fini, avec
            // l'id du message pour qu'il puisse rafraîchir proprement si besoin.
            echo 'data: '.json_encode(['done' => true, 'message_id' => $assistantMessage->id])."\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            // Empêche un proxy nginx de mettre en mémoire tampon toute la
            // réponse avant de l'envoyer (casserait le streaming en prod).
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * Vérifie les droits, valide l'entrée, crée le message utilisateur et
     * construit le tableau à envoyer à l'API. Commun à store() et stream().
     *
     * @return array{0: Message, 1: AiModel, 2: array}
     */
    private function prepare(Request $request, Conversation $conversation): array
    {
        abort_if($conversation->user_id !== $request->user()->id, 403);

        $request->validate([
            'content' => 'required|string',
            'model_id' => 'required|exists:models,id',
        ]);

        $userMessage = Message::create([
            'conversation_id' => $conversation->id,
            'parent_message_id' => $conversation->active_leaf_message_id,
            'role' => 'user',
            'content' => $request->input('content'),
        ]);

        $conversation->update(['active_leaf_message_id' => $userMessage->id]);

        // Titre généré au premier message (fonctionnalité obligatoire) :
        // simple troncature pour l'instant, pas besoin d'un appel LLM en plus.
        if ($conversation->title === null) {
            $conversation->update([
                'title' => \Illuminate\Support\Str::limit($request->input('content'), 50),
            ]);
        }

        $model = AiModel::findOrFail($request->input('model_id'));

        $apiMessages = collect($conversation->activeThread())
            ->map(fn ($msg) => ['role' => $msg->role, 'content' => $msg->content])
            ->toArray();

        $systemPrompt = $conversation->custom_instructions
            ?? $conversation->user->custom_instructions
            ?? config('assistant.default_system_prompt');

        if ($systemPrompt) {
            array_unshift($apiMessages, [
                'role' => 'system',
                'content' => $systemPrompt,
            ]);
        }

        return [$userMessage, $model, $apiMessages];
    }
}
