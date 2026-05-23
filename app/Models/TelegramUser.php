<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramUser extends Model
{
    protected $fillable = [
        'chat_id', 'username', 'first_name', 'last_name', 'state'
    ];

    public function tasks(): HasMany
    {
        return $this->hasMany(UserTask::class);
    }
}