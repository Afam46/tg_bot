<?php

namespace App\Services;

use App\Models\UserTask;

class TaskService
{
    public function createTask(int $userId, string $text): UserTask
    {
        return UserTask::create([
            'telegram_user_id' => $userId,
            'task_text' => $text,
            'status' => false
        ]);
    }

    public function completeTask(int $taskId): void
    {
        $task = UserTask::findOrFail($taskId);

        $task->status = true;

        $task->save();
    }

    public function getActiveTasks(int $userId)
    {
        return UserTask::where('telegram_user_id',$userId)->where('status',false)->get();
    }
}