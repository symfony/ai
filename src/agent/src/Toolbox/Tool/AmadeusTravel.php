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
#[AsTool('amadeus_flight_search', 'Tool that searches for flights using Amadeus API')]
#[AsTool('amadeus_hotel_search', 'Tool that searches for hotels using Amadeus API', method: 'searchHotels')]
#[AsTool('amadeus_airport_search', 'Tool that searches for airports using Amadeus API', method: 'searchAirports')]
#[AsTool('amadeus_city_search', 'Tool that searches for cities using Amadeus API', method: 'searchCities')]
final readonly class AmadeusTravel
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
        #[\SensitiveParameter] private string $apiSecret,
        private string $environment = 'test', // 'test' or 'production'
        private array $options = [],
    ) {
    }

    /**
     * Search for flights using Amadeus API.
     *
     * @param string $origin        Origin airport IATA code (e.g., 'LAX')
     * @param string $destination   Destination airport IATA code (e.g., 'NYC')
     * @param string $departureDate Departure date in YYYY-MM-DD format
     * @param int    $adults        Number of adult passengers
     * @param int    $children      Number of child passengers
     * @param int    $infants       Number of infant passengers
     * @param string $travelClass   Travel class: ECONOMY, PREMIUM_ECONOMY, BUSINESS, FIRST
     * @param int    $maxResults    Maximum number of results to return
     *
     * @return array<int, array{
     *     price: array{total: string, currency: string},
     *     segments: array<int, array{
     *         departure: array{at: string, iataCode: string},
     *         arrival: array{at: string, iataCode: string},
     *         flightNumber: string,
     *         carrier: string,
     *         aircraft: array{code: string, name: string},
     *         duration: string,
     *     }>,
     *     duration: string,
     *     stops: int,
     * }>
     */
    public function __invoke(
        string $origin,
        string $destination,
        string $departureDate,
        int $adults = 1,
        int $children = 0,
        int $infants = 0,
        string $travelClass = 'ECONOMY',
        int $maxResults = 10,
    ): array {
        try {
            // Get access token first
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return [
                    [
                        'price' => ['total' => '0', 'currency' => 'USD'],
                        'segments' => [],
                        'duration' => '0',
                        'stops' => 0,
                    ],
                ];
            }

            $baseUrl = 'production' === $this->environment
                ? 'https://api.amadeus.com'
                : 'https://test.api.amadeus.com';

            $params = [
                'originLocationCode' => $origin,
                'destinationLocationCode' => $destination,
                'departureDate' => $departureDate,
                'adults' => $adults,
                'travelClass' => $travelClass,
                'max' => $maxResults,
            ];

            if ($children > 0) {
                $params['children'] = $children;
            }
            if ($infants > 0) {
                $params['infants'] = $infants;
            }

            $response = $this->httpClient->request('GET', $baseUrl.'/v2/shopping/flight-offers', [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                    'Accept' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (!isset($data['data'])) {
                return [];
            }

            $results = [];
            foreach ($data['data'] as $offer) {
                $flightData = [
                    'price' => [
                        'total' => $offer['price']['total'],
                        'currency' => $offer['price']['currency'],
                    ],
                    'segments' => [],
                    'duration' => $offer['itineraries'][0]['duration'] ?? '',
                    'stops' => \count($offer['itineraries'][0]['segments']) - 1,
                ];

                foreach ($offer['itineraries'][0]['segments'] as $segment) {
                    $flightData['segments'][] = [
                        'departure' => [
                            'at' => $segment['departure']['at'],
                            'iataCode' => $segment['departure']['iataCode'],
                        ],
                        'arrival' => [
                            'at' => $segment['arrival']['at'],
                            'iataCode' => $segment['arrival']['iataCode'],
                        ],
                        'flightNumber' => $segment['carrierCode'].$segment['number'],
                        'carrier' => $segment['carrierCode'],
                        'aircraft' => [
                            'code' => $segment['aircraft']['code'] ?? '',
                            'name' => $segment['aircraft']['name'] ?? '',
                        ],
                        'duration' => $segment['duration'],
                    ];
                }

                $results[] = $flightData;
            }

            return $results;
        } catch (\Exception $e) {
            return [
                [
                    'price' => ['total' => '0', 'currency' => 'USD'],
                    'segments' => [],
                    'duration' => '0',
                    'stops' => 0,
                ],
            ];
        }
    }

    /**
     * Search for hotels using Amadeus API.
     *
     * @param string $cityCode     City IATA code
     * @param string $checkInDate  Check-in date in YYYY-MM-DD format
     * @param string $checkOutDate Check-out date in YYYY-MM-DD format
     * @param int    $adults       Number of adults
     * @param int    $rooms        Number of rooms
     *
     * @return array<int, array{
     *     hotel_id: string,
     *     name: string,
     *     rating: int,
     *     price: array{total: string, currency: string},
     *     address: string,
     *     coordinates: array{latitude: float, longitude: float},
     *     amenities: array<int, string>,
     *     description: string,
     * }>
     */
    public function searchHotels(
        string $cityCode,
        string $checkInDate,
        string $checkOutDate,
        int $adults = 2,
        int $rooms = 1,
    ): array {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return [];
            }

            $baseUrl = 'production' === $this->environment
                ? 'https://api.amadeus.com'
                : 'https://test.api.amadeus.com';

            $response = $this->httpClient->request('GET', $baseUrl.'/v3/shopping/hotel-offers', [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                    'Accept' => 'application/json',
                ],
                'query' => array_merge($this->options, [
                    'cityCode' => $cityCode,
                    'checkInDate' => $checkInDate,
                    'checkOutDate' => $checkOutDate,
                    'adults' => $adults,
                    'roomQuantity' => $rooms,
                ]),
            ]);

            $data = $response->toArray();

            if (!isset($data['data'])) {
                return [];
            }

            $results = [];
            foreach ($data['data'] as $hotel) {
                $results[] = [
                    'hotel_id' => $hotel['hotel']['hotelId'],
                    'name' => $hotel['hotel']['name'],
                    'rating' => $hotel['hotel']['rating'] ?? 0,
                    'price' => [
                        'total' => $hotel['offers'][0]['price']['total'] ?? '0',
                        'currency' => $hotel['offers'][0]['price']['currency'] ?? 'USD',
                    ],
                    'address' => $hotel['hotel']['address']['lines'][0] ?? '',
                    'coordinates' => [
                        'latitude' => $hotel['hotel']['latitude'] ?? 0.0,
                        'longitude' => $hotel['hotel']['longitude'] ?? 0.0,
                    ],
                    'amenities' => $hotel['hotel']['amenities'] ?? [],
                    'description' => $hotel['hotel']['description']['text'] ?? '',
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Search for airports using Amadeus API.
     *
     * @param string $keyword Airport name or city name
     *
     * @return array<int, array{
     *     iata_code: string,
     *     name: string,
     *     city: string,
     *     country: string,
     *     coordinates: array{latitude: float, longitude: float},
     * }>
     */
    public function searchAirports(string $keyword): array
    {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return [];
            }

            $baseUrl = 'production' === $this->environment
                ? 'https://api.amadeus.com'
                : 'https://test.api.amadeus.com';

            $response = $this->httpClient->request('GET', $baseUrl.'/v1/reference-data/locations', [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                    'Accept' => 'application/json',
                ],
                'query' => array_merge($this->options, [
                    'subType' => 'AIRPORT',
                    'keyword' => $keyword,
                ]),
            ]);

            $data = $response->toArray();

            if (!isset($data['data'])) {
                return [];
            }

            $results = [];
            foreach ($data['data'] as $airport) {
                $results[] = [
                    'iata_code' => $airport['iataCode'],
                    'name' => $airport['name'],
                    'city' => $airport['address']['cityName'] ?? '',
                    'country' => $airport['address']['countryName'] ?? '',
                    'coordinates' => [
                        'latitude' => $airport['geoCode']['latitude'] ?? 0.0,
                        'longitude' => $airport['geoCode']['longitude'] ?? 0.0,
                    ],
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Search for cities using Amadeus API.
     *
     * @param string $keyword City name
     *
     * @return array<int, array{
     *     iata_code: string,
     *     name: string,
     *     country: string,
     *     coordinates: array{latitude: float, longitude: float},
     * }>
     */
    public function searchCities(string $keyword): array
    {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return [];
            }

            $baseUrl = 'production' === $this->environment
                ? 'https://api.amadeus.com'
                : 'https://test.api.amadeus.com';

            $response = $this->httpClient->request('GET', $baseUrl.'/v1/reference-data/locations', [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                    'Accept' => 'application/json',
                ],
                'query' => array_merge($this->options, [
                    'subType' => 'CITY',
                    'keyword' => $keyword,
                ]),
            ]);

            $data = $response->toArray();

            if (!isset($data['data'])) {
                return [];
            }

            $results = [];
            foreach ($data['data'] as $city) {
                $results[] = [
                    'iata_code' => $city['iataCode'],
                    'name' => $city['name'],
                    'country' => $city['address']['countryName'] ?? '',
                    'coordinates' => [
                        'latitude' => $city['geoCode']['latitude'] ?? 0.0,
                        'longitude' => $city['geoCode']['longitude'] ?? 0.0,
                    ],
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get access token from Amadeus API.
     */
    private function getAccessToken(): ?string
    {
        try {
            $baseUrl = 'production' === $this->environment
                ? 'https://api.amadeus.com'
                : 'https://test.api.amadeus.com';

            $response = $this->httpClient->request('POST', $baseUrl.'/v1/security/oauth2/token', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => http_build_query([
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->apiKey,
                    'client_secret' => $this->apiSecret,
                ]),
            ]);

            $data = $response->toArray();

            return $data['access_token'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
