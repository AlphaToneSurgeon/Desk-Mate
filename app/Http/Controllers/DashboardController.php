<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        // Une requête d'agrégation SQL : on ne récupère pas tous les messages
        // pour les additionner en PHP (ça chargerait potentiellement des
        // milliers de lignes juste pour faire un SUM). On demande à MySQL de
        // faire les totaux lui-même, groupés par modèle utilisé.
        //
        // whereHas('conversation', ...) : ne compte que les messages des
        // conversations qui appartiennent à l'utilisateur connecté — sans ça,
        // n'importe qui verrait les statistiques de tout le monde.
        $byModel = Message::query()
            ->whereNotNull('model_id')
            ->whereHas('conversation', fn ($q) => $q->where('user_id', $userId))
            ->selectRaw('model_id, COUNT(*) as message_count, SUM(input_tokens) as input_tokens, SUM(output_tokens) as output_tokens')
            ->groupBy('model_id')
            ->with('model:id,display_name,input_price_per_mtok,output_price_per_mtok')
            ->get()
            ->map(function ($row) {
                // Prix par MILLION de tokens en base -> on divise donc par 1_000_000.
                $cost = ($row->input_tokens / 1_000_000) * $row->model->input_price_per_mtok
                    + ($row->output_tokens / 1_000_000) * $row->model->output_price_per_mtok;

                return [
                    'model' => $row->model->display_name,
                    'message_count' => (int) $row->message_count,
                    'input_tokens' => (int) $row->input_tokens,
                    'output_tokens' => (int) $row->output_tokens,
                    'cost' => round($cost, 4),
                ];
            });

        return Inertia::render('Dashboard', [
            'stats' => [
                'total_conversations' => $request->user()->conversations()->count(),
                'total_messages' => $request->user()->conversations()->withCount('messages')->get()->sum('messages_count'),
                'total_cost' => round($byModel->sum('cost'), 4),
                'by_model' => $byModel->values(),
            ],
        ]);
    }
}
