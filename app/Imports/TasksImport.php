<?php

namespace App\Imports;

use App\Models\UserTask;
use Maatwebsite\Excel\Concerns\ToModel;

class TasksImport implements ToModel
{
    protected $userId;

    public function __construct($userId)
    {
        $this->userId = $userId;
    }

    public function model(array $row)
    {
        return new UserTask([
            'telegram_user_id' => $this->userId,
            'task_text' => $row[0],
            'status' => false
        ]);
    }
}