<?php

namespace App\Services;

use App\Models\UserTask;
use Illuminate\Support\Facades\Cache;

class TaskService
{
    public function createTask(int $userId, string $text)
    {
        $task = UserTask::create([
            'telegram_user_id' => $userId,
            'task_text' => $text,
            'status' => false
        ]);

        Cache::forget('active_tasks_' . $userId);
        Cache::forget('task_stats_'.$task->telegram_user_id);

        return $task;
    }

    public function completeTask(object $task)
    {
        $task->status = true;
        $task->save();

        Cache::forget('active_tasks_'.$task->telegram_user_id);
        Cache::forget('task_stats_'.$task->telegram_user_id);
    }

    public function getStats(object $user)
    {   
        return Cache::remember('task_stats_'.$user->id, 300, function() use($user)
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
        });
    }
    
    public function findTask(int $taskId)
    {
        return UserTask::find($taskId);
    }

    public function getActiveTasks(int $userId)
    {   
        return Cache::remember('active_tasks_'.$userId, 300, function() use($userId)
        {
            return UserTask::where('telegram_user_id',$userId)->where('status',false)->get();
        });
    }

    public function getActiveTasksPaginated(int $userId, int $page = 1): array
    {
        $perPage = 5;

        $query = UserTask::where('telegram_user_id', $userId)->where('status', false);

        $total = $query->count();

        $tasks = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();

        return [
            'items' => $tasks,
            'total' => $total,
        ];
    }
}