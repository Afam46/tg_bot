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

    public function getTelegram(){
        return $this->telegram;
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

    public function answerCallbackQuery($callbackQuery, $text = null, $showAlert = null){
        $params = [
            'callback_query_id' => $callbackQuery->getId(),
        ];

        if(!is_null($text)) {
            $params['text'] = $text;
        }

        if(!is_null($showAlert)){
            $params['show_alert'] = $showAlert;
        }

        $this->telegram->answerCallbackQuery($params);
    }

    public function editMessageText($chatId, $messageId, $text, $parseMode = null, $replyMarkup = null)
    {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
        ];

        if(!is_null($parseMode)){
            $params['parse_mode'] = $parseMode;
        }

        if(!is_null($replyMarkup)){
            $params['reply_markup'] = $replyMarkup;
        }

        $this->telegram->editMessageText($params);
    }

    public function sendChatAction($chatId)
    {
        $params = [
            'chat_id' => $chatId,
            'action' => 'typing'
        ];

        $this->telegram->sendChatAction($params);
    }

    public function sendDocument($chatId, $filePath, $text, $keyboard)
    {
        $params = [
            'chat_id' => $chatId,
            'document' => fopen($filePath, 'r'),
            'caption' => $text,
            'reply_markup' => $keyboard
        ];

        $this->telegram->sendDocument($params);
    }
}