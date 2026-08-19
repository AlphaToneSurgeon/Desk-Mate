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

    /**
     * Comme sendMessage(), mais en streaming : appelle $onChunk(string $delta)
     * à chaque petit bout de texte reçu d'OpenRouter, au fur et à mesure.
     *
     * @return array{content: string, usage: ?array} Le texte complet accumulé + l'usage (tokens), une fois le flux terminé
     */
    public function streamMessage(string $model, array $messages, callable $onChunk, int $maxTokens = 1024): array
    {
        $apiKey = config('services.openrouter.key');
        $baseUrl = config('services.openrouter.base_url');

        // withOptions(['stream' => true]) : on dit à Guzzle (le client HTTP
        // sous le capot de Laravel) de NE PAS télécharger toute la réponse
        // d'un coup, mais de nous laisser un "tuyau" ouvert qu'on lit nous-même.
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])->withOptions(['stream' => true])
            ->post("{$baseUrl}/chat/completions", [
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => $maxTokens,
                'stream' => true,
                // Demande explicitement à OpenRouter de nous envoyer les
                // tokens consommés dans un dernier événement du flux.
                'stream_options' => ['include_usage' => true],
            ])->throw();

        $body = $response->toPsrResponse()->getBody();

        $fullContent = '';
        $usage = null;
        $buffer = ''; // ce qu'on a lu du réseau mais pas encore traité

        // Le flux SSE ressemble à :
        //   data: {"choices":[{"delta":{"content":"Bonj"}}]}\n\n
        //   data: {"choices":[{"delta":{"content":"our"}}]}\n\n
        //   data: [DONE]\n\n
        // Chaque "événement" est séparé du suivant par une ligne vide (\n\n).
        // Comme le réseau peut nous livrer les octets en morceaux qui ne
        // correspondent PAS forcément à un événement complet, on accumule
        // dans $buffer et on ne traite que ce qui forme un événement entier.
        while (! $body->eof()) {
            $buffer .= $body->read(1024);

            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $event = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                if (! str_starts_with($event, 'data: ')) {
                    continue;
                }

                $data = trim(substr($event, strlen('data: ')));

                if ($data === '[DONE]') {
                    continue;
                }

                $json = json_decode($data, true);

                if (isset($json['choices'][0]['delta']['content'])) {
                    $delta = $json['choices'][0]['delta']['content'];
                    $fullContent .= $delta;
                    $onChunk($delta);
                }

                if (isset($json['usage'])) {
                    $usage = $json['usage'];
                }
            }
        }

        return ['content' => $fullContent, 'usage' => $usage];
    }
}
