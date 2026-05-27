<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\TelegramUser;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class TelegramUserServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_updates_if_exists(): void
    {
        TelegramUser::create([
            'chat_id' => 1,
            'username' => 'old_username',
        ]);

        $chatMock = Mockery::mock();
        $chatMock->shouldReceive('getFirstName')->andReturn('Islam');
        $chatMock->shouldReceive('getLastName')->andReturn('Zainullin');
        $chatMock->shouldReceive('getUsername')->andReturn('new_username');

        $messageMock = Mockery::mock();
        $messageMock->shouldReceive('getChat')->andReturn($chatMock);

        $userService = new UserService;

        $text = $userService->updateOrCreateUser($messageMock, 1);

        $this->assertEquals('✅ Вы авторизованы', $text);

        $this->assertDatabaseHas('telegram_users', [
            'chat_id' => 1,
            'username' => 'new_username',
        ]);

        $this->assertDatabaseCount('telegram_users', 1);
    }

    public function test_telegram_user_find(): void
    {
        TelegramUser::create([
            'chat_id' => 1,
            'telegram_id' => 123456,
            'username' => 'test_user',
        ]);

        $userService = new UserService;

        $user = $userService->findUser(1);

        $this->assertEquals('test_user', $user->username);
    }

    public function test_set_state():void 
    {
        $user = TelegramUser::create([
            'chat_id' => 1,
            'telegram_id' => 123456,
            'username' => 'test_user',
        ]);

        $userService = new UserService;

        $userService->setState($user, 'test');

        $this->assertEquals($user->state, 'test');
    }
}
