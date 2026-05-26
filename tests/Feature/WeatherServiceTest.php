<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\Services\WeatherService;

class WeatherServiceTest extends TestCase
{
    public function test_can_get_weather(): void
    {
        Http::fake([
            '*' => Http::response([
                'main' => [
                    'temp' => 20,
                    'feels_like' => 18,
                    'humidity' => 70,
                ],
                'wind' => [
                    'speed' => 5,
                ],
                'weather' => [
                    [
                        'description' => 'Ясно'
                    ],
                ]
            ], 200),
        ]);

        $weatherService = new WeatherService;

        $result = $weatherService->getWeather('Kazan');

        $this->assertEquals(20, $result['temp']);
    }
}