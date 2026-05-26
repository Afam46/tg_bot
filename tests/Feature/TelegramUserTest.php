<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\TelegramUser;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class TelegramUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_or_create(): void
    {
        $chatMock = Mockery::mock();
        $chatMock->shouldReceive('getFirstName')->andReturn('Islam');
        $chatMock->shouldReceive('getLastName')->andReturn('Zainullin');
        $chatMock->shouldReceive('getUsername')->andReturn('afamka');

        $messageMock = Mockery::mock();
        $messageMock->shouldReceive('getChat')->andReturn($chatMock);

        $userService = new UserService;

        $text = $userService->updateOrCreateUser($messageMock, 1);

        $this->assertEquals('✅ Авторизация прошла успешно', $text);

        $this->assertDatabaseHas('telegram_users', [
            'chat_id' => 1,
            'username' => 'afamka',
            'first_name' => 'Islam',
        ]);
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
}
