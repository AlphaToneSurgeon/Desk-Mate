<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\OpenRouterClient;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function store(Request $request, Conversation $conversation)
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

        $model = AiModel::findOrFail($request->input('model_id'));

        $messages = collect($conversation->activeThread())
            ->map(fn ($msg) => ['role' => $msg->role, 'content' => $msg->content])
            ->toArray();

        $systemPrompt = $conversation->custom_instructions ?? $conversation->user->custom_instructions;

        if ($systemPrompt) {
            array_unshift($messages, [
                'role' => 'system',
                'content' => $systemPrompt,
            ]);
        }

        $response = (new OpenRouterClient())->sendMessage($model->slug, $messages);

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
}
