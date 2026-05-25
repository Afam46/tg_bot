<?php

namespace App\Filament\Resources\TelegramUsers;

use App\Filament\Resources\TelegramUsers\Pages\CreateTelegramUser;
use App\Filament\Resources\TelegramUsers\Pages\EditTelegramUser;
use App\Filament\Resources\TelegramUsers\Pages\ListTelegramUsers;
use App\Filament\Resources\TelegramUsers\Pages\ViewTelegramUser;
use App\Filament\Resources\TelegramUsers\Schemas\TelegramUserForm;
use App\Filament\Resources\TelegramUsers\Schemas\TelegramUserInfolist;
use App\Filament\Resources\TelegramUsers\Tables\TelegramUsersTable;
use App\Models\TelegramUser;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TelegramUserResource extends Resource
{
    protected static ?string $model = TelegramUser::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'TelegramUser';

    public static function form(Schema $schema): Schema
    {
        return TelegramUserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TelegramUserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TelegramUsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelegramUsers::route('/'),
            'create' => CreateTelegramUser::route('/create'),
            'view' => ViewTelegramUser::route('/{record}'),
            'edit' => EditTelegramUser::route('/{record}/edit'),
        ];
    }
}
