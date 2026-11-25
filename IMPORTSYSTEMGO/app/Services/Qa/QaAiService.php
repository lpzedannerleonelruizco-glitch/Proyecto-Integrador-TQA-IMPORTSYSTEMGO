<?php

namespace App\Services\Qa;

use App\Models\QaAiSession;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class QaAiService
{
    public function analyze(User $owner, string $prompt): QaAiSession
    {
        $prompt = trim($prompt);
        $session = QaAiSession::query()->create([
            'public_id' => (string) Str::uuid(),
            'owner_id' => $owner->id,
            'provider' => config('qa.ai.provider', 'none'),
            'model' => config('qa.ai.model'),
            'prompt' => $prompt,
            'status' => 'PENDING',
        ]);

        $provider = config('qa.ai.provider', 'none');
        try {
            if ($provider === 'ollama') {
                $response = Http::timeout(120)->retry(2, 1000)->post(rtrim((string) config('qa.ai.ollama_url'), '/').'/api/generate', [
                    'model' => config('qa.ai.model'),
                    'prompt' => "Actúa como analista QA. Responde en español. Analiza el requerimiento o error y propone escenarios Gherkin, positivos, negativos, bordes y regresión sin afirmar que las pruebas pasaron.\n\n".$prompt,
                    'stream' => false,
                ]);
                if (! $response->successful()) {
                    throw new \RuntimeException('ollama_unavailable');
                }
                $content = (string) $response->json('response');
            } elseif ($provider === 'openai_compatible') {
                $url = rtrim((string) config('qa.ai.api_url'), '/').'/chat/completions';
                $response = Http::withToken((string) config('qa.ai.api_key'))
                    ->timeout(120)->retry(2, 1000)->post($url, [
                        'model' => config('qa.ai.model'),
                        'messages' => [
                            ['role' => 'system', 'content' => 'Analista QA senior. No afirmes resultados sin pruebas reales. Responde en español.'],
                            ['role' => 'user', 'content' => $prompt],
                        ],
                        'temperature' => 0.2,
                    ]);
                if (! $response->successful()) {
                    throw new \RuntimeException('ai_api_unavailable');
                }
                $content = (string) $response->json('choices.0.message.content');
            } else {
                throw new \RuntimeException('ai_not_configured');
            }

            $session->forceFill([
                'response' => $content,
                'status' => 'COMPLETED',
                'error_message' => null,
            ])->save();
        } catch (\Throwable $exception) {
            $session->forceFill([
                'status' => 'FAILED',
                'error_message' => $exception->getMessage(),
            ])->save();
        }

        return $session;
    }
}
