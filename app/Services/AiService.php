<?php

namespace App\Services;

use App\Jobs\ProcessAiMessageJob;
use Illuminate\Support\Facades\Http;

class AiService
{
    public function process(int $chatId, string $query): void
    {
        ProcessAiMessageJob::dispatch($chatId, $query);
    }

    public function ask(string $query): string
    {
        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            try {
                $response = Http::timeout(30)->withHeaders([
                    'Authorization' => 'Bearer ' . env('AI_API_KEY'),
                ])->post('https://api.deepseek.com/chat/completions', [
                    'model' => 'deepseek-chat',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Ты полезный Telegram ассистент.'],
                        ['role' => 'user', 'content' => $query]
                    ]
                ]);

                if ($response->successful()) {
                    return $response->json()['choices'][0]['message']['content'] ?? 'Ошибка AI';
                }

                if ($response->status() === 503) {
                    $attempt++;
                    sleep(2);
                    continue;
                }

                throw new \Exception('HTTP ' . $response->status());

            } catch (\Exception $e) {
                $attempt++;
                if ($attempt >= $maxAttempts) {
                    throw new \Exception('AI API unavailable: ' . $e->getMessage());
                }
                sleep(2);
            }
        }

        throw new \Exception('AI API unavailable after retries');
    }
}