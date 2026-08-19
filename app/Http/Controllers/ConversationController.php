<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Conversations/Index', [
            'conversations' => $request->user()
                ->conversations()
                ->latest('updated_at')
                ->get(['id', 'title', 'updated_at']),
        ]);
    }

    public function store(Request $request)
    {
        $conversation = $request->user()->conversations()->create([
            'title' => null,
        ]);

        return to_route('conversations.show', $conversation);
    }

    public function show(Request $request, Conversation $conversation)
    {
        abort_if($conversation->user_id !== $request->user()->id, 403);

        return Inertia::render('Conversations/Show', [
            'conversation' => $conversation,
            'thread' => $conversation->activeThread(),
            'models' => AiModel::where('is_active', true)->get(['id', 'slug', 'display_name', 'provider']),
        ]);
    }
}
