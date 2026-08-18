<?php

namespace App\Http\Resources;

use App\Data\WeatherData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * HTTP representation of weather data.
 *
 * @property-read WeatherData $resource
 */
class WeatherResource extends JsonResource
{
    /**
     * Keep a flat payload ({city, temperature, ...}) instead of {data: {...}}.
     */
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
