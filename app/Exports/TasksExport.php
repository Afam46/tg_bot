<?php

namespace App\Exports;

use App\Models\UserTask;
use Maatwebsite\Excel\Concerns\FromCollection;

class TasksExport implements FromCollection
{
    protected $userId;

    public function __construct($userId)
    {
        $this->userId = $userId;
    }

    public function collection()
    {
        return UserTask::where('telegram_user_id', $this->userId)
            ->select('task_text', 'status', 'created_at')
            ->get();
    }
}