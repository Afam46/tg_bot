<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\UserTask;
use App\Models\TelegramUser;
use App\Services\KeyboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class KeyboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_tasks_keyboard(): void
    {
        $user = TelegramUser::create([
            'chat_id' => 1,
            'telegram_id' => 123456,
            'username' => 'afamka',
        ]);

        UserTask::create([
            'telegram_user_id' => $user->id,
            'task_text' => 'Купить хлеб',
            'status' => false,
        ]);

        $keyboardService = new KeyboardService;

        $keyboard = $keyboardService->getTasksKeyboard($user->id);

        $keyboard = json_decode($keyboard, true);

        $this->assertEquals(
            '✅ Купить хлеб',
            $keyboard['inline_keyboard'][0][0]['text']
        );

        $this->assertEquals(
            'done_1',
            $keyboard['inline_keyboard'][0][0]['callback_data']
        );
    }
}