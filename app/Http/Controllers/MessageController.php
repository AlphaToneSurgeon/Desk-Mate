<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\OpenRouterClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

        return $this->streamAssistantReply($conversation, $userMessage->id, $model, $apiMessages);
    }

    /**
     * Régénère la réponse assistant $message : crée un NOUVEAU message avec
     * le même parent que $message (donc un frère), sans toucher à l'ancien.
     * C'est le mécanisme de fork appliqué à "je n'aime pas cette réponse".
     */
    public function regenerate(Request $request, Conversation $conversation, Message $message): StreamedResponse
    {
        abort_if($conversation->user_id !== $request->user()->id, 403);
        abort_unless($message->conversation_id === $conversation->id && $message->role === 'assistant', 404);

        $model = $request->filled('model_id')
            ? AiModel::findOrFail($request->input('model_id'))
            : $message->model;

        $apiMessages = $this->buildApiMessages($conversation, $message->parent_message_id);

        return $this->streamAssistantReply($conversation, $message->parent_message_id, $model, $apiMessages);
    }

    /**
     * "Modifie" le message utilisateur $message : crée un NOUVEAU message
     * avec le même parent (un frère de $message), puis relance une réponse
     * à partir de là. L'ancienne branche (avec l'ancienne question et sa
     * réponse) reste intacte en base, juste plus affichée par défaut.
     */
    public function updateMessage(Request $request, Conversation $conversation, Message $message): StreamedResponse
    {
        abort_if($conversation->user_id !== $request->user()->id, 403);
        abort_unless($message->conversation_id === $conversation->id && $message->role === 'user', 404);

        $request->validate([
            'content' => 'required|string',
            'model_id' => 'required|exists:models,id',
        ]);

        $newUserMessage = Message::create([
            'conversation_id' => $conversation->id,
            'parent_message_id' => $message->parent_message_id,
            'role' => 'user',
            'content' => $request->input('content'),
        ]);

        $model = AiModel::findOrFail($request->input('model_id'));
        $apiMessages = $this->buildApiMessages($conversation, $newUserMessage->id);

        return $this->streamAssistantReply($conversation, $newUserMessage->id, $model, $apiMessages);
    }

    /**
     * Cœur partagé par stream()/regenerate()/updateMessage() : appelle le
     * LLM en streaming, relaie chaque fragment au navigateur, puis enregistre
     * le message assistant complet et met à jour la feuille active.
     */
    private function streamAssistantReply(Conversation $conversation, ?int $parentMessageId, AiModel $model, array $apiMessages): StreamedResponse
    {
        return response()->stream(function () use ($model, $apiMessages, $conversation, $parentMessageId) {
            $result = (new OpenRouterClient())->streamMessage($model->slug, $apiMessages, function (string $delta) {
                echo 'data: '.json_encode(['content' => $delta])."\n\n";
                ob_flush();
                flush();
            });

            $assistantMessage = Message::create([
                'conversation_id' => $conversation->id,
                'parent_message_id' => $parentMessageId,
                'model_id' => $model->id,
                'role' => 'assistant',
                'content' => $result['content'],
                'input_tokens' => $result['usage']['prompt_tokens'] ?? null,
                'output_tokens' => $result['usage']['completion_tokens'] ?? null,
            ]);

            $conversation->update(['active_leaf_message_id' => $assistantMessage->id]);

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
     * Construit le tableau à envoyer à l'API à partir du fil se terminant à
     * $leafMessageId, avec l'instruction système en tête si elle existe.
     */
    private function buildApiMessages(Conversation $conversation, ?int $leafMessageId): array
    {
        $apiMessages = collect($conversation->threadEndingAt($leafMessageId))
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

        return $apiMessages;
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
                'title' => Str::limit($request->input('content'), 50),
            ]);
        }

        $model = AiModel::findOrFail($request->input('model_id'));
        $apiMessages = $this->buildApiMessages($conversation, $userMessage->id);

        return [$userMessage, $model, $apiMessages];
    }
}
