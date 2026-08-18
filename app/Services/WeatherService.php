<?php

namespace App\Services;

use App\Data\WeatherData;
use App\Exceptions\CityNotFoundException;
use App\Exceptions\WeatherServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WeatherService
{
    /**
     * Fetch and normalize current weather data for a city from OpenWeatherMap.
     *
     * Always calls the provider. Does not read or write the cache.
     *
     * @throws CityNotFoundException
     * @throws WeatherServiceException
     */
    public function fetch(string $city): WeatherData
    {
        $response = $this->request($city);
        $this->ensureSuccessful($response, $city);

        return $this->normalize($response, $city);
    }

    /**
     * Return cached weather for a city, or fetch and store it for 10 minutes.
     *
     * `source` is `cache` when this call reused a stored payload, otherwise `external`.
     *
     * @throws CityNotFoundException
     * @throws WeatherServiceException
     */
    public function fetchCached(string $city): WeatherData
    {
        $cacheKey = 'weather:' . Str::lower($city);
        $fetched = false;

        $payload = Cache::remember(
            $cacheKey,
            now()->addMinutes(10),
            function () use ($city, &$fetched) {
                $fetched = true;

                return $this->fetch($city)->toArray();
            },
        );

        if (!$fetched) {
            $payload['source'] = 'cache';
        }

        return WeatherData::fromArray($payload);
    }

    /**
     * Get the API key from the configuration.
     *
     * @throws WeatherServiceException
     */
    private function apiKey(): string
    {
        $apiKey = trim((string) config('services.openweathermap.key'));

        if ($apiKey === '') {
            throw new WeatherServiceException((string) __('errors.weather.missing_api_key'));
        }

        return $apiKey;
    }

    /**
     * Send a request to the OpenWeatherMap API.
     *
     * @throws WeatherServiceException
     */
    private function request(string $city): Response
    {
        try {
            return Http::timeout(5)->get((string) config('services.openweathermap.base_url'), [
                'q' => $city,
                'appid' => $this->apiKey(),
                'units' => 'metric',
            ]);
        } catch (ConnectionException $e) {
            throw new WeatherServiceException((string) __('errors.weather.provider_unreachable'), previous: $e);
        }
    }

    /**
     * Ensure the response is successful.
     *
     * @throws CityNotFoundException
     * @throws WeatherServiceException
     */
    private function ensureSuccessful(Response $response, string $city): void
    {
        if ($response->status() === 404) {
            throw new CityNotFoundException((string) __('errors.weather.city_not_found', ['city' => $city]));
        }

        if ($response->failed()) {
            $message = $response->json('message') ?? "HTTP {$response->status()}";

            throw new WeatherServiceException((string) __('errors.weather.provider_error', ['message' => $message]));
        }
    }

    /**
     * Normalize the response.
     *
     * @throws WeatherServiceException
     */
    private function normalize(Response $response, string $city): WeatherData
    {
        $city = $response->json('name') ?? $city;
        $temperature = $response->json('main.temp');
        $description = $response->json('weather.0.description');
        $country = $response->json('sys.country');
        $feelsLike = $response->json('main.feels_like');
        $humidity = $response->json('main.humidity');
        $windSpeed = $response->json('wind.speed');

        if ($temperature === null || $description === null) {
            throw new WeatherServiceException((string) __('errors.weather.unexpected_response'));
        }

        return new WeatherData(
            city: (string) $city,
            country: $country !== null ? (string) $country : null,
            temperature: (float) $temperature,
            feelsLike: $feelsLike !== null ? (float) $feelsLike : null,
            description: (string) $description,
            humidity: $humidity !== null ? (int) $humidity : null,
            windSpeed: $windSpeed !== null ? (float) $windSpeed : null,
            timestamp: now()->toIso8601String(),
            source: 'external',
        );
    }
}
