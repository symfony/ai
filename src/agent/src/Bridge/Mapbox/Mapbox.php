<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Mapbox;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
#[AsTool(name: 'geocode', description: 'Convert addresses to coordinates using Mapbox Geocoding API', method: 'geocode')]
#[AsTool(name: 'reverse_geocode', description: 'Convert coordinates to addresses using Mapbox Reverse Geocoding API', method: 'reverseGeocode')]
final class Mapbox
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $accessToken,
    ) {
    }

    /**
     * @param string $address The address to geocode (e.g., "1600 Pennsylvania Ave, Washington DC")
     * @param int    $limit   Maximum number of results to return (1-10)
     *
     * @return array{
     *     results: array<array{
     *         address: string,
     *         coordinates: array{longitude: float, latitude: float},
     *         relevance: float,
     *         place_type: string[]
     *     }>,
     *     count: int
     * }
     */
    public function geocode(
        string $address,
        #[With(minimum: 1, maximum: 10)]
        int $limit = 1,
    ): array {
        $response = $this->httpClient->request('GET', 'https://api.mapbox.com/geocoding/v5/mapbox.places/'.urlencode($address).'.json', [
            'query' => [
                'access_token' => $this->accessToken,
                'limit' => $limit,
            ],
        ]);

        $data = $response->toArray();

        if (!isset($data['features']) || [] === $data['features']) {
            return [
                'results' => [],
                'count' => 0,
            ];
        }

        $results = [];
        foreach ($data['features'] as $feature) {
            $center = $feature['center'] ?? [0.0, 0.0];
            $results[] = [
                'address' => $feature['place_name'] ?? '',
                'coordinates' => [
                    'longitude' => $center[0] ?? 0.0,
                    'latitude' => $center[1] ?? 0.0,
                ],
                'relevance' => $feature['relevance'] ?? 0.0,
                'place_type' => $feature['place_type'] ?? [],
            ];
        }

        return [
            'results' => $results,
            'count' => \count($results),
        ];
    }

    /**
     * @param float $longitude The longitude coordinate
     * @param float $latitude  The latitude coordinate
     * @param int   $limit     Maximum number of results to return (1-5)
     *
     * @return array{
     *     results: array<array{
     *         address: string,
     *         coordinates: array{longitude: float, latitude: float},
     *         place_type: string[],
     *         context: array<array{id: string, text: string}>
     *     }>,
     *     count: int
     * }
     */
    public function reverseGeocode(
        float $longitude,
        float $latitude,
        #[With(minimum: 1, maximum: 5)]
        int $limit = 1,
    ): array {
        $response = $this->httpClient->request('GET', 'https://api.mapbox.com/search/geocode/v6/reverse', [
            'query' => [
                'longitude' => $longitude,
                'latitude' => $latitude,
                'access_token' => $this->accessToken,
                'limit' => $limit,
            ],
        ]);

        $data = $response->toArray();

        if (!isset($data['features']) || [] === $data['features']) {
            return [
                'results' => [],
                'count' => 0,
            ];
        }

        $results = [];
        foreach ($data['features'] as $feature) {
            $properties = $feature['properties'] ?? [];
            $coordinates = $properties['coordinates'] ?? [];

            $context = [];
            if (isset($properties['context'])) {
                foreach ($properties['context'] as $key => $contextItem) {
                    if (\is_array($contextItem) && isset($contextItem['name'])) {
                        $context[] = [
                            'id' => $contextItem['id'] ?? $contextItem['mapbox_id'] ?? '',
                            'text' => $contextItem['name'],
                            'type' => $key,
                        ];
                    }
                }
            }

            $results[] = [
                'address' => $properties['place_formatted'] ?? $properties['name'] ?? '',
                'coordinates' => [
                    'longitude' => $coordinates['longitude'] ?? 0.0,
                    'latitude' => $coordinates['latitude'] ?? 0.0,
                ],
                'place_type' => [$properties['feature_type'] ?? 'unknown'],
                'context' => $context,
            ];
        }

        return [
            'results' => $results,
            'count' => \count($results),
        ];
    }
}
