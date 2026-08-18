<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherControllerTest extends TestCase
{
    private function fakeOpenWeatherMapResponse(): array
    {
        return [
            'name' => 'London',
            'sys' => ['country' => 'GB'],
            'main' => [
                'temp' => 18.5,
                'feels_like' => 17.2,
                'humidity' => 72,
            ],
            'weather' => [
                ['description' => 'scattered clouds'],
            ],
            'wind' => ['speed' => 4.12],
        ];
    }

    public function test_it_returns_weather_data_for_a_valid_city(): void
    {
        Http::fake([
            '*' => Http::response($this->fakeOpenWeatherMapResponse(), 200),
        ]);

        $response = $this->getJson('/api/weather/London');

        $response->assertOk()->assertJson([
            'city' => 'London',
            'country' => 'GB',
            'temperature' => 18.5,
            'feels_like' => 17.2,
            'description' => 'scattered clouds',
            'humidity' => 72,
            'wind_speed' => 4.12,
            'source' => 'external',
        ]);

        $this->assertArrayHasKey('timestamp', $response->json());

        Http::assertSent(fn($request) => $request['q'] === 'London' && $request['appid'] === 'test-api-key');
    }

    public function test_it_returns_404_when_city_is_not_found(): void
    {
        Http::fake([
            '*' => Http::response(['cod' => '404', 'message' => 'city not found'], 404),
        ]);

        $response = $this->getJson('/api/weather/Nowhereville');

        $response->assertStatus(404)->assertJsonStructure(['error']);
    }

    public function test_it_returns_a_gateway_error_when_the_api_key_is_blank(): void
    {
        config(['services.openweathermap.key' => '   ']);

        $response = $this->getJson('/api/weather/London');

        $response->assertStatus(502)->assertJsonStructure(['error']);
        Http::assertNothingSent();
    }

    public function test_it_returns_a_gateway_error_when_the_provider_fails(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'Internal Server Error'], 500),
        ]);

        $response = $this->getJson('/api/weather/London');

        $response->assertStatus(502)->assertJsonStructure(['error']);
    }

    public function test_it_returns_a_gateway_error_when_the_provider_is_unreachable(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $response = $this->getJson('/api/weather/London');

        $response->assertStatus(502)->assertJsonStructure(['error']);
    }

    public function test_it_rejects_an_invalid_city_name(): void
    {
        $response = $this->getJson('/api/weather/12345');

        $response->assertStatus(422)->assertJsonValidationErrors(['city']);
    }

    public function test_it_accepts_a_city_name_with_a_period(): void
    {
        Http::fake([
            '*' => Http::response([
                'name' => 'St. Louis',
                'main' => ['temp' => 22.0],
                'weather' => [
                    ['description' => 'clear sky'],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/weather/' . rawurlencode('St. Louis'));

        $response->assertOk()->assertJson([
            'city' => 'St. Louis',
            'source' => 'external',
        ]);
    }

    public function test_cached_endpoint_serves_external_data_on_first_request_and_cache_on_second(): void
    {
        Http::fake([
            '*' => Http::response($this->fakeOpenWeatherMapResponse(), 200),
        ]);

        $first = $this->getJson('/api/weather/London/cached');
        $first->assertOk()->assertJson(['source' => 'external']);

        $second = $this->getJson('/api/weather/London/cached');
        $second->assertOk()->assertJson(['source' => 'cache']);

        // Only one real HTTP call should have been made; the second request was served from cache.
        Http::assertSentCount(1);

        // The cached payload (city/temperature/description/timestamp) must match the original.
        $this->assertSame($first->json('timestamp'), $second->json('timestamp'));
    }
}
