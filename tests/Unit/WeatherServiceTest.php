<?php

namespace Tests\Unit;

use App\Exceptions\CityNotFoundException;
use App\Exceptions\WeatherServiceException;
use App\Services\WeatherService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherServiceTest extends TestCase
{
    private WeatherService $weather;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->weather = new WeatherService();
    }

    /**
     * @return array<string, mixed>
     */
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

    public function test_fetch_maps_the_provider_payload(): void
    {
        Http::fake([
            '*' => Http::response($this->fakeOpenWeatherMapResponse(), 200),
        ]);

        $data = $this->weather->fetch('London');

        $this->assertSame('London', $data->city);
        $this->assertSame('GB', $data->country);
        $this->assertSame(18.5, $data->temperature);
        $this->assertSame(17.2, $data->feelsLike);
        $this->assertSame('scattered clouds', $data->description);
        $this->assertSame(72, $data->humidity);
        $this->assertSame(4.12, $data->windSpeed);
        $this->assertNotSame('', $data->timestamp);
        $this->assertSame('external', $data->source);

        Http::assertSent(fn ($request) => $request['q'] === 'London'
            && $request['appid'] === 'test-api-key'
            && $request['units'] === 'metric');
    }

    public function test_fetch_throws_when_the_city_is_not_found(): void
    {
        Http::fake([
            '*' => Http::response(['cod' => '404', 'message' => 'city not found'], 404),
        ]);

        $this->expectException(CityNotFoundException::class);

        $this->weather->fetch('Nowhereville');
    }

    public function test_fetch_throws_when_the_api_key_is_blank(): void
    {
        config(['services.openweathermap.key' => '   ']);

        try {
            $this->weather->fetch('London');
            $this->fail('Expected WeatherServiceException was not thrown.');
        } catch (WeatherServiceException) {
            Http::assertNothingSent();
        }
    }

    public function test_fetch_throws_when_the_provider_fails(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'Internal Server Error'], 500),
        ]);

        $this->expectException(WeatherServiceException::class);

        $this->weather->fetch('London');
    }

    public function test_fetch_throws_when_the_provider_is_unreachable(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $this->expectException(WeatherServiceException::class);

        $this->weather->fetch('London');
    }

    public function test_fetch_throws_when_the_provider_payload_is_unexpected(): void
    {
        Http::fake([
            '*' => Http::response(['name' => 'London'], 200),
        ]);

        $this->expectException(WeatherServiceException::class);

        $this->weather->fetch('London');
    }

    public function test_fetch_cached_fetches_once_and_labels_source(): void
    {
        Http::fake([
            '*' => Http::response($this->fakeOpenWeatherMapResponse(), 200),
        ]);

        $first = $this->weather->fetchCached('London');
        $second = $this->weather->fetchCached('London');

        $this->assertSame('external', $first->source);
        $this->assertSame('cache', $second->source);
        $this->assertSame($first->timestamp, $second->timestamp);
        Http::assertSentCount(1);
    }
}
