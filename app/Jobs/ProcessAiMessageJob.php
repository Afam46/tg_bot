<?php

namespace App\Jobs;

use App\Services\AiService;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessAiMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $chatId,
        public string $text
    ){}

    public function handle(AiService $aiService, TelegramService $telegramService): void
    {
        try {

            $answer = $aiService->ask($this->text);

            $answer = mb_substr($answer, 0, 4000);

            $telegramService->sendMessage(
                $this->chatId,
                $answer
            );

        } catch (\Exception $e) {
            Log::error('AI ERROR: ' . $e->getMessage());

            $telegramService->sendMessage(
                $this->chatId,
                '❌ Ошибка AI сервиса: ' . $e->getMessage()
            );
        }
    }
}