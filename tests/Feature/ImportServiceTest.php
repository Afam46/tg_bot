<?php

namespace Tests\Feature;

use Tests\TestCase;
use Mockery;
use Illuminate\Support\Facades\Queue;
use App\Jobs\ImportTasksJob;
use App\Models\TelegramUser;
use App\Services\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_import_file(): void
    {
        Queue::fake();

        $user = TelegramUser::create([
            'chat_id' => 1,
            'telegram_id' => 123456,
            'username' => 'afamka',
        ]);

        $documentMock = Mockery::mock();
        $documentMock->shouldReceive('getFileId')->andReturn('file_123');

        $messageMock = Mockery::mock();
        $messageMock->shouldReceive('getDocument')->andReturn($documentMock);

        $fileMock = Mockery::mock();
        $fileMock->shouldReceive('getFilePath')->andReturn('documents/test.xlsx');

        $telegramMock = Mockery::mock();
        $telegramMock->shouldReceive('getFile')->once()->with(['file_id'=>'file_123'])->andReturn($fileMock);

        $importService = Mockery::mock(ImportService::class)->makePartial()->shouldAllowMockingProtectedMethods();

        $importService->shouldReceive('downloadFile')->once();

        $importService->importFile($telegramMock,$messageMock,$user,1);

        Queue::assertPushed(ImportTasksJob::class);
    }
}