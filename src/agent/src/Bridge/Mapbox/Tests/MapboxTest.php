<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Mapbox\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\Mapbox\Mapbox;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class MapboxTest extends TestCase
{
    public function testGeocodeWithSingleResult()
    {
        $result = JsonMockResponse::fromFile(__DIR__.'/Fixtures/geocode-single.json');
        $httpClient = new MockHttpClient($result);

        $mapbox = new Mapbox($httpClient, 'test_token');

        $actual = $mapbox->geocode('Brandenburg Gate, Berlin');
        $expected = [
            'results' => [
                [
                    'address' => 'Brandenburg Gate, Pariser Platz, 10117 Berlin, Germany',
                    'coordinates' => [
                        'longitude' => 13.377704,
                        'latitude' => 52.516275,
                    ],
                    'relevance' => 1.0,
                    'place_type' => ['poi'],
                ],
            ],
            'count' => 1,
        ];

        $this->assertEquals($expected, $actual);
    }

    public function testGeocodeWithMultipleResults()
    {
        $result = JsonMockResponse::fromFile(__DIR__.'/Fixtures/geocode-multiple.json');
        $httpClient = new MockHttpClient($result);

        $mapbox = new Mapbox($httpClient, 'test_token');

        $actual = $mapbox->geocode('Paris', 2);
        $expected = [
            'results' => [
                [
                    'address' => 'Paris, France',
                    'coordinates' => [
                        'longitude' => 2.3522,
                        'latitude' => 48.8566,
                    ],
                    'relevance' => 1.0,
                    'place_type' => ['place'],
                ],
                [
                    'address' => 'Paris, Texas, United States',
                    'coordinates' => [
                        'longitude' => -95.5555,
                        'latitude' => 33.6609,
                    ],
                    'relevance' => 0.8,
                    'place_type' => ['place'],
                ],
            ],
            'count' => 2,
        ];

        $this->assertEquals($expected, $actual);
    }

    public function testGeocodeWithNoResults()
    {
        $result = JsonMockResponse::fromFile(__DIR__.'/Fixtures/geocode-empty.json');
        $httpClient = new MockHttpClient($result);

        $mapbox = new Mapbox($httpClient, 'test_token');

        $actual = $mapbox->geocode('nonexistent location xyz123');
        $expected = [
            'results' => [],
            'count' => 0,
        ];

        $this->assertEquals($expected, $actual);
    }

    public function testReverseGeocodeWithValidCoordinates()
    {
        $result = JsonMockResponse::fromFile(__DIR__.'/Fixtures/reverse-geocode.json');
        $httpClient = new MockHttpClient($result);

        $mapbox = new Mapbox($httpClient, 'test_token');

        $actual = $mapbox->reverseGeocode(-73.985131, 40.758895);
        $expected = [
            'results' => [
                [
                    'address' => 'Times Square, New York, NY 10036, United States',
                    'coordinates' => [
                        'longitude' => -73.985131,
                        'latitude' => 40.758895,
                    ],
                    'place_type' => ['address'],
                    'context' => [
                        [
                            'id' => 'place.12345',
                            'text' => 'New York',
                            'type' => 'place',
                        ],
                        [
                            'id' => 'region.6789',
                            'text' => 'New York',
                            'type' => 'region',
                        ],
                        [
                            'id' => 'country.54321',
                            'text' => 'United States',
                            'type' => 'country',
                        ],
                    ],
                ],
            ],
            'count' => 1,
        ];

        $this->assertEquals($expected, $actual);
    }

    public function testReverseGeocodeWithNoResults()
    {
        $result = JsonMockResponse::fromFile(__DIR__.'/Fixtures/reverse-geocode-empty.json');
        $httpClient = new MockHttpClient($result);

        $mapbox = new Mapbox($httpClient, 'test_token');

        $actual = $mapbox->reverseGeocode(0.0, 0.0);
        $expected = [
            'results' => [],
            'count' => 0,
        ];

        $this->assertEquals($expected, $actual);
    }
}
