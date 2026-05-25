<?php

namespace App\Filament\Resources\UserTasks\Pages;

use App\Filament\Resources\UserTasks\UserTaskResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUserTask extends CreateRecord
{
    protected static string $resource = UserTaskResource::class;
}
