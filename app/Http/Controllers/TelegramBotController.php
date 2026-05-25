<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Telegram\Bot\Api;
use App\Models\UserTask;
use App\Models\TelegramUser;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TasksExport;
use App\Jobs\ImportTasksJob;
use App\Services\WeatherService;
use App\Services\AiService;
use App\Services\TaskService;
use App\Services\TelegramService;

class TelegramBotController extends Controller
{
    protected $weatherService;
    protected $aiService;
    protected $taskService;
    protected $telegramService;

    public function __construct(WeatherService $weatherService, AiService $aiService,
    TaskService $taskService, TelegramService $telegramService)
    {
        $this->weatherService = $weatherService;
        $this->aiService = $aiService;
        $this->taskService = $taskService;
        $this->telegramService = $telegramService;
    }


    private function getMainKeyboard()
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

    private function getStartKeyboard()
    {
        return json_encode([
            'keyboard' => [['/start']],
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ]);
    }

    private function getTasksKeyboard($user)
    {
        $tasks = $this->taskService->getActiveTasks($user->id);
        
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

    public function webhook(Request $request)
    {
        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        $update = $telegram->getWebhookUpdate();

        if ($callbackQuery = $update->getCallbackQuery()) {
            return $this->handleCallback($telegram, $callbackQuery);
        }

        $message = $update->getMessage();

        if (!$message) {
            return response()->json(['status' => 'ok']);
        }

        $chatId = $message->getChat()->getId();
        $text = $message->getText() ?? '';

        if ($text === '/start') {
            return $this->handleStart($telegram, $message, $chatId);
        }

        $user = TelegramUser::where('chat_id', $chatId)->first();

        if ($user && $user->state === 'waiting_import' && $message->getDocument()) {
            return $this->handleImportTasks($telegram, $chatId, $user, $message);
        }

        if (!$user) {
            return $this->handleUnregistered($telegram, $chatId);
        }

        if ($user->state === 'waiting_task') {
            return $this->handleWaitingTask($telegram, $chatId, $user, $text);
        }

        if ($user->state === 'waiting_city') {
            return $this->handleGetWeather($telegram, $chatId, $user, $text);
        }

        if ($user->state === 'waiting_ai') {
            return $this->handleAiQuery($telegram, $chatId, $user, $text);
        }

        $handlers = [
            '👤 Мой профиль' => 'handleProfile',
            '➕ Создать задачу' => 'handleCreateTask',
            '📋 Мои задачи' => 'handleMyTasks',
            '🌤️ Погода' => 'handleWeather',
            '🤖 ИИ-режим' => 'handleAiMode',
            '📥 Экспорт задач' => 'handleExportTasks',
            '📤 Импорт задач' => 'handleImport',
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
            ? "✅ Авторизация прошла успешно" 
            : "✅ Вы авторизованы";

        $this->telegramService->sendMessage($chatId, $text, $this->getMainKeyboard());

        return response()->json(['status' => 'ok']);
    }

    private function handleUnregistered($telegram, $chatId)
    {
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => 'Привет! Напиши /start, чтобы авторизоваться',
            'reply_markup' => $this->getStartKeyboard()
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function handleWaitingTask($telegram, $chatId, $user, $text)
    {
        $tasks = $this->taskService->createTask($user->id, $text);
        
        $user->state = null;
        $user->save();

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "✅ Задача добавлена!\n📋 Текст: {$text}",
            'reply_markup' => $this->getMainKeyboard()
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function handleProfile($telegram, $chatId, $user)
    {   
        $allTasksCount = $user->tasks()->count();
        $completedTasksCount = $user->tasks()->where('status', true)->count();
        $unCompletedTasksCount = $allTasksCount - $completedTasksCount;
        
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "👤 {$user->username} 👤\n\n"
                    . "📛 Имя: {$user->first_name}\n"
                    . "📋 Всего задач: {$allTasksCount}\n"
                    . "✅ Решенных задач: {$completedTasksCount}\n"
                    . "❌ Осталось задач: {$unCompletedTasksCount}"
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function handleCreateTask($telegram, $chatId, $user)
    {
        $user->state = 'waiting_task';
        $user->save();

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => '✏️ Опиши задачу:',
            'reply_markup' => json_encode(['remove_keyboard' => true])
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function handleMyTasks($telegram, $chatId, $user)
    {
        $tasks = $this->taskService->getActiveTasks($user->id);
        
        $count = $tasks->count();

        if ($count == 0) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "🎉 У тебя нет активных задач! 🎉"
            ]);
            return response()->json(['status' => 'ok']);
        }
        
        $keyboard = $this->getTasksKeyboard($user);
        
        $response = "📋 *Твои задачи ({$count}):*\n";
        foreach ($tasks as $index => $task) {
            $response .= ($index + 1) . ". {$task->task_text}\n";
        }
        $response .= "\n✅ Нажми на задачу, чтобы выполнить";
        
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $response,
            'parse_mode' => 'Markdown',
            'reply_markup' => $keyboard
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function handleDone($telegram, $chatId, $user, $taskNumber)
    {
        $tasks = $this->taskService->getActiveTasks($user->id);

        $index = $taskNumber - 1;

        if (!isset($tasks[$index])) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "❌ Задача с номером {$taskNumber} не найдена!"
            ]);
            return response()->json(['status' => 'ok']);
        }

        $task = $tasks[$index];
        $task->status = true;
        $task->save();

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "✅ Задача '{$task->task_text}' выполнена!"
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function handleWeather($telegram, $chatId, $user)
    {
        $user->state = 'waiting_city';
        $user->save();
        
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => '🔍 Введите название города 🔍',
            'reply_markup' => json_encode(['remove_keyboard' => true])
        ]);
        
        return response()->json(['status' => 'ok']);
    }

    private function handleGetWeather($telegram, $chatId, $user, $city)
    {
        try {

            $weather = $this->weatherService->getWeather($city);
            
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' =>
                    "🌡 Температура: {$weather['temp']}°C\n" .
                    "🤔 Ощущается: {$weather['feels_like']}°C\n" .
                    "💧 Влажность: {$weather['humidity']}%\n" .
                    "💨 Ветер: {$weather['wind_speed']} м/с"
            ]);

        } catch (\Exception $e) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '❌ ' . $e->getMessage()
            ]);
        }

        $user->state = null;
        $user->save();

        return response()->json(['status' => 'ok']);
    }

    private function handleCallback($telegram, $callbackQuery)
    {
        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $data = $callbackQuery->getData();
        
        $telegram->answerCallbackQuery([
            'callback_query_id' => $callbackQuery->getId(),
        ]);
        
        if (str_starts_with($data, 'done_')) {
            $taskId = str_replace('done_', '', $data);
            $task = UserTask::find($taskId);
            
            if ($task && $task->status == false) {
                $task->status = true;
                $task->save();

                $this->refreshTasksMessage($telegram, $chatId, $callbackQuery->getMessage()->getMessageId());
                
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "✅ Задача «{$task->task_text}» выполнена! ✅"
                ]);
            } else {
                $telegram->answerCallbackQuery([
                    'callback_query_id' => $callbackQuery->getId(),
                    'text' => "❌ Задача уже была выполнена",
                    'show_alert' => false
                ]);
            }
        }
        
        return response()->json(['status' => 'ok']);
    }

    private function refreshTasksMessage($telegram, $chatId, $messageId)
    {
        $user = TelegramUser::where('chat_id', $chatId)->first();
        if (!$user) return;
        
        $tasks = $this->taskService->getActiveTasks($user->id);
        
        $count = $tasks->count();
        
        if ($count == 0) {
            $telegram->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => "🎉 У тебя нет активных задач! 🎉"
            ]);
            return;
        }
        
        $keyboard = $this->getTasksKeyboard($user);
        
        $response = "📋 *Твои задачи ({$count}):*\n";
        foreach ($tasks as $index => $task) {
            $response .= ($index + 1) . ". {$task->task_text}\n";
        }
        $response .= "\n✅ Нажми на задачу, чтобы выполнить";
        
        $telegram->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $response,
            'parse_mode' => 'Markdown',
            'reply_markup' => $keyboard
        ]);
    }

    private function handleAiMode($telegram, $chatId, $user)
    {
        $user->state = 'waiting_ai';
        $user->save();
        
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "🤖 ИИ-режим активирован!\n\nЗадайте любой вопрос, и я постараюсь ответить.\n\nЧтобы выключить режим, отправьте команду /exit",
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode(['remove_keyboard' => true])
        ]);
        
        return response()->json(['status' => 'ok']);
    }

    private function handleAiQuery($telegram, $chatId, $user, $query)
    {
        if ($query === '/exit') {

            $user->state = null;
            $user->save();

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '🤖 ИИ-режим выключен!',
                'reply_markup' => $this->getMainKeyboard()
            ]);

            return response()->json(['status' => 'ok']);
        }

        try {

            $answer = $this->aiService->ask($query);

            $answer = mb_substr($answer, 0, 4000);

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $answer
            ]);

        } catch (\Exception $e) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '❌ ' . $e->getMessage(),
                'reply_markup' => $this->getMainKeyboard()
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    private function handleExportTasks($telegram, $chatId, $user)
    {
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => '⏳ Подготавливаю Excel файл...'
        ]);

        $fileName = 'tasks_' . $user->id . '.xlsx';

        Excel::store(
            new TasksExport($user->id),
            $fileName,
            'public'
        );

        $filePath = storage_path('app/public/' . $fileName);

        $telegram->sendDocument([
            'chat_id' => $chatId,
            'document' => fopen($filePath, 'r'),
            'caption' => '📥 Ваш экспорт задач',
            'reply_markup' => $this->getMainKeyboard()
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function handleImport($telegram, $chatId, $user)
    {
        $user->state = 'waiting_import';
        $user->save();

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => '📤 Отправьте Excel файл (.xlsx) с задачами',
            'reply_markup' => json_encode([
                'remove_keyboard' => true
            ])
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function handleImportTasks($telegram, $chatId, $user, $message)
    {
        try {

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

            ImportTasksJob::dispatch(
                $user->id,
                $chatId,
                $localPath
            );

            $user->state = null;
            $user->save();

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '⏳ Импорт поставлен в очередь',
                'reply_markup' => $this->getMainKeyboard()
            ]);

        } catch (\Exception $e) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '❌ Ошибка импорта: ' . $e->getMessage(),
                'reply_markup' => $this->getMainKeyboard()
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    private function handleUnknown($telegram, $chatId)
    {
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => '❌ Используйте кнопки',
            'reply_markup' => $this->getMainKeyboard()
        ]);

        return response()->json(['status' => 'ok']);
    }
}