<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Telegram\Bot\Api;

class SetWebhook extends Command
{
    protected $signature = 'bot:set-webhook {url}';
    protected $description = 'Set Telegram webhook';

    public function handle()
    {
        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        $url = $this->argument('url');
        
        $telegram->setWebhook(['url' => $url]);
        
        $this->info("Webhook set to: {$url}");
    }
}