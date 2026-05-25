<?php

namespace App\Services;
use App\Models\TelegramUser;

class UserService
{
    public function findUser($chatId)
    {
        return TelegramUser::where('chat_id', $chatId)->first();;
    }

    public function updateOrCreateUser($message, $chatId)
    {
        $firstName = $message->getChat()->getFirstName() ?? 'User';
        $lastName = $message->getChat()->getLastName() ?? null;
        $username = $message->getChat()->getUsername();

        $user = TelegramUser::updateOrCreate(
            ['chat_id' => $chatId],
            [
                'username' => $username,
                'first_name' => $firstName,
                'last_name' => $lastName
            ]
        );

        $text = $user->wasRecentlyCreated 
            ? "✅ Авторизация прошла успешно" 
            : "✅ Вы авторизованы";

        return $text;
    }
}
