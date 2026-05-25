<?php

namespace App\Filament\Resources\TelegramUsers\Pages;

use App\Filament\Resources\TelegramUsers\TelegramUserResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTelegramUser extends ViewRecord
{
    protected static string $resource = TelegramUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
