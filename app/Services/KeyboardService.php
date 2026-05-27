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

    public function getTasksKeyboard(int $userId, int $page = 1)
    {
        $perPage = 5;

        $query = UserTask::where('telegram_user_id', $userId)
            ->where('status', false);

        $totalTasks = $query->count();

        $totalPages = max(1, ceil($totalTasks / $perPage));

        $tasks = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();

        if ($tasks->isEmpty()) {
            return null;
        }

        $keyboard = [];

        foreach ($tasks as $task) {

            $keyboard[] = [
                [
                    'text' => '✅ ' .
                        mb_substr($task->task_text, 0, 35) .
                        (mb_strlen($task->task_text) > 35 ? '…' : ''),

                    'callback_data' => 'done_' . $task->id
                ]
            ];
        }

        $navigation = [];

        if ($page > 1) {
            $navigation[] = [
                'text' => '⬅️',
                'callback_data' => 'tasks_page_' . ($page - 1)
            ];
        }

        $navigation[] = [
            'text' => "{$page}/{$totalPages}",
            'callback_data' => 'ignore'
        ];

        if ($page < $totalPages) {
            $navigation[] = [
                'text' => '➡️',
                'callback_data' => 'tasks_page_' . ($page + 1)
            ];
        }

        $keyboard[] = $navigation;

        return json_encode([
            'inline_keyboard' => $keyboard
        ]);
    }
}
