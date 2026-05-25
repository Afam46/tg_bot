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

    public function completeTask($task)
    {
        $task->status = true;
        $task->save();
    }

    public function getStats($user)
    {
        $allTasksCount = $user->tasks()->count();
        $completedTasksCount = $user->tasks()->where('status', true)->count();
        $uncompletedTasksCount = $allTasksCount - $completedTasksCount;

        $stats = [
            'all' => $allTasksCount,
            'completed' => $completedTasksCount,
            'uncompleted' => $uncompletedTasksCount
        ];

        return $stats;
    }

    public function findTask($taskId)
    {
        return UserTask::find($taskId);
    }

    public function getActiveTasks(int $userId)
    {
        return UserTask::where('telegram_user_id',$userId)->where('status',false)->get();
    }
}