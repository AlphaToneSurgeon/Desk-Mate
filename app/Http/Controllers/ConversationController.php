<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        // On ne renvoie QUE les conversations de l'utilisateur connecté.
        // Ne jamais faire Conversation::all() ici : n'importe quel utilisateur
        // verrait les conversations de tout le monde.
        return $request->user()
            ->conversations()
            ->latest('updated_at')
            ->get(['id', 'title', 'updated_at']);
    }

    public function store(Request $request)
    {
        $conversation = $request->user()->conversations()->create([
            'title' => null, // généré au premier message, cf. jour 3
        ]);
        return $conversation;
    }

    public function show(Request $request, Conversation $conversation)
    {
        // Vérification d'autorisation : cette conversation appartient-elle
        // bien à l'utilisateur connecté ? Sans ce contrôle, n'importe quel
        // utilisateur connecté pourrait lire /conversations/{id} avec
        // l'id de conversation de quelqu'un d'autre.
        abort_if($conversation->user_id !== $request->user()->id, 403);

        return [
            'conversation' => $conversation,
            'thread' => $conversation->activeThread(),
        ];
    }
}
