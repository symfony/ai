<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Profiler\Model;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Model\CollectorData;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class CollectorDataTest extends TestCase
{
    public function testConstructorSetsProperties()
    {
        $data = ['method' => 'GET', 'path' => '/test'];
        $summary = ['requests' => 1, 'status' => 200];

        $collectorData = new CollectorData(
            name: 'request',
            data: $data,
            summary: $summary
        );

        $this->assertSame('request', $collectorData->name);
        $this->assertSame($data, $collectorData->data);
        $this->assertSame($summary, $collectorData->summary);
    }

    public function testDataCanBeEmpty()
    {
        $collectorData = new CollectorData(
            name: 'request',
            data: [],
            summary: []
        );

        $this->assertIsArray($collectorData->data);
        $this->assertEmpty($collectorData->data);
    }

    public function testSummaryCanBeEmpty()
    {
        $collectorData = new CollectorData(
            name: 'request',
            data: ['method' => 'GET'],
            summary: []
        );

        $this->assertIsArray($collectorData->summary);
        $this->assertEmpty($collectorData->summary);
    }

    public function testPropertiesAreReadonly()
    {
        $collectorData = new CollectorData(
            name: 'request',
            data: [],
            summary: []
        );

        $reflection = new \ReflectionClass($collectorData);

        $nameProperty = $reflection->getProperty('name');
        $this->assertTrue($nameProperty->isReadOnly());

        $dataProperty = $reflection->getProperty('data');
        $this->assertTrue($dataProperty->isReadOnly());

        $summaryProperty = $reflection->getProperty('summary');
        $this->assertTrue($summaryProperty->isReadOnly());
    }
}
