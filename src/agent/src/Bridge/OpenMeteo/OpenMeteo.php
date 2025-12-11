<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\OpenMeteo;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsTool(name: 'weather_current', description: 'get current weather for a location', method: 'current')]
#[AsTool(name: 'weather_forecast', description: 'get weather forecast for a location', method: 'forecast')]
final class OpenMeteo
{
    private const WMO_CODES = [
        0 => 'Clear',
        1 => 'Mostly Clear',
        2 => 'Partly Cloudy',
        3 => 'Overcast',
        45 => 'Fog',
        48 => 'Icy Fog',
        51 => 'Light Drizzle',
        53 => 'Drizzle',
        55 => 'Heavy Drizzle',
        56 => 'Light Freezing Drizzle',
        57 => 'Freezing Drizzle',
        61 => 'Light Rain',
        63 => 'Rain',
        65 => 'Heavy Rain',
        66 => 'Light Freezing Rain',
        67 => 'Freezing Rain',
        71 => 'Light Snow',
        73 => 'Snow',
        75 => 'Heavy Snow',
        77 => 'Snow Grains',
        80 => 'Light Showers',
        81 => 'Showers',
        82 => 'Heavy Showers',
        85 => 'Light Snow Showers',
        86 => 'Snow Showers',
        95 => 'Thunderstorm',
        96 => 'Light Thunderstorm with Hail',
        99 => 'Thunderstorm with Hail',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @param float $latitude  the latitude of the location
     * @param float $longitude the longitude of the location
     *
     * @return array{
     *     weather: string,
     *     time: string,
     *     temperature: string,
     *     wind_speed: string,
     * }
     */
    public function current(float $latitude, float $longitude): array
    {
        $result = $this->httpClient->request('GET', 'https://api.open-meteo.com/v1/forecast', [
            'query' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'current' => 'weather_code,temperature_2m,wind_speed_10m',
            ],
        ]);

        $data = $result->toArray();

        return [
            'weather' => self::WMO_CODES[$data['current']['weather_code']] ?? 'Unknown',
            'time' => $data['current']['time'],
            'temperature' => $data['current']['temperature_2m'].$data['current_units']['temperature_2m'],
            'wind_speed' => $data['current']['wind_speed_10m'].$data['current_units']['wind_speed_10m'],
        ];
    }

    /**
     * @param float $latitude  the latitude of the location
     * @param float $longitude the longitude of the location
     * @param int   $days      the number of days to forecast
     *
     * @return array{
     *     weather: string,
     *     time: string,
     *     temperature_min: string,
     *     temperature_max: string,
     * }[]
     */
    public function forecast(
        float $latitude,
        float $longitude,
        #[With(minimum: 1, maximum: 16)]
        int $days = 7,
    ): array {
        $result = $this->httpClient->request('GET', 'https://api.open-meteo.com/v1/forecast', [
            'query' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'daily' => 'weather_code,temperature_2m_max,temperature_2m_min',
                'forecast_days' => $days,
            ],
        ]);

        $data = $result->toArray();
        $forecast = [];
        for ($i = 0; $i < $days; ++$i) {
            $forecast[] = [
                'weather' => self::WMO_CODES[$data['daily']['weather_code'][$i]] ?? 'Unknown',
                'time' => $data['daily']['time'][$i],
                'temperature_min' => $data['daily']['temperature_2m_min'][$i].$data['daily_units']['temperature_2m_min'],
                'temperature_max' => $data['daily']['temperature_2m_max'][$i].$data['daily_units']['temperature_2m_max'],
            ];
        }

        return $forecast;
    }
}
