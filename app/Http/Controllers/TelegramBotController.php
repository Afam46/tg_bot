<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TasksExport;
use App\Services\WeatherService;
use App\Services\AiService;
use App\Services\TaskService;
use App\Services\TelegramService;
use App\Services\UserStateService;
use App\Services\ImportService;
use App\Services\UserService;
use App\Services\KeyboardService;

class TelegramBotController extends Controller
{
    protected WeatherService $weatherService;
    protected AiService $aiService;
    protected TaskService $taskService;
    protected TelegramService $telegramService;
    protected UserStateService $userStateService;
    protected ImportService $importService;
    protected UserService $userService;
    protected KeyboardService $keyboardService;

    public function __construct(WeatherService $weatherService, AiService $aiService,
    TaskService $taskService, TelegramService $telegramService, UserStateService $userStateService,
    ImportService $importService, UserService $userService, KeyboardService $keyboardService)
    {
        $this->weatherService = $weatherService;
        $this->aiService = $aiService;
        $this->taskService = $taskService;
        $this->telegramService = $telegramService;
        $this->userStateService = $userStateService;
        $this->importService = $importService;
        $this->userService = $userService;
        $this->keyboardService = $keyboardService;
    }

    private function ok()
    {
        return response()->json(['status' => 'ok']);
    }

    public function webhook(Request $request)
    {
        $telegram = $this->telegramService->getTelegram();
        $update = $telegram->getWebhookUpdate();

        if ($callbackQuery = $update->getCallbackQuery()) {
            return $this->handleCallback($callbackQuery);
        }

        $message = $update->getMessage();

        if (!$message) {
            return $this->ok();
        }

        $chatId = $message->getChat()->getId();
        $text = $message->getText() ?? '';

        if ($text === '/start') {
            return $this->handleStart($message, $chatId);
        }

        $user = $this->userService->findUser($chatId);

        if ($user && $user->state === 'waiting_import' && $message->getDocument()) {
            return $this->handleImportTasks($chatId, $user, $message);
        }

        if (!$user) {
            return $this->handleUnregistered($chatId);
        }

        if ($user->state === 'waiting_task') {
            return $this->handleWaitingTask($chatId, $user, $text);
        }

        if ($user->state === 'waiting_city') {
            return $this->handleGetWeather($chatId, $user, $text);
        }

        if ($user->state === 'waiting_ai') {
            return $this->handleAiQuery($chatId, $user, $text);
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
            return $this->$method($chatId, $user);
        }

        if (preg_match('/^\/done (\d+)$/', $text, $matches)) {
            return $this->handleDone($chatId, $user, (int)$matches[1]);
        }

        return $this->handleUnknown($chatId);
    }

    private function handleStart($message, $chatId)
    {
        $text = $this->userService->updateOrCreateUser($message, $chatId);

        $this->telegramService->sendMessage($chatId, $text, $this->keyboardService->getMainKeyboard());

        return $this->ok();
    }

    private function handleUnregistered($chatId)
    {
        $this->telegramService->sendMessage($chatId, 'Привет! Напиши /start, чтобы авторизоваться',
            $this->keyboardService->getStartKeyboard());

        return $this->ok();
    }

    private function handleWaitingTask($chatId, $user, $text)
    {
        $this->taskService->createTask($user->id, $text);
        $this->userStateService->setState($user, null);

        $this->telegramService->sendMessage($chatId, "✅ Задача добавлена!\n📋 Текст: {$text}",
            $this->keyboardService->getMainKeyboard());

        return $this->ok();
    }

    private function handleProfile($chatId, $user)
    {   
        $stats = $this->taskService->getStats($user);
      
        $text = "👤 {$user->username} 👤\n\n" . "📛 Имя: {$user->first_name}\n"
        . "📋 Всего задач: {$stats['all']}\n" . "✅ Решенных задач: {$stats['completed']}\n"
        . "❌ Осталось задач: {$stats['uncompleted']}";

        $this->telegramService->sendMessage($chatId, $text);

        return $this->ok();
    }

    private function handleCreateTask($chatId, $user)
    {
        $this->userStateService->setState($user, 'waiting_task');

        $this->telegramService->sendMessage($chatId, '✏️ Опиши задачу:', json_encode(['remove_keyboard' => true]));

        return $this->ok();
    }

    private function handleMyTasks($chatId, $user)
    {
        $tasks = $this->taskService->getActiveTasks($user->id);
        
        $count = $tasks->count();

        if ($count == 0) {
            $this->telegramService->sendMessage($chatId, "🎉 У тебя нет активных задач! 🎉");
            return $this->ok();
        }
        
        $response = "📋 *Твои задачи ({$count}):*\n";
        foreach ($tasks as $index => $task) {
            $response .= ($index + 1) . ". {$task->task_text}\n";
        }
        $response .= "\n✅ Нажми на задачу, чтобы выполнить";
        
        $this->telegramService->sendMessage($chatId, $response, $this->keyboardService->getTasksKeyboard($user->id));

        return $this->ok();
    }

    private function handleDone($chatId, $user, $taskNumber)
    {
        $tasks = $this->taskService->getActiveTasks($user->id);

        $index = $taskNumber - 1;

        if (!isset($tasks[$index])) {
            $this->telegramService->sendMessage($chatId, "❌ Задача с номером {$taskNumber} не найдена!" );
            return $this->ok();
        }

        $task = $tasks[$index];
        
        $this->taskService->completeTask($task);
        $this->telegramService->sendMessage($chatId, "✅ Задача '{$task->task_text}' выполнена!" );

        return $this->ok();
    }

    private function handleWeather($chatId, $user)
    {
        $this->userStateService->setState($user, 'waiting_city');
        
        $this->telegramService->sendMessage($chatId, '🔍 Введите название города 🔍',
        json_encode(['remove_keyboard' => true]));
        
        return $this->ok();
    }

    private function handleGetWeather($chatId, $user, $city)
    {
        try {

            $weather = $this->weatherService->getWeather($city);
            
            $text = "🌍 Погода в городе '{$city}'\n\n" . "🌡 Температура: {$weather['temp']}°C\n" .
            "🤔 Ощущается: {$weather['feels_like']}°C\n" . "💧 Влажность: {$weather['humidity']}%\n" .
            "💨 Ветер: {$weather['wind_speed']} м/с";

            $this->telegramService->sendMessage($chatId, $text, $this->keyboardService->getMainKeyboard());

        } catch (\Exception $e) {
            Log::error('Ошибка: ' . $e->getMessage());
            
            $this->telegramService->sendMessage($chatId, '❌ ' . $e->getMessage());
        }

        $this->userStateService->setState($user, null);

        return $this->ok();
    }

    private function handleCallback($callbackQuery)
    {
        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $data = $callbackQuery->getData();

        $this->telegramService->answerCallbackQuery($callbackQuery);
        
        if (str_starts_with($data, 'done_')) {
            $taskId = str_replace('done_', '', $data);
            $task = $this->taskService->findTask($taskId);
            
            if ($task && $task->status == false) {

                $this->taskService->completeTask($task);
                $this->refreshTasksMessage($chatId, $callbackQuery->getMessage()->getMessageId());
                $this->telegramService->sendMessage($chatId, "✅ Задача «{$task->task_text}» выполнена! ✅");

            } else {
                $this->telegramService->answerCallbackQuery($callbackQuery,
                "❌ Задача уже была выполнена", false);
            }
        }
        
        return $this->ok();
    }

    private function refreshTasksMessage($chatId, $messageId)
    {
        $user = $this->userService->findUser($chatId);

        if (!$user) return;
        
        $tasks = $this->taskService->getActiveTasks($user->id);
        
        $count = $tasks->count();
        
        if ($count == 0) {
            $this->telegramService->editMessageText($chatId, $messageId, "🎉 У тебя нет активных задач! 🎉");
            return;
        }
        
        $keyboard = $this->keyboardService->getTasksKeyboard($user->id);
        
        $response = "📋 *Твои задачи ({$count}):*\n";
        foreach ($tasks as $index => $task) {
            $response .= ($index + 1) . ". {$task->task_text}\n";
        }
        $response .= "\n✅ Нажми на задачу, чтобы выполнить";

        $this->telegramService->editMessageText($chatId, $messageId, $response, 'Markdown', $keyboard);
    }

    private function handleAiMode($chatId, $user)
    {
        $this->userStateService->setState($user, 'waiting_ai');

        $text = "🤖 ИИ-режим активирован!\n\nЗадайте любой вопрос, и я постараюсь ответить.".
        "\n\nЧтобы выключить режим, отправьте команду /exit";

        $this->telegramService->sendMessage($chatId, $text, json_encode(['remove_keyboard' => true]));
        
        return $this->ok();
    }

    private function handleAiQuery($chatId, $user, $query)
    {
        if ($query === '/exit') {
            $this->userStateService->setState($user, null);

            $this->telegramService->sendMessage($chatId, '🤖 ИИ-режим выключен!', $this->keyboardService->getMainKeyboard());

            return $this->ok();
        }

        try {
            $this->telegramService->sendChatAction($chatId);

            $answer = $this->aiService->ask($query);

            $answer = mb_substr($answer, 0, 4000);

            $this->telegramService->sendMessage($chatId, $answer);

        } catch (\Exception $e) {
            Log::error('Ошибка: ' . $e->getMessage());
            $this->telegramService->sendMessage($chatId, '❌ ' . $e->getMessage(), $this->keyboardService->getMainKeyboard());
        }

        return $this->ok();
    }

    private function handleExportTasks($chatId, $user)
    {
        $this->telegramService->sendMessage($chatId, '⏳ Подготавливаю Excel файл...');

        $fileName = 'tasks_' . $user->id . '.xlsx';

        Excel::store(
            new TasksExport($user->id),
            $fileName,
            'public'
        );

        $filePath = storage_path('app/public/' . $fileName);

        $this->telegramService->sendDocument($chatId, $filePath,
        '📥 Ваш экспорт задач', $this->keyboardService->getMainKeyboard());

        return $this->ok();
    }

    private function handleImport($chatId, $user)
    {
        $this->userStateService->setState($user, 'waiting_import');

        $this->telegramService->sendMessage($chatId, '📤 Отправьте Excel файл (.xlsx) с задачами',
        json_encode(['remove_keyboard' => true]));

        return $this->ok();
    }

    private function handleImportTasks($chatId, $user, $message)
    {
        try {

            $this->importService->importFile($this->telegramService->getTelegram(), $message, $user, $chatId);
            $this->userStateService->setState($user, null);
            $this->telegramService->sendMessage($chatId, '⏳ Импорт поставлен в очередь', $this->keyboardService->getMainKeyboard());
        
        } catch (\Exception $e) {
            Log::error('Ошибка: ' . $e->getMessage());
            $this->telegramService->sendMessage($chatId, '❌ Ошибка импорта: ' . $e->getMessage(), $this->keyboardService->getMainKeyboard());
        }

        return $this->ok();
    }

    private function handleUnknown($chatId)
    {
        $this->telegramService->sendMessage($chatId, '❌ Используйте кнопки', $this->keyboardService->getMainKeyboard());
        return $this->ok();
    }
}