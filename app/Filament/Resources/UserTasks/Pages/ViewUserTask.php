<?php

namespace App\Filament\Resources\UserTasks\Pages;

use App\Filament\Resources\UserTasks\UserTaskResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUserTask extends ViewRecord
{
    protected static string $resource = UserTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
