<?php

namespace App\Filament\Resources\UserTasks;

use App\Filament\Resources\UserTasks\Pages\CreateUserTask;
use App\Filament\Resources\UserTasks\Pages\EditUserTask;
use App\Filament\Resources\UserTasks\Pages\ListUserTasks;
use App\Filament\Resources\UserTasks\Pages\ViewUserTask;
use App\Filament\Resources\UserTasks\Schemas\UserTaskForm;
use App\Filament\Resources\UserTasks\Schemas\UserTaskInfolist;
use App\Filament\Resources\UserTasks\Tables\UserTasksTable;
use App\Models\UserTask;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UserTaskResource extends Resource
{
    protected static ?string $model = UserTask::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'UserTask';

    public static function form(Schema $schema): Schema
    {
        return UserTaskForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserTaskInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserTasksTable::configure($table);
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
            'index' => ListUserTasks::route('/'),
            'create' => CreateUserTask::route('/create'),
            'view' => ViewUserTask::route('/{record}'),
            'edit' => EditUserTask::route('/{record}/edit'),
        ];
    }
}
