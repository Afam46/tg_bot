<?php

namespace Tests\Feature;

use Tests\TestCase;
use Mockery;
use Telegram\Bot\Api;
use App\Services\TelegramService;

class TelegramServiceTest extends TestCase
{
    public function test_can_send_message(): void
    {
        $telegramMock = Mockery::mock(Api::class);

        $telegramMock->shouldReceive('sendMessage')->once()->with([
            'chat_id' => 1,
            'text' => 'Привет',
        ]);

        $telegramService = new TelegramService($telegramMock);

        $telegramService->sendMessage(1, 'Привет');
    }

    public function test_get_telegram(): void
    {
        $telegramMock = Mockery::mock(Api::class);

        $telegramService = new TelegramService($telegramMock);

        $result = $telegramService->getTelegram();

        $this->assertInstanceOf(Api::class, $result);
    }

    public function test_answer_callback_query(): void
    {
        $telegramMock = Mockery::mock(Api::class);

        $callbackMock = Mockery::mock();

        $callbackMock->shouldReceive('getId')->andReturn('123');

        $telegramMock->shouldReceive('answerCallbackQuery')->once()->with(['callback_query_id' => '123',]);

        $telegramService = new TelegramService($telegramMock);

        $telegramService->answerCallbackQuery($callbackMock);
    }

    public function test_edit_message_text(): void
    {
        $telegramMock = Mockery::mock(Api::class);

        $telegramMock->shouldReceive('editMessageText')->once()->with([
            'chat_id' => 1,
            'message_id' => 123,
            'text' => 'test',
        ]);

        $telegramService = new TelegramService($telegramMock);

        $telegramService->editMessageText(1, 123, 'test');
    }

    public function test_send_chat_action(): void
    {
        $telegramMock = Mockery::mock(Api::class);

        $telegramMock->shouldReceive('sendChatAction')->once()->with([
            'chat_id' => 1,
            'action' => 'typing'
        ]);

        $telegramService = new TelegramService($telegramMock);

        $telegramService->sendChatAction(1);
    }

    public function test_send_document(): void
    {
        $telegramMock = Mockery::mock(Api::class);

        $telegramMock->shouldReceive('sendDocument')->once()->with(Mockery::on(function ($params) {
                return $params['chat_id'] === 1
                    && $params['caption'] === 'test'
                    && $params['reply_markup'] === 'keyboard'
                    && is_resource($params['document']);
            }));

        $telegramService = new TelegramService($telegramMock);

        $filePath = __FILE__;

        $telegramService->sendDocument(
            1,
            $filePath,
            'test',
            'keyboard'
        );
    }
}