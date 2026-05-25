<?php

namespace App\Filament\Resources\UserTasks\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserTaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('telegram_user_id')
                    ->tel()
                    ->required()
                    ->numeric(),
                Textarea::make('task_text')
                    ->required()
                    ->columnSpanFull(),
                Toggle::make('status')
                    ->required(),
            ]);
    }
}
