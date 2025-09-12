<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Tool;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
#[AsTool('open_weather_map', 'Tool that queries the OpenWeatherMap API for weather information')]
#[AsTool('open_weather_forecast', 'Tool that gets weather forecast from OpenWeatherMap', method: 'getForecast')]
final readonly class OpenWeatherMap
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
        private string $units = 'metric',
        private array $options = [],
    ) {
    }

    /**
     * Get current weather information for a location.
     *
     * @param string $location location string (e.g. London,GB or London,US or just London)
     *
     * @return array{
     *     location: string,
     *     temperature: float,
     *     description: string,
     *     humidity: int,
     *     pressure: int,
     *     wind_speed: float,
     *     wind_direction: int,
     *     visibility: int,
     *     clouds: int,
     *     sunrise: string,
     *     sunset: string,
     * }|string
     */
    public function __invoke(string $location): array|string
    {
        try {
            // First, get coordinates for the location
            $coordinates = $this->getCoordinates($location);

            if (!$coordinates) {
                return "Location '{$location}' not found.";
            }

            $response = $this->httpClient->request('GET', 'https://api.openweathermap.org/data/2.5/weather', [
                'query' => array_merge($this->options, [
                    'lat' => $coordinates['lat'],
                    'lon' => $coordinates['lon'],
                    'appid' => $this->apiKey,
                    'units' => $this->units,
                ]),
            ]);

            $data = $response->toArray();

            return [
                'location' => $data['name'].', '.$data['sys']['country'],
                'temperature' => $data['main']['temp'],
                'description' => $data['weather'][0]['description'],
                'humidity' => $data['main']['humidity'],
                'pressure' => $data['main']['pressure'],
                'wind_speed' => $data['wind']['speed'] ?? 0,
                'wind_direction' => $data['wind']['deg'] ?? 0,
                'visibility' => $data['visibility'] ?? 0,
                'clouds' => $data['clouds']['all'],
                'sunrise' => date('Y-m-d H:i:s', $data['sys']['sunrise']),
                'sunset' => date('Y-m-d H:i:s', $data['sys']['sunset']),
            ];
        } catch (\Exception $e) {
            return 'Error fetching weather data: '.$e->getMessage();
        }
    }

    /**
     * Get weather forecast for a location.
     *
     * @param string $location location string (e.g. London,GB or London,US or just London)
     * @param int    $days     Number of forecast days (1-5)
     *
     * @return array<int, array{
     *     date: string,
     *     temperature_min: float,
     *     temperature_max: float,
     *     description: string,
     *     humidity: int,
     *     wind_speed: float,
     * }>|string
     */
    public function getForecast(string $location, int $days = 5): array|string
    {
        try {
            // First, get coordinates for the location
            $coordinates = $this->getCoordinates($location);

            if (!$coordinates) {
                return "Location '{$location}' not found.";
            }

            $response = $this->httpClient->request('GET', 'https://api.openweathermap.org/data/2.5/forecast', [
                'query' => array_merge($this->options, [
                    'lat' => $coordinates['lat'],
                    'lon' => $coordinates['lon'],
                    'appid' => $this->apiKey,
                    'units' => $this->units,
                    'cnt' => $days * 8, // 8 forecasts per day (every 3 hours)
                ]),
            ]);

            $data = $response->toArray();
            $forecasts = [];

            // Group forecasts by date
            $dailyForecasts = [];
            foreach ($data['list'] as $forecast) {
                $date = date('Y-m-d', $forecast['dt']);

                if (!isset($dailyForecasts[$date])) {
                    $dailyForecasts[$date] = [
                        'date' => $date,
                        'temperatures' => [],
                        'descriptions' => [],
                        'humidities' => [],
                        'wind_speeds' => [],
                    ];
                }

                $dailyForecasts[$date]['temperatures'][] = $forecast['main']['temp'];
                $dailyForecasts[$date]['descriptions'][] = $forecast['weather'][0]['description'];
                $dailyForecasts[$date]['humidities'][] = $forecast['main']['humidity'];
                $dailyForecasts[$date]['wind_speeds'][] = $forecast['wind']['speed'] ?? 0;
            }

            // Calculate daily averages and min/max
            foreach ($dailyForecasts as $date => $dayData) {
                $forecasts[] = [
                    'date' => $date,
                    'temperature_min' => min($dayData['temperatures']),
                    'temperature_max' => max($dayData['temperatures']),
                    'description' => $this->getMostCommonDescription($dayData['descriptions']),
                    'humidity' => (int) round(array_sum($dayData['humidities']) / \count($dayData['humidities'])),
                    'wind_speed' => round(array_sum($dayData['wind_speeds']) / \count($dayData['wind_speeds']), 2),
                ];
            }

            return \array_slice($forecasts, 0, $days);
        } catch (\Exception $e) {
            return 'Error fetching forecast data: '.$e->getMessage();
        }
    }

    /**
     * Get coordinates for a location.
     *
     * @return array{lat: float, lon: float}|null
     */
    private function getCoordinates(string $location): ?array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://api.openweathermap.org/geo/1.0/direct', [
                'query' => [
                    'q' => $location,
                    'limit' => 1,
                    'appid' => $this->apiKey,
                ],
            ]);

            $data = $response->toArray();

            if (empty($data)) {
                return null;
            }

            return [
                'lat' => $data[0]['lat'],
                'lon' => $data[0]['lon'],
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the most common weather description from a list.
     *
     * @param array<int, string> $descriptions
     */
    private function getMostCommonDescription(array $descriptions): string
    {
        $counts = array_count_values($descriptions);
        arsort($counts);

        return array_key_first($counts) ?? 'Unknown';
    }
}
