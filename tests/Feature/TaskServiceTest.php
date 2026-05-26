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
}
