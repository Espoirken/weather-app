# Weather App

A Laravel API that returns current weather for a city from [OpenWeatherMap](https://openweathermap.org/current). There are two routes: one that always hits the provider, and one that caches the result for 10 minutes.

## Endpoints

| Method | URL | Behavior |
|---|---|---|
| GET | `/api/weather/{city}` | Always fetches from OpenWeatherMap. `source` is `external`. |
| GET | `/api/weather/{city}/cached` | Returns a cached payload when one exists (10 minutes). `source` is `cache` on a hit, `external` on a miss. |

Example (`GET /api/weather/London`):

```json
{
  "city": "London",
  "country": "GB",
  "temperature": 18.5,
  "feels_like": 17.2,
  "description": "scattered clouds",
  "humidity": 72,
  "wind_speed": 4.12,
  "timestamp": "2026-08-18T10:00:00+00:00",
  "source": "external"
}
```

`temperature` and `feels_like` are Celsius. `wind_speed` is meters per second. `humidity` is a percent. `timestamp` is when this app last fetched the data, not a forecast time. `country`, `feels_like`, `humidity`, and `wind_speed` are `null` when the provider omits them.

### Errors

| Status | When | Shape |
|---|---|---|
| 422 | `{city}` is empty or not letters / spaces / hyphens / apostrophes / periods | Laravel validation (`message` + `errors.city`) |
| 404 | OpenWeatherMap does not know the city | `{"error": "..."}` |
| 502 | Timeout, provider error, unexpected payload, or blank API key | `{"error": "..."}` |

Provider messages come from `lang/en/errors.php`.

## Run

PHP 8.3+ and Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Add a free OpenWeatherMap key to `.env` ([sign up](https://home.openweathermap.org/users/sign_up)):

```
OPENWEATHERMAP_API_KEY=your_real_key_here
```

Then:

```bash
php artisan serve
```

```bash
curl http://localhost:8000/api/weather/London
curl http://localhost:8000/api/weather/London/cached
```

The second `/cached` call within 10 minutes should return the same `timestamp` with `"source": "cache"`.

## Tests

```bash
php artisan test
```

The suite fakes OpenWeatherMap with `Http::fake()`, so it does not need a real key or network. `phpunit.xml` sets `OPENWEATHERMAP_API_KEY=test-api-key`.

- Feature tests (`tests/Feature/WeatherControllerTest.php`) hit the HTTP API: statuses, JSON shape, validation, and `/cached`.
- Unit tests (`tests/Unit/WeatherServiceTest.php`) call `WeatherService` directly: mapping, typed exceptions (including an unexpected 200 payload), and cache source labels.

## Approach

Request flow: **Form Request → controller → service → API Resource**.

- `WeatherRequest` trims and validates `{city}` before the controller runs.
- `WeatherController` only chooses `fetch()` or `fetchCached()` and returns `WeatherResource`.
- `WeatherService` calls OpenWeatherMap, maps HTTP/connection failures to `CityNotFoundException` or `WeatherServiceException`, and returns a `WeatherData` object. `fetch()` always sets `source` to `external`. `fetchCached()` uses `Cache::remember()` (key `weather:{city}`, 10 minutes) and returns `fromCache()` on a hit so `source` is `cache`.
- `WeatherResource` maps `WeatherData` to JSON. Wrapping is off, so the payload is flat rather than `{ "data": { ... } }`.
- Exceptions are rendered in `bootstrap/app.php` (404 / 502). The controller does not catch them.
