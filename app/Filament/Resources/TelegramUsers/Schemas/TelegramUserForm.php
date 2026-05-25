<?php

namespace App\Filament\Resources\TelegramUsers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TelegramUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('chat_id')
                    ->required()
                    ->numeric(),
                TextInput::make('username'),
                TextInput::make('first_name'),
                TextInput::make('last_name'),
            ]);
    }
}
