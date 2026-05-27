<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Telegram\Bot\Api;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TasksExport;
use App\Services\KeyboardService;
use App\Services\TelegramService;

class ExportTasksJob implements ShouldQueue
{
    use Queueable;

     public function __construct(
        public int $userId,
        public int $chatId,
    ) {}

    public function handle( TelegramService $telegramService, KeyboardService $keyboardService): void
    {
        $fileName = 'tasks_' . $this->userId . '.xlsx';

        Excel::store(new TasksExport($this->userId), $fileName, 'public');

        $filePath = storage_path('app/public/' . $fileName);

        $keyboardService = new KeyboardService;

        $telegramService->sendDocument($this->chatId, $filePath, '📥 Ваш экспорт задач',
            $keyboardService->getMainKeyboard());

        unlink($filePath);
    }
}
