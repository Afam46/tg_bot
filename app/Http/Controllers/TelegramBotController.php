<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Telegram\Bot\Api;
use App\Models\UserTask;
use App\Models\TelegramUser;

class TelegramBotController extends Controller
{
    private function getMainKeyboard()
    {
        return json_encode([
            'keyboard' => [
                ['Мой профиль', 'Создать задачу'],
                ['Мои задачи']
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ]);
    }

    private function getStartKeyboard()
    {
        return json_encode([
            'keyboard' => [['/start']],
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ]);
    }

    public function webhook(Request $request)
    {
        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        $update = $telegram->getWebhookUpdate();
        $message = $update->getMessage();

        if (!$message || !$message->getText()) {
            return response()->json(['status' => 'ok']);
        }

        $chatId = $message->getChat()->getId();
        $text = $message->getText();

        if ($text === '/start') {
            return $this->handleStart($telegram, $message, $chatId);
        }

        $user = TelegramUser::where('chat_id', $chatId)->first();
        if (!$user) {
            return $this->handleUnregistered($telegram, $chatId);
        }

        if ($user->state === 'waiting_task') {
            return $this->handleWaitingTask($telegram, $chatId, $user, $text);
        }

        $handlers = [
            'Мой профиль' => 'handleProfile',
            'Создать задачу' => 'handleCreateTask',
            'Мои задачи' => 'handleMyTasks',
        ];

        if (isset($handlers[$text])) {
            $method = $handlers[$text];
            return $this->$method($telegram, $chatId, $user);
        }

        if (preg_match('/^\/done (\d+)$/', $text, $matches)) {
            return $this->handleDone($telegram, $chatId, $user, (int)$matches[1]);
        }

        return $this->handleUnknown($telegram, $chatId);
    }

    private function handleStart($telegram, $message, $chatId)
    {
        $firstName = $message->getChat()->getFirstName() ?? 'User';
        $lastName = $message->getChat()->getLastName() ?? null;
        $username = $message->getChat()->getUsername();

        $user = TelegramUser::updateOrCreate(
            ['chat_id' => $chatId],
            [
                'username' => $username,
                'first_name' => $firstName,
                'last_name' => $lastName
            ]
        );

        $text = $user->wasRecentlyCreated 
            ? "Чел, теперь ты сохранен в моей БД" 
            : "Ты и так зареган";

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => $this->getMainKeyboard()
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function handleUnregistered($telegram, $chatId)
    {
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => 'Привет! Напиши /start, чтобы зарегистрироваться.',
            'reply_markup' => $this->getStartKeyboard()
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function handleWaitingTask($telegram, $chatId, $user, $text)
    {
        UserTask::create([
            'telegram_user_id' => $user->id,
            'task_text' => $text,
            'status' => false
        ]);

        $user->state = null;
        $user->save();

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "✅ Задача добавлена!",
            'reply_markup' => $this->getMainKeyboard()
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function handleProfile($telegram, $chatId, $user)
    {
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "Username: {$user->username}\nFirstName: {$user->first_name}\nLastName: {$user->last_name}\nЗадач: " . $user->tasks()->count()
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function handleCreateTask($telegram, $chatId, $user)
    {
        $user->state = 'waiting_task';
        $user->save();

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => 'Опиши задачу:',
            'reply_markup' => json_encode(['remove_keyboard' => true])
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function handleMyTasks($telegram, $chatId, $user)
    {
        $tasks = UserTask::where('telegram_user_id', $user->id)
                        ->where('status', false)
                        ->get();
        
        $count = $tasks->count();

        if ($count == 0) {
            $response = "🎉 У тебя нет активных задач!";
        } else {
            $response = "📋 Твои задачи ($count):\n";
            foreach ($tasks as $index => $task) {
                $response .= ($index + 1) . ". {$task->task_text}\n";
            }
            $response .= "\nЧтобы отметить выполненную: /done [номер]";
        }

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $response
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function handleDone($telegram, $chatId, $user, $taskNumber)
    {
        $tasks = UserTask::where('telegram_user_id', $user->id)
                        ->where('status', false)
                        ->get();

        $index = $taskNumber - 1;

        if (!isset($tasks[$index])) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "❌ Задача с номером {$taskNumber} не найдена."
            ]);
            return response()->json(['status' => 'ok']);
        }

        $task = $tasks[$index];
        $task->status = true;
        $task->save();

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "✅ Задача «{$task->task_text}» выполнена!"
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function handleUnknown($telegram, $chatId)
    {
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => 'Используй кнопки!',
            'reply_markup' => $this->getMainKeyboard()
        ]);

        return response()->json(['status' => 'ok']);
    }
}