<?php

namespace App\Services;

use Telegram\Bot\Api;

class TelegramService
{
    protected Api $telegram;

    public function __construct()
    {
        $this->telegram = new Api(
            env('TELEGRAM_BOT_TOKEN')
        );
    }

    public function sendMessage(int $chatId,string $text,$keyboard = null): void
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($keyboard) {
            $params['reply_markup'] = $keyboard;
        }

        $this->telegram->sendMessage($params);
    }
}