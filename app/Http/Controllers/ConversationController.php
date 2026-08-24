<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Models\Conversation;
use App\Models\Tag;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Conversations/Index', [
            'conversations' => $request->user()
                ->conversations()
                ->with('tags:id,name,color') // évite le N+1 : charge tous les tags en 1 requête plutôt qu'une par conversation
                ->latest('updated_at')
                ->get(['id', 'title', 'updated_at']),
            'availableTags' => $request->user()->tags()->get(['id', 'name', 'color']),
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

    public function attachTag(Request $request, Conversation $conversation, Tag $tag)
    {
        abort_if($conversation->user_id !== $request->user()->id, 403);
        abort_if($tag->user_id !== $request->user()->id, 403);

        // syncWithoutDetaching : ajoute ce tag SANS retirer les autres déjà
        // attachés (contrairement à sync(), qui remplacerait toute la liste).
        $conversation->tags()->syncWithoutDetaching([$tag->id]);

        return back();
    }

    public function detachTag(Request $request, Conversation $conversation, Tag $tag)
    {
        abort_if($conversation->user_id !== $request->user()->id, 403);

        $conversation->tags()->detach($tag->id);

        return back();
    }
}
