<?php

namespace App\Services;

use App\Jobs\ExportTasksJob;

class ExportService
{
    public function exportFile(object $user, int $chatId)
    {
        ExportTasksJob::dispatch($user->id, $chatId);
    }
}
