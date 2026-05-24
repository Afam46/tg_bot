<?php

namespace App\Jobs;

use App\Imports\TasksImport;
use App\Models\TelegramUser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Telegram\Bot\Api;

class ImportTasksJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $filePath,
        public int $userId,
        public int $chatId
    ) {}

    public function handle(): void
    {
        Excel::import(
            new TasksImport($this->userId),
            storage_path('app/' . $this->filePath)
        );

        Storage::delete($this->filePath);

        $user = TelegramUser::find($this->userId);

        if ($user) {
            $user->state = null;
            $user->save();
        }

        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));

        $telegram->sendMessage([
            'chat_id' => $this->chatId,
            'text' => '✅ Импорт задач завершен!'
        ]);
    }
}