<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TasksExport;
use App\Services\WeatherService;
use App\Services\AiService;
use App\Services\TaskService;
use App\Services\TelegramService;
use App\Services\ImportService;
use App\Services\UserService;
use App\Services\KeyboardService;
use App\Services\ExportService;

class TelegramBotController extends Controller
{
    protected WeatherService $weatherService;
    protected AiService $aiService;
    protected TaskService $taskService;
    protected TelegramService $telegramService;
    protected ImportService $importService;
    protected UserService $userService;
    protected KeyboardService $keyboardService;
    protected ExportService $exportService;

    public function __construct(
        WeatherService $weatherService,
        AiService $aiService,
        TaskService $taskService,
        TelegramService $telegramService,
        ImportService $importService,
        UserService $userService,
        KeyboardService $keyboardService,
        ExportService $exportService
    ) {
        $this->weatherService = $weatherService;
        $this->aiService = $aiService;
        $this->taskService = $taskService;
        $this->telegramService = $telegramService;
        $this->importService = $importService;
        $this->userService = $userService;
        $this->keyboardService = $keyboardService;
        $this->exportService = $exportService;
    }

    private function ok(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function webhook(Request $request): JsonResponse
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

        return $this->handleUnknown($chatId);
    }

    private function handleStart(object $message, int $chatId): JsonResponse
    {
        $text = $this->userService->updateOrCreateUser($message, $chatId);

        $this->telegramService->sendMessage($chatId, $text, $this->keyboardService->getMainKeyboard());

        return $this->ok();
    }

    private function handleUnregistered(int $chatId): JsonResponse
    {
        $this->telegramService->sendMessage($chatId, 'Привет! Напиши /start, чтобы авторизоваться',
            $this->keyboardService->getStartKeyboard());

        return $this->ok();
    }

    private function handleWaitingTask(int $chatId, object $user, string $text): JsonResponse
    {
        $this->taskService->createTask($user->id, $text);
        $this->userService->setState($user, null);

        $this->telegramService->sendMessage($chatId, "✅ Задача добавлена!\n📋 Текст: {$text}",
            $this->keyboardService->getMainKeyboard());

        return $this->ok();
    }

    private function handleProfile(int $chatId, object $user): JsonResponse
    {   
        $stats = $this->taskService->getStats($user);
      
        $text = "👤 {$user->username} 👤\n\n" . "📛 Имя: {$user->first_name}\n"
        . "📋 Всего задач: {$stats['all']}\n" . "✅ Решенных задач: {$stats['completed']}\n"
        . "❌ Осталось задач: {$stats['uncompleted']}";

        $this->telegramService->sendMessage($chatId, $text);

        return $this->ok();
    }

    private function handleCreateTask(int $chatId, object $user): JsonResponse
    {
        $this->userService->setState($user, 'waiting_task');

        $this->telegramService->sendMessage($chatId, '✏️ Опиши задачу:', json_encode(['remove_keyboard' => true]));

        return $this->ok();
    }

   private function handleMyTasks(int $chatId, object $user, int $page = 1): JsonResponse
   {
        $tasks = $this->taskService->getActiveTasksPaginated($user->id, $page);

        $count = $tasks['total'];

        if ($count == 0) {
            $this->telegramService->sendMessage($chatId, "🎉 У тебя нет активных задач! 🎉");

            return $this->ok();
        }

        $response = "📋 Твои задачи ({$count}):\n\n";

        foreach ($tasks['items'] as $index => $task) {

            $number = (($page - 1) * 5) + $index + 1;

            $response .= "{$number}. {$task->task_text}\n";
        }

        $response .= "\n✅ Нажми на задачу, чтобы выполнить";

        $this->telegramService->sendMessage($chatId, $response,
            $this->keyboardService->getTasksKeyboard($user->id,$page));

        return $this->ok();
    }

    private function handleDone(int $chatId, object $user, int $taskId, int $messageId, int $page): JsonResponse
    {
        $task = $this->taskService->findTask($taskId);

        if (!$task || $task->status) {
            return $this->ok();
        }

        $this->taskService->completeTask($task);

        $tasks = $this->taskService->getActiveTasksPaginated($user->id, $page);

        if ($page > 1 && count($tasks['items']) === 0) {

            $page--;

            $tasks = $this->taskService->getActiveTasksPaginated($user->id, $page);
        }

        $count = $tasks['total'];

        if ($count === 0){

            $this->telegramService->editMessageText($chatId, $messageId, "🎉 У тебя больше нет активных задач!");

            return $this->ok();
        }

        $response = "📋 Твои задачи ({$count}):\n\n";

        foreach ($tasks['items'] as $index => $task) {

            $number = (($page - 1) * 5) + $index + 1;

            $response .= "{$number}. {$task->task_text}\n";
        }

        $response .= "\n✅ Нажми на задачу, чтобы выполнить";

        $this->telegramService->editMessageText($chatId, $messageId, $response, null,
            $this->keyboardService->getTasksKeyboard($user->id, $page));

        return $this->ok();
    }

    private function handleWeather(int $chatId, object $user): JsonResponse
    {
        $this->userService->setState($user, 'waiting_city');
        
        $this->telegramService->sendMessage($chatId, '🔍 Введите название города 🔍',
        json_encode(['remove_keyboard' => true]));
        
        return $this->ok();
    }

    private function handleGetWeather(int $chatId, object $user, string $city): JsonResponse
    {
        try {

            $weather = $this->weatherService->getWeather($city);
            
            $text = "🌍 Погода в городе '{$city}'\n\n" . "🌡 Температура: {$weather['temp']}°C\n" .
            "🤔 Ощущается: {$weather['feels_like']}°C\n" . "💧 Влажность: {$weather['humidity']}%\n" .
            "💨 Ветер: {$weather['wind_speed']} м/с";

            $this->telegramService->sendMessage($chatId, $text, $this->keyboardService->getMainKeyboard());

        } catch (\Exception $e) {
            Log::error('Ошибка: ' . $e->getMessage());
            
            $this->telegramService->sendMessage($chatId, '❌ ' . $e->getMessage(),
                $this->keyboardService->getMainKeyboard());
        }

        $this->userService->setState($user, null);

        return $this->ok();
    }

    private function handleCallback(object $callbackQuery): JsonResponse
    {
        $chatId = $callbackQuery->getMessage()->getChat()->getId();

        $messageId = $callbackQuery->getMessage()->getMessageId();

        $data = $callbackQuery->getData();

        $user = $this->userService->findUser($chatId);

        $this->telegramService->answerCallbackQuery($callbackQuery);

        if (str_starts_with($data, 'done_')){

            [, $taskId, $page] = explode('_', $data);

            return $this->handleDone($chatId, $user, (int) $taskId, $messageId, (int) $page);
        }

        if (str_starts_with($data, 'tasks_page_')) {

            $page = (int) str_replace('tasks_page_', '',$data);

            $tasks = $this->taskService->getActiveTasksPaginated($user->id, $page);

            $count = $tasks['total'];

            $response = "📋 Твои задачи ({$count}):\n\n";

            foreach ($tasks['items'] as $index => $task) {

                $number = (($page - 1) * 5) + $index + 1;

                $response .= "{$number}. {$task->task_text}\n";
            }

            $response .= "\n✅ Нажми на задачу, чтобы выполнить";

            $this->telegramService->editMessageText($chatId, $messageId, $response, null,
                $this->keyboardService->getTasksKeyboard($user->id, $page));
        }

        return $this->ok();
    }

    private function handleAiMode(int $chatId, object $user): JsonResponse
    {
        $this->userService->setState($user, 'waiting_ai');

        $text = "🤖 ИИ-режим активирован!\n\nЗадайте любой вопрос, и я постараюсь ответить.".
        "\n\nЧтобы выключить режим, отправьте команду /exit";

        $this->telegramService->sendMessage($chatId, $text, json_encode(['remove_keyboard' => true]));
        
        return $this->ok();
    }

    private function handleAiQuery(int $chatId, object $user,string $query): JsonResponse
    {
        if ($query === '/exit') {

            $this->userService->setState($user, null);

            $this->telegramService->sendMessage(
                $chatId,
                '🤖 ИИ-режим выключен!',
                $this->keyboardService->getMainKeyboard()
            );

            return $this->ok();
        }

        $this->telegramService->sendChatAction($chatId);

        $this->aiService->process($chatId, $query);

        return $this->ok();
    }

    private function handleExportTasks(int $chatId, object $user): JsonResponse
    {
        $this->exportService->exportFile($user, $chatId);
        
        $this->telegramService->sendMessage($chatId, '⏳ Экспорт поставлен в очередь',
            $this->keyboardService->getMainKeyboard());

        return $this->ok();
    }

    private function handleImport(int $chatId, object $user): JsonResponse
    {
        $this->userService->setState($user, 'waiting_import');

        $this->telegramService->sendMessage($chatId, '📤 Отправьте Excel файл (.xlsx) с задачами',
            json_encode(['remove_keyboard' => true]));

        return $this->ok();
    }

    private function handleImportTasks(int $chatId, object $user, object $message): JsonResponse
    {
        try {

            $this->importService->importFile($this->telegramService->getTelegram(), $message, $user, $chatId);
            $this->userService->setState($user, null);
            $this->telegramService->sendMessage($chatId, '⏳ Импорт поставлен в очередь',
                $this->keyboardService->getMainKeyboard());
        
        } catch (\Exception $e) {
            Log::error('Ошибка: ' . $e->getMessage());
            $this->telegramService->sendMessage($chatId, '❌ Ошибка импорта: ' . $e->getMessage(), $this->keyboardService->getMainKeyboard());
        }

        return $this->ok();
    }

    private function handleUnknown(int $chatId): JsonResponse
    {
        $this->telegramService->sendMessage($chatId, '❌ Используйте кнопки', $this->keyboardService->getMainKeyboard());
        return $this->ok();
    }
}