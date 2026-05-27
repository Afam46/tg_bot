<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\UserTask;
use Tests\TestCase;
use App\Models\TelegramUser;
use App\Services\TaskService;

class TaskServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_task(): void
    {
        $user = TelegramUser::create([
            'chat_id' => 1,
            'telegram_id' => 123456,
            'username' => 'test_user',
        ]);

        $taskService = new TaskService;

        $task = $taskService->createTask($user->id, 'Тестовая задача');

        $this->assertEquals('Тестовая задача', $task->task_text);

        $this->assertDatabaseHas('user_tasks', [
            'task_text' => 'Тестовая задача',
        ]);
    }

    public function test_user_can_complete_task(): void
    {
        $user = TelegramUser::create([
            'chat_id' => 1,
            'telegram_id' => 123456,
            'username' => 'test_user',
        ]);

        $task = UserTask::create([
            'telegram_user_id' => $user->id,
            'task_text' => 'Купить пиво',
            'status' => false,
        ]);

        $taskService = new TaskService;
        
        $taskService->completeTask($task);

        $this->assertDatabaseHas('user_tasks', [
            'task_text' => 'Купить пиво',
            'status' => true,
        ]);
    }

    public function test_find_task(): void
    {
        $user = TelegramUser::create([
            'chat_id' => 1,
            'telegram_id' => 123456,
            'username' => 'test_user',
        ]);

        $task = UserTask::create([
            'telegram_user_id' => $user->id,
            'task_text' => 'Купить пиво',
            'status' => false,
        ]);

        $taskService = new TaskService;

        $foundTask = $taskService->findTask($task->id);

        $this->assertEquals('Купить пиво', $foundTask->task_text);
    }

    public function test_get_active_tasks(): void
    {
        $taskService = new TaskService;

        $user = TelegramUser::create([
            'chat_id' => 1,
            'telegram_id' => 123456,
            'username' => 'test_user',
        ]);

        $task = UserTask::create([
            'telegram_user_id' => $user->id,
            'task_text' => 'Купить пиво',
            'status' => false,
        ]);

        $task = UserTask::create([
            'telegram_user_id' => $user->id,
            'task_text' => 'Купить хлеб',
            'status' => false,
        ]);

        $tasks = $taskService->getActiveTasks($user->id);

        $this->assertCount(2, $tasks);
    }
}
