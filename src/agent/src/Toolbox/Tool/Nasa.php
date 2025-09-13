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
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
#[AsTool('nasa_images', 'Tool that searches NASA Image and Video Library')]
#[AsTool('nasa_apod', 'Tool that gets NASA Astronomy Picture of the Day', method: 'getApod')]
#[AsTool('nasa_asteroids', 'Tool that gets information about near-Earth asteroids', method: 'getAsteroids')]
#[AsTool('nasa_earth_imagery', 'Tool that gets satellite imagery of Earth', method: 'getEarthImagery')]
final readonly class Nasa
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private ?string $apiKey = null,
        private array $options = [],
    ) {
    }

    /**
     * Search NASA Image and Video Library.
     *
     * @param string $query      Search query for NASA images and videos
     * @param int    $maxResults Maximum number of results to return
     * @param string $mediaType  Media type filter: image, video, audio
     * @param string $yearStart  Start year for date range filter
     * @param string $yearEnd    End year for date range filter
     *
     * @return array<int, array{
     *     nasa_id: string,
     *     title: string,
     *     description: string,
     *     center: string,
     *     date_created: string,
     *     media_type: string,
     *     photographer: string,
     *     keywords: array<int, string>,
     *     thumbnail_url: string,
     *     large_url: string,
     *     href: string,
     * }>
     */
    public function __invoke(
        #[With(maximum: 500)]
        string $query,
        int $maxResults = 20,
        string $mediaType = 'image',
        string $yearStart = '',
        string $yearEnd = '',
    ): array {
        try {
            $params = [
                'q' => $query,
                'media_type' => $mediaType,
                'page_size' => $maxResults,
            ];

            if ($this->apiKey) {
                $params['api_key'] = $this->apiKey;
            }

            if ($yearStart) {
                $params['year_start'] = $yearStart;
            }
            if ($yearEnd) {
                $params['year_end'] = $yearEnd;
            }

            $response = $this->httpClient->request('GET', 'https://images-api.nasa.gov/search', [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (!isset($data['collection']['items'])) {
                return [];
            }

            $results = [];
            foreach ($data['collection']['items'] as $item) {
                $metadata = $item['data'][0] ?? [];
                $links = $item['links'] ?? [];

                // Find thumbnail and large image URLs
                $thumbnailUrl = '';
                $largeUrl = '';
                $href = '';

                foreach ($links as $link) {
                    if ('image' === $link['render']) {
                        if (str_contains($link['href'], 'thumb')) {
                            $thumbnailUrl = $link['href'];
                        } else {
                            $largeUrl = $link['href'];
                        }
                        $href = $link['href'];
                    }
                }

                $results[] = [
                    'nasa_id' => $metadata['nasa_id'] ?? '',
                    'title' => $metadata['title'] ?? '',
                    'description' => $metadata['description'] ?? '',
                    'center' => $metadata['center'] ?? '',
                    'date_created' => $metadata['date_created'] ?? '',
                    'media_type' => $metadata['media_type'] ?? '',
                    'photographer' => $metadata['photographer'] ?? '',
                    'keywords' => $metadata['keywords'] ?? [],
                    'thumbnail_url' => $thumbnailUrl,
                    'large_url' => $largeUrl,
                    'href' => $href,
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return [
                [
                    'nasa_id' => 'error',
                    'title' => 'Search Error',
                    'description' => 'Unable to search NASA images: '.$e->getMessage(),
                    'center' => '',
                    'date_created' => '',
                    'media_type' => '',
                    'photographer' => '',
                    'keywords' => [],
                    'thumbnail_url' => '',
                    'large_url' => '',
                    'href' => '',
                ],
            ];
        }
    }

    /**
     * Get NASA Astronomy Picture of the Day.
     *
     * @param string $date Specific date (YYYY-MM-DD) or leave empty for today
     *
     * @return array{
     *     date: string,
     *     explanation: string,
     *     hdurl: string,
     *     media_type: string,
     *     service_version: string,
     *     title: string,
     *     url: string,
     *     copyright: string,
     * }|string
     */
    public function getApod(string $date = ''): array|string
    {
        try {
            $params = [];
            if ($this->apiKey) {
                $params['api_key'] = $this->apiKey;
            }
            if ($date) {
                $params['date'] = $date;
            }

            $response = $this->httpClient->request('GET', 'https://api.nasa.gov/planetary/apod', [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'date' => $data['date'],
                'explanation' => $data['explanation'],
                'hdurl' => $data['hdurl'] ?? '',
                'media_type' => $data['media_type'],
                'service_version' => $data['service_version'] ?? '',
                'title' => $data['title'],
                'url' => $data['url'],
                'copyright' => $data['copyright'] ?? '',
            ];
        } catch (\Exception $e) {
            return 'Error getting APOD: '.$e->getMessage();
        }
    }

    /**
     * Get information about near-Earth asteroids.
     *
     * @param string $startDate Start date for asteroid data (YYYY-MM-DD)
     * @param string $endDate   End date for asteroid data (YYYY-MM-DD)
     *
     * @return array<int, array{
     *     name: string,
     *     nasa_jpl_url: string,
     *     absolute_magnitude_h: float,
     *     estimated_diameter: array{
     *         kilometers: array{estimated_diameter_min: float, estimated_diameter_max: float},
     *         meters: array{estimated_diameter_min: float, estimated_diameter_max: float},
     *     },
     *     is_potentially_hazardous_asteroid: bool,
     *     close_approach_data: array<int, array{
     *         close_approach_date: string,
     *         relative_velocity: array{kilometers_per_second: string, kilometers_per_hour: string},
     *         miss_distance: array{astronomical: string, lunar: string, kilometers: string},
     *         orbiting_body: string,
     *     }>,
     * }>|string
     */
    public function getAsteroids(string $startDate, string $endDate = ''): array|string
    {
        try {
            if (!$this->apiKey) {
                return 'NASA API key required for asteroid data';
            }

            $params = [
                'start_date' => $startDate,
                'api_key' => $this->apiKey,
            ];

            if ($endDate) {
                $params['end_date'] = $endDate;
            }

            $response = $this->httpClient->request('GET', 'https://api.nasa.gov/neo/rest/v1/feed', [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (!isset($data['near_earth_objects'])) {
                return [];
            }

            $asteroids = [];
            foreach ($data['near_earth_objects'] as $date => $dayAsteroids) {
                foreach ($dayAsteroids as $asteroid) {
                    $asteroids[] = [
                        'name' => $asteroid['name'],
                        'nasa_jpl_url' => $asteroid['nasa_jpl_url'],
                        'absolute_magnitude_h' => $asteroid['absolute_magnitude_h'],
                        'estimated_diameter' => $asteroid['estimated_diameter'],
                        'is_potentially_hazardous_asteroid' => $asteroid['is_potentially_hazardous_asteroid'],
                        'close_approach_data' => $asteroid['close_approach_data'],
                    ];
                }
            }

            return $asteroids;
        } catch (\Exception $e) {
            return 'Error getting asteroid data: '.$e->getMessage();
        }
    }

    /**
     * Get satellite imagery of Earth.
     *
     * @param float  $lat  Latitude
     * @param float  $lon  Longitude
     * @param string $date Date for imagery (YYYY-MM-DD)
     *
     * @return array{
     *     date: string,
     *     id: string,
     *     resource: array{
     *         dataset: string,
     *         planet: string,
     *     },
     *     service_version: string,
     *     url: string,
     * }|string
     */
    public function getEarthImagery(float $lat, float $lon, string $date = ''): array|string
    {
        try {
            if (!$this->apiKey) {
                return 'NASA API key required for Earth imagery';
            }

            $params = [
                'lat' => $lat,
                'lon' => $lon,
                'api_key' => $this->apiKey,
            ];

            if ($date) {
                $params['date'] = $date;
            }

            $response = $this->httpClient->request('GET', 'https://api.nasa.gov/planetary/earth/imagery', [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'date' => $data['date'],
                'id' => $data['id'],
                'resource' => $data['resource'],
                'service_version' => $data['service_version'],
                'url' => $data['url'],
            ];
        } catch (\Exception $e) {
            return 'Error getting Earth imagery: '.$e->getMessage();
        }
    }

    /**
     * Get Mars weather data.
     *
     * @return array{
     *     sol_keys: array<int, string>,
     *     validity_checks: array<string, mixed>,
     *     latest_weather: array<string, mixed>,
     * }|string
     */
    public function getMarsWeather(): array|string
    {
        try {
            $response = $this->httpClient->request('GET', 'https://api.nasa.gov/insight_weather/', [
                'query' => array_merge($this->options, [
                    'api_key' => $this->apiKey,
                    'feedtype' => 'json',
                    'ver' => '1.0',
                ]),
            ]);

            return $response->toArray();
        } catch (\Exception $e) {
            return 'Error getting Mars weather: '.$e->getMessage();
        }
    }
}
