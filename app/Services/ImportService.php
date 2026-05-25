<?php

namespace App\Services;
use App\Jobs\ImportTasksJob;

class ImportService
{
    public function importFile($telegram, $message, $user, $chatId)
    {
        $document = $message->getDocument();

        $fileId = $document->getFileId();

        $file = $telegram->getFile([
            'file_id' => $fileId
        ]);

        $telegramFilePath = $file->getFilePath();

        $url = "https://api.telegram.org/file/bot" . env('TELEGRAM_BOT_TOKEN') . "/" . $telegramFilePath;

        $localPath = storage_path(
            'app/imports/tasks_' . time() . '.xlsx'
        );

        file_put_contents(
            $localPath,
            file_get_contents($url)
        );

        ImportTasksJob::dispatch($user->id, $chatId, $localPath);
    }
}
