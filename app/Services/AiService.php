<?php

namespace App\Services;

use App\Jobs\ProcessAiMessageJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    public function process(int $chatId, string $query): void
    {
        ProcessAiMessageJob::dispatch($chatId, $query);
    }

    public function ask(string $query): string
    {
        try {
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . env('AI_API_KEY'),
            ])->post('https://api.deepseek.com/chat/completions', [
                'model' => 'deepseek-chat',
                'messages' => [
                    ['role' => 'system', 'content' => 'Ты полезный Telegram ассистент. Отвечай кратко.'],
                    ['role' => 'user', 'content' => $query]
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['choices'][0]['message']['content'] ?? 'Ошибка AI';
            }
        } catch (\Exception $e) {
            Log::warning('DeepSeek failed: ' . $e->getMessage());
        }

        try {
            $response = Http::timeout(30)->get('https://api.popcat.xyz/chat', [
                'msg' => $query
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['response'] ?? 'Не удалось получить ответ';
            }
        } catch (\Exception $e) {
            Log::warning('Popcat failed: ' . $e->getMessage());
        }

        return "🤖 Извини, AI-сервис временно недоступен. Попробуй позже или задай вопрос иначе.";
    }
}