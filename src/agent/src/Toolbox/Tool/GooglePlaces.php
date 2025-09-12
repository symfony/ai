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
#[AsTool('google_places_search', 'Tool that searches Google Places')]
#[AsTool('google_places_get_details', 'Tool that gets Google Places details', method: 'getDetails')]
#[AsTool('google_places_nearby_search', 'Tool that searches nearby places', method: 'nearbySearch')]
#[AsTool('google_places_autocomplete', 'Tool that provides place autocomplete', method: 'autocomplete')]
#[AsTool('google_places_geocode', 'Tool that geocodes addresses', method: 'geocode')]
#[AsTool('google_places_reverse_geocode', 'Tool that reverse geocodes coordinates', method: 'reverseGeocode')]
#[AsTool('google_places_photo', 'Tool that gets Google Places photos', method: 'getPhoto')]
#[AsTool('google_places_reviews', 'Tool that gets Google Places reviews', method: 'getReviews')]
final readonly class GooglePlaces
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://maps.googleapis.com/maps/api/place',
        private array $options = [],
    ) {
    }

    /**
     * Search Google Places.
     *
     * @param string $query    Search query
     * @param string $location Location (lat,lng)
     * @param int    $radius   Search radius in meters
     * @param string $type     Place type filter
     * @param string $language Response language
     * @param string $region   Region bias
     *
     * @return array{
     *     results: array<int, array{
     *         placeId: string,
     *         name: string,
     *         vicinity: string,
     *         geometry: array{
     *             location: array{
     *                 lat: float,
     *                 lng: float,
     *             },
     *         },
     *         rating: float,
     *         priceLevel: int,
     *         types: array<int, string>,
     *         photos: array<int, array{
     *             photoReference: string,
     *             height: int,
     *             width: int,
     *         }>,
     *         openingHours: array{
     *             openNow: bool,
     *             periods: array<int, array{
     *                 open: array{
     *                     day: int,
     *                     time: string,
     *                 },
     *                 close: array{
     *                     day: int,
     *                     time: string,
     *                 },
     *             }>,
     *         },
     *     }>,
     *     status: string,
     *     nextPageToken: string,
     * }
     */
    public function __invoke(
        string $query,
        string $location = '',
        int $radius = 50000,
        string $type = '',
        string $language = 'en',
        string $region = '',
    ): array {
        try {
            $params = [
                'key' => $this->apiKey,
                'query' => $query,
                'language' => $language,
            ];

            if ($location) {
                $params['location'] = $location;
                $params['radius'] = $radius;
            }

            if ($type) {
                $params['type'] = $type;
            }

            if ($region) {
                $params['region'] = $region;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/textsearch/json", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'results' => array_map(fn ($result) => [
                    'placeId' => $result['place_id'],
                    'name' => $result['name'],
                    'vicinity' => $result['vicinity'] ?? '',
                    'geometry' => [
                        'location' => [
                            'lat' => $result['geometry']['location']['lat'],
                            'lng' => $result['geometry']['location']['lng'],
                        ],
                    ],
                    'rating' => $result['rating'] ?? 0.0,
                    'priceLevel' => $result['price_level'] ?? 0,
                    'types' => $result['types'],
                    'photos' => array_map(fn ($photo) => [
                        'photoReference' => $photo['photo_reference'],
                        'height' => $photo['height'],
                        'width' => $photo['width'],
                    ], $result['photos'] ?? []),
                    'openingHours' => [
                        'openNow' => $result['opening_hours']['open_now'] ?? false,
                        'periods' => array_map(fn ($period) => [
                            'open' => [
                                'day' => $period['open']['day'],
                                'time' => $period['open']['time'],
                            ],
                            'close' => [
                                'day' => $period['close']['day'] ?? 0,
                                'time' => $period['close']['time'] ?? '',
                            ],
                        ], $result['opening_hours']['periods'] ?? []),
                    ],
                ], $data['results'] ?? []),
                'status' => $data['status'],
                'nextPageToken' => $data['next_page_token'] ?? '',
            ];
        } catch (\Exception $e) {
            return [
                'results' => [],
                'status' => 'ERROR',
                'nextPageToken' => '',
            ];
        }
    }

    /**
     * Get Google Places details.
     *
     * @param string        $placeId  Place ID
     * @param array<string> $fields   Fields to return
     * @param string        $language Response language
     *
     * @return array{
     *     placeId: string,
     *     name: string,
     *     formattedAddress: string,
     *     geometry: array{
     *         location: array{
     *             lat: float,
     *             lng: float,
     *         },
     *     },
     *     rating: float,
     *     userRatingsTotal: int,
     *     priceLevel: int,
     *     phoneNumber: string,
     *     website: string,
     *     openingHours: array{
     *         openNow: bool,
     *         periods: array<int, array{
     *             open: array{
     *                 day: int,
     *                 time: string,
     *             },
     *             close: array{
     *                 day: int,
     *                 time: string,
     *             },
     *         }>,
     *         weekdayText: array<int, string>,
     *     },
     *     reviews: array<int, array{
     *         authorName: string,
     *         rating: int,
     *         text: string,
     *         time: int,
     *     }>,
     *     photos: array<int, array{
     *         photoReference: string,
     *         height: int,
     *         width: int,
     *     }>,
     *     status: string,
     * }
     */
    public function getDetails(
        string $placeId,
        array $fields = ['place_id', 'name', 'formatted_address', 'geometry', 'rating', 'reviews', 'photos'],
        string $language = 'en',
    ): array {
        try {
            $params = [
                'key' => $this->apiKey,
                'place_id' => $placeId,
                'fields' => implode(',', $fields),
                'language' => $language,
            ];

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/details/json", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();
            $result = $data['result'] ?? [];

            return [
                'placeId' => $result['place_id'] ?? $placeId,
                'name' => $result['name'] ?? '',
                'formattedAddress' => $result['formatted_address'] ?? '',
                'geometry' => [
                    'location' => [
                        'lat' => $result['geometry']['location']['lat'] ?? 0.0,
                        'lng' => $result['geometry']['location']['lng'] ?? 0.0,
                    ],
                ],
                'rating' => $result['rating'] ?? 0.0,
                'userRatingsTotal' => $result['user_ratings_total'] ?? 0,
                'priceLevel' => $result['price_level'] ?? 0,
                'phoneNumber' => $result['formatted_phone_number'] ?? '',
                'website' => $result['website'] ?? '',
                'openingHours' => [
                    'openNow' => $result['opening_hours']['open_now'] ?? false,
                    'periods' => array_map(fn ($period) => [
                        'open' => [
                            'day' => $period['open']['day'],
                            'time' => $period['open']['time'],
                        ],
                        'close' => [
                            'day' => $period['close']['day'] ?? 0,
                            'time' => $period['close']['time'] ?? '',
                        ],
                    ], $result['opening_hours']['periods'] ?? []),
                    'weekdayText' => $result['opening_hours']['weekday_text'] ?? [],
                ],
                'reviews' => array_map(fn ($review) => [
                    'authorName' => $review['author_name'],
                    'rating' => $review['rating'],
                    'text' => $review['text'],
                    'time' => $review['time'],
                ], $result['reviews'] ?? []),
                'photos' => array_map(fn ($photo) => [
                    'photoReference' => $photo['photo_reference'],
                    'height' => $photo['height'],
                    'width' => $photo['width'],
                ], $result['photos'] ?? []),
                'status' => $data['status'],
            ];
        } catch (\Exception $e) {
            return [
                'placeId' => $placeId,
                'name' => '',
                'formattedAddress' => '',
                'geometry' => ['location' => ['lat' => 0.0, 'lng' => 0.0]],
                'rating' => 0.0,
                'userRatingsTotal' => 0,
                'priceLevel' => 0,
                'phoneNumber' => '',
                'website' => '',
                'openingHours' => ['openNow' => false, 'periods' => [], 'weekdayText' => []],
                'reviews' => [],
                'photos' => [],
                'status' => 'ERROR',
            ];
        }
    }

    /**
     * Search nearby places.
     *
     * @param string $location Location (lat,lng)
     * @param int    $radius   Search radius in meters
     * @param string $type     Place type filter
     * @param string $keyword  Keyword filter
     * @param string $language Response language
     *
     * @return array{
     *     results: array<int, array{
     *         placeId: string,
     *         name: string,
     *         vicinity: string,
     *         geometry: array{
     *             location: array{
     *                 lat: float,
     *                 lng: float,
     *             },
     *         },
     *         rating: float,
     *         priceLevel: int,
     *         types: array<int, string>,
     *     }>,
     *     status: string,
     *     nextPageToken: string,
     * }
     */
    public function nearbySearch(
        string $location,
        int $radius = 50000,
        string $type = '',
        string $keyword = '',
        string $language = 'en',
    ): array {
        try {
            $params = [
                'key' => $this->apiKey,
                'location' => $location,
                'radius' => $radius,
                'language' => $language,
            ];

            if ($type) {
                $params['type'] = $type;
            }

            if ($keyword) {
                $params['keyword'] = $keyword;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/nearbysearch/json", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'results' => array_map(fn ($result) => [
                    'placeId' => $result['place_id'],
                    'name' => $result['name'],
                    'vicinity' => $result['vicinity'] ?? '',
                    'geometry' => [
                        'location' => [
                            'lat' => $result['geometry']['location']['lat'],
                            'lng' => $result['geometry']['location']['lng'],
                        ],
                    ],
                    'rating' => $result['rating'] ?? 0.0,
                    'priceLevel' => $result['price_level'] ?? 0,
                    'types' => $result['types'],
                ], $data['results'] ?? []),
                'status' => $data['status'],
                'nextPageToken' => $data['next_page_token'] ?? '',
            ];
        } catch (\Exception $e) {
            return [
                'results' => [],
                'status' => 'ERROR',
                'nextPageToken' => '',
            ];
        }
    }

    /**
     * Provide place autocomplete.
     *
     * @param string        $input    Input text
     * @param string        $location Location bias (lat,lng)
     * @param int           $radius   Radius bias in meters
     * @param string        $language Response language
     * @param array<string> $types    Place types filter
     *
     * @return array{
     *     predictions: array<int, array{
     *         description: string,
     *         placeId: string,
     *         types: array<int, string>,
     *         terms: array<int, array{
     *             offset: int,
     *             value: string,
     *         }>,
     *         matchedSubstrings: array<int, array{
     *             length: int,
     *             offset: int,
     *         }>,
     *     }>,
     *     status: string,
     * }
     */
    public function autocomplete(
        string $input,
        string $location = '',
        int $radius = 200000,
        string $language = 'en',
        array $types = [],
    ): array {
        try {
            $params = [
                'key' => $this->apiKey,
                'input' => $input,
                'language' => $language,
            ];

            if ($location) {
                $params['location'] = $location;
                $params['radius'] = $radius;
            }

            if (!empty($types)) {
                $params['types'] = implode('|', $types);
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/autocomplete/json", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'predictions' => array_map(fn ($prediction) => [
                    'description' => $prediction['description'],
                    'placeId' => $prediction['place_id'],
                    'types' => $prediction['types'],
                    'terms' => array_map(fn ($term) => [
                        'offset' => $term['offset'],
                        'value' => $term['value'],
                    ], $prediction['terms']),
                    'matchedSubstrings' => array_map(fn ($match) => [
                        'length' => $match['length'],
                        'offset' => $match['offset'],
                    ], $prediction['matched_substrings']),
                ], $data['predictions'] ?? []),
                'status' => $data['status'],
            ];
        } catch (\Exception $e) {
            return [
                'predictions' => [],
                'status' => 'ERROR',
            ];
        }
    }

    /**
     * Geocode addresses.
     *
     * @param string $address  Address to geocode
     * @param string $language Response language
     * @param string $region   Region bias
     *
     * @return array{
     *     results: array<int, array{
     *         formattedAddress: string,
     *         geometry: array{
     *             location: array{
     *                 lat: float,
     *                 lng: float,
     *             },
     *         },
     *         types: array<int, string>,
     *         placeId: string,
     *     }>,
     *     status: string,
     * }
     */
    public function geocode(
        string $address,
        string $language = 'en',
        string $region = '',
    ): array {
        try {
            $params = [
                'key' => $this->apiKey,
                'address' => $address,
                'language' => $language,
            ];

            if ($region) {
                $params['region'] = $region;
            }

            $response = $this->httpClient->request('GET', 'https://maps.googleapis.com/maps/api/geocode/json', [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'results' => array_map(fn ($result) => [
                    'formattedAddress' => $result['formatted_address'],
                    'geometry' => [
                        'location' => [
                            'lat' => $result['geometry']['location']['lat'],
                            'lng' => $result['geometry']['location']['lng'],
                        ],
                    ],
                    'types' => $result['types'],
                    'placeId' => $result['place_id'],
                ], $data['results'] ?? []),
                'status' => $data['status'],
            ];
        } catch (\Exception $e) {
            return [
                'results' => [],
                'status' => 'ERROR',
            ];
        }
    }

    /**
     * Reverse geocode coordinates.
     *
     * @param string        $latlng      Coordinates (lat,lng)
     * @param string        $language    Response language
     * @param array<string> $resultTypes Result type filter
     *
     * @return array{
     *     results: array<int, array{
     *         formattedAddress: string,
     *         geometry: array{
     *             location: array{
     *                 lat: float,
     *                 lng: float,
     *             },
     *         },
     *         types: array<int, string>,
     *         placeId: string,
     *         addressComponents: array<int, array{
     *             longName: string,
     *             shortName: string,
     *             types: array<int, string>,
     *         }>,
     *     }>,
     *     status: string,
     * }
     */
    public function reverseGeocode(
        string $latlng,
        string $language = 'en',
        array $resultTypes = [],
    ): array {
        try {
            $params = [
                'key' => $this->apiKey,
                'latlng' => $latlng,
                'language' => $language,
            ];

            if (!empty($resultTypes)) {
                $params['result_type'] = implode('|', $resultTypes);
            }

            $response = $this->httpClient->request('GET', 'https://maps.googleapis.com/maps/api/geocode/json', [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'results' => array_map(fn ($result) => [
                    'formattedAddress' => $result['formatted_address'],
                    'geometry' => [
                        'location' => [
                            'lat' => $result['geometry']['location']['lat'],
                            'lng' => $result['geometry']['location']['lng'],
                        ],
                    ],
                    'types' => $result['types'],
                    'placeId' => $result['place_id'],
                    'addressComponents' => array_map(fn ($component) => [
                        'longName' => $component['long_name'],
                        'shortName' => $component['short_name'],
                        'types' => $component['types'],
                    ], $result['address_components']),
                ], $data['results'] ?? []),
                'status' => $data['status'],
            ];
        } catch (\Exception $e) {
            return [
                'results' => [],
                'status' => 'ERROR',
            ];
        }
    }

    /**
     * Get Google Places photo.
     *
     * @param string $photoReference Photo reference
     * @param int    $maxWidth       Maximum width
     * @param int    $maxHeight      Maximum height
     *
     * @return array{
     *     photoUrl: string,
     *     width: int,
     *     height: int,
     *     attribution: string,
     * }
     */
    public function getPhoto(
        string $photoReference,
        int $maxWidth = 400,
        int $maxHeight = 400,
    ): array {
        try {
            $params = [
                'key' => $this->apiKey,
                'photoreference' => $photoReference,
                'maxwidth' => $maxWidth,
                'maxheight' => $maxHeight,
            ];

            $photoUrl = "{$this->baseUrl}/photo?".http_build_query(array_merge($this->options, $params));

            return [
                'photoUrl' => $photoUrl,
                'width' => $maxWidth,
                'height' => $maxHeight,
                'attribution' => 'Photo provided by Google Places API',
            ];
        } catch (\Exception $e) {
            return [
                'photoUrl' => '',
                'width' => 0,
                'height' => 0,
                'attribution' => '',
            ];
        }
    }

    /**
     * Get Google Places reviews.
     *
     * @param string $placeId  Place ID
     * @param string $language Response language
     *
     * @return array{
     *     reviews: array<int, array{
     *         authorName: string,
     *         authorUrl: string,
     *         language: string,
     *         profilePhotoUrl: string,
     *         rating: int,
     *         relativeTimeDescription: string,
     *         text: string,
     *         time: int,
     *     }>,
     *     status: string,
     * }
     */
    public function getReviews(
        string $placeId,
        string $language = 'en',
    ): array {
        try {
            $details = $this->getDetails($placeId, ['reviews'], $language);

            return [
                'reviews' => $details['reviews'],
                'status' => $details['status'],
            ];
        } catch (\Exception $e) {
            return [
                'reviews' => [],
                'status' => 'ERROR',
            ];
        }
    }
}
