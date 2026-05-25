<?php

namespace App\Filament\Resources\TelegramUsers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TelegramUserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('chat_id')
                    ->numeric(),
                TextEntry::make('username')
                    ->placeholder('-'),
                TextEntry::make('first_name')
                    ->placeholder('-'),
                TextEntry::make('last_name')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
