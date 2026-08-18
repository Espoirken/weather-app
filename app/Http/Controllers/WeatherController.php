<?php

namespace App\Http\Controllers;

use App\Http\Requests\WeatherRequest;
use App\Http\Resources\WeatherResource;
use App\Services\WeatherService;

class WeatherController extends Controller
{
    public function __construct(private readonly WeatherService $weather)
    {
    }

    /**
     * Return live weather for a city from the external provider.
     */
    public function show(WeatherRequest $request): WeatherResource
    {
        return new WeatherResource(
            $this->weather->fetch((string) $request->validated('city')),
        );
    }

    /**
     * Return weather for a city, using a 10-minute cache when available.
     */
    public function cached(WeatherRequest $request): WeatherResource
    {
        return new WeatherResource(
            $this->weather->fetchCached((string) $request->validated('city')),
        );
    }
}
