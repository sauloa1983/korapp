<?php

namespace App\Services\Serna\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SernaAiClient
{
    public function enabled(): bool
    {
        return (bool) config('serna_ai.enabled')
            && filled(config('serna_ai.api_key'));
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return array<string, mixed>
     */
    public function chatJson(array $messages, float $temperature = 0.2): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('La IA no está configurada. Define OPENAI_API_KEY en el .env.');
        }

        try {
            $response = Http::baseUrl((string) config('serna_ai.base_url'))
                ->withToken((string) config('serna_ai.api_key'))
                ->timeout((int) config('serna_ai.timeout', 45))
                ->acceptJson()
                ->post('/chat/completions', [
                    'model' => config('serna_ai.model'),
                    'temperature' => $temperature,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => $messages,
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('No se pudo conectar con el proveedor de IA: '.$exception->getMessage(), 0, $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Error del proveedor de IA (HTTP '.$response->status().'): '.$response->body());
        }

        $content = data_get($response->json(), 'choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('La IA no devolvió contenido útil.');
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('La IA devolvió JSON inválido.');
        }

        return $decoded;
    }
}
