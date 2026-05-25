<?php

namespace App\Services;
use App\Models\UserTask;

class KeyboardService
{
    public function getMainKeyboard()
    {
        return json_encode([
            'keyboard' => [
                ['👤 Мой профиль', '➕ Создать задачу'],
                ['📋 Мои задачи', '🌤️ Погода'],
                ['📤 Импорт задач', '📥 Экспорт задач'],
                ['🤖 ИИ-режим'],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ]);
    }

    public function getStartKeyboard()
    {
        return json_encode([
            'keyboard' => [['/start']],
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ]);
    }

    public function getTasksKeyboard($userId)
    {
        $tasks = UserTask::where('telegram_user_id',$userId)->where('status',false)->get();
        
        if ($tasks->isEmpty()) {
            return null;
        }
        
        $keyboard = [];
        foreach ($tasks as $task) {
            $keyboard[] = [
                [
                    'text' => "✅ " . mb_substr($task->task_text, 0, 35) . (mb_strlen($task->task_text) > 35 ? '…' : ''),
                    'callback_data' => "done_" . $task->id
                ]
            ];
        }
        
        return json_encode(['inline_keyboard' => $keyboard]);
    }
}
