<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OpenRouterClient
{
    /**
     * Envoie une conversation à OpenRouter (format compatible OpenAI) et
     * renvoie la réponse décodée.
     *
     * @param  string  $model    Identifiant OpenRouter (ex: "anthropic/claude-sonnet-4.5")
     * @param  array   $messages Tableau de ["role" => "system"|"user"|"assistant", "content" => "..."]
     * @param  int     $maxTokens
     * @return array   Le tableau JSON décodé renvoyé par l'API
     */
    public function sendMessage(string $model, array $messages, int $maxTokens = 1024): array
    {
        $apiKey = config('services.openrouter.key');
        $baseUrl = config('services.openrouter.base_url');

        $body = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
        ];

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])->post("{$baseUrl}/chat/completions", $body)
            ->throw()
            ->json();

        return $response;
    }
}
