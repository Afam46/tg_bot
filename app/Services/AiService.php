<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AiService
{
    public function ask(string $query): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('AI_API_KEY'),
            'Content-Type' => 'application/json',
        ])->timeout(60)->post(
            'https://logfare.ai/v1/chat/completions',
            [
                'model' => 'deepseek-v4-flash',

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

        $data = $response->json();

        return $data['choices'][0]['message']['content'] ?? 'Ошибка AI';
    }
}