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
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('AI_API_KEY'),
            'Content-Type' => 'application/json',
        ])->timeout(20)->retry(3, 2000)->post(
            'https://logfare.ai/v1/chat/completions',
            [
                'model' => 'gemma-4-31b-it',

                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Ты полезный Telegram ассистент.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $query
                    ]
                ],
            ]
        );

        if ($response->failed()) {
            throw new \Exception('AI API unavailable');
        }

        $data = $response->json();

        return $data['choices'][0]['message']['content']
            ?? 'Ошибка AI';
    }
}