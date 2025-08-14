<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Tool\OpenMeteo;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

#[CoversClass(OpenMeteo::class)]
final class OpenMeteoTest extends TestCase
{
    public function testCurrent()
    {
        $result = JsonMockResponse::fromFile(__DIR__.'/fixtures/openmeteo-current.json');
        $httpClient = new MockHttpClient($result);

        $openMeteo = new OpenMeteo($httpClient);

        $actual = $openMeteo->current(52.52, 13.42);
        $expected = [
            'weather' => 'Overcast',
            'time' => '2024-12-21T01:15',
            'temperature' => '2.6°C',
            'wind_speed' => '10.7km/h',
        ];

        $this->assertSame($expected, $actual);
    }

    public function testForecast()
    {
        $result = JsonMockResponse::fromFile(__DIR__.'/fixtures/openmeteo-forecast.json');
        $httpClient = new MockHttpClient($result);

        $openMeteo = new OpenMeteo($httpClient);

        $actual = $openMeteo->forecast(52.52, 13.42, 3);
        $expected = [
            [
                'weather' => 'Light Rain',
                'time' => '2024-12-21',
                'temperature_min' => '2°C',
                'temperature_max' => '6°C',
            ],
            [
                'weather' => 'Light Showers',
                'time' => '2024-12-22',
                'temperature_min' => '1.3°C',
                'temperature_max' => '6.4°C',
            ],
            [
                'weather' => 'Light Snow Showers',
                'time' => '2024-12-23',
                'temperature_min' => '1.5°C',
                'temperature_max' => '4.1°C',
            ],
        ];

        $this->assertSame($expected, $actual);
    }
}
