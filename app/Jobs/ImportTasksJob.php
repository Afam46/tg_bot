<?php

namespace App\Jobs;

use Telegram\Bot\Api;
use App\Imports\TasksImport;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class ImportTasksJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public function __construct(
        public int $userId,
        public int $chatId,
        public string $filePath
    ) {}

    public function handle(): void
    {
        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));

        Excel::import(
            new TasksImport($this->userId),
            $this->filePath
        );

        if (file_exists($this->filePath)) {
            unlink($this->filePath);
        }

        $telegram->sendMessage([
            'chat_id' => $this->chatId,
            'text' => '✅ Задачи успешно импортированы!'
        ]);
    }
}