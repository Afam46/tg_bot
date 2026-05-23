<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Telegram\Bot\Api;
use App\Models\TelegramUser;

class TelegramBotController extends Controller
{
    public function webhook(Request $request)
    {
        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        $update = $telegram->getWebhookUpdate();

        $message = $update->getMessage();

        if (!$message || !$message->getText()) {
            return response()->json(['status' => 'ok'])->header('ngrok-skip-browser-warning', 'true');
        }

        $chat_id = $message->getChat()->getId();
        $text = $message->getText();

        if($text === '/start'){
            $first_name = $message->getChat()->getFirstName() ?? 'User';
            $last_name = $message->getChat()->getLastName() ?? null;
            $user_name = $message->getChat()->getUsername();

            TelegramUser::updateOrCreate(
                ['chat_id' => $chat_id],
                [
                    'username' => $user_name,
                    'first_name' => $first_name,
                    'last_name' => $last_name
                ]
            );

            $telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => "Чел, теперь ты сохранен в моей БД",
                'reply_markup' => json_encode([
                    'keyboard' => [['Мой профиль', 'Создать задачу']],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false
                ])
            ]);

            return response()->json(['status' => 'ok'])->header('ngrok-skip-browser-warning', 'true');
        }

        if(!$user = TelegramUser::where('chat_id', $chat_id)->first()){
            $telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => 'Привет! Напиши /start, чтобы зарегистрироваться.',
                'reply_markup' => json_encode([
                    'keyboard' => [['/start']],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false
                ])
            ]);

            return response()->json(['status' => 'ok'])->header('ngrok-skip-browser-warning', 'true');
        }

        if($text === 'Мой профиль'){
            $telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => "$user->username"
            ]);
        }elseif($text === 'Создать задачу'){
            $telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => 'Напиши название:' //как тут продолжить? если написать название, то будет else
            ]);
        }else{
            $telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => 'Используй кнопки!',
                'reply_markup' => json_encode([
                    'keyboard' => [['Мой профиль', 'Создать задачу']],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false
                ])
            ]);
        }


        return response()->json(['status' => 'ok'])->header('ngrok-skip-browser-warning', 'true');
    }
}