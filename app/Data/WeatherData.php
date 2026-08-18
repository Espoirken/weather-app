<?php

namespace App\Data;

readonly class WeatherData
{
    /**
     * @param  'cache'|'external'  $source
     */
    public function __construct(
        public string $city,
        public ?string $country,
        public float $temperature,
        public ?float $feelsLike,
        public string $description,
        public ?int $humidity,
        public ?float $windSpeed,
        public string $timestamp,
        public string $source,
    ) {
    }

    /**
     * @return array<string, float|int|string|null>
     */
    public function toArray(): array
    {
        return [
            'city' => $this->city,
            'country' => $this->country,
            'temperature' => $this->temperature,
            'feels_like' => $this->feelsLike,
            'description' => $this->description,
            'humidity' => $this->humidity,
            'wind_speed' => $this->windSpeed,
            'timestamp' => $this->timestamp,
            'source' => $this->source,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            city: (string) $data['city'],
            country: isset($data['country']) ? (string) $data['country'] : null,
            temperature: (float) $data['temperature'],
            feelsLike: isset($data['feels_like']) ? (float) $data['feels_like'] : null,
            description: (string) $data['description'],
            humidity: isset($data['humidity']) ? (int) $data['humidity'] : null,
            windSpeed: isset($data['wind_speed']) ? (float) $data['wind_speed'] : null,
            timestamp: (string) $data['timestamp'],
            source: ($data['source'] ?? '') === 'cache' ? 'cache' : 'external',
        );
    }
}
