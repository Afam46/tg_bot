<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WeatherService
{
    public function getWeather(string $city)
    {
        $apiKey = config('app.weather_api_key');

        $url = "https://api.openweathermap.org/data/2.5/weather?q={$city}&appid={$apiKey}&units=metric&lang=ru";

        $response = Http::timeout(10)->get($url);

        if ($response->status() === 404) {
            throw new \Exception("Город не найден");
        }

        if (!$response->successful()) {
            throw new \Exception("Ошибка API погоды");
        }

        $data = $response->json();

        return [
            'temp' => $data['main']['temp'],
            'feels_like' => $data['main']['feels_like'],
            'humidity' => $data['main']['humidity'],
            'description' => $data['weather'][0]['description'],
            'wind_speed' => $data['wind']['speed'],
        ];
    }
}