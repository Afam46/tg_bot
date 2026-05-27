<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\Services\AiService;

class AiServiceTest extends TestCase
{
    public function test_can_get_ai_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Привет! Я AI ассистент'
                        ]
                    ]
                ]
            ], 200),
        ]);

        $aiService = new AiService;

        $result = $aiService->ask('Привет');

        $this->assertEquals(
            'Привет! Я AI ассистент',
            $result
        );
    }
}