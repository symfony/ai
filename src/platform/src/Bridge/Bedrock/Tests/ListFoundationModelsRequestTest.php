<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Bedrock\ListFoundationModelsRequest;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ListFoundationModelsRequestTest extends TestCase
{
    public function testRequestWithoutFilters()
    {
        $request = new ListFoundationModelsRequest();
        $httpRequest = $request->request();

        $this->assertSame('GET', $httpRequest->getMethod());
        $this->assertSame('/foundation-models', $httpRequest->getUri());
        $this->assertSame([], $httpRequest->getQuery());
    }

    public function testRequestWithProviderFilter()
    {
        $request = new ListFoundationModelsRequest(['byProvider' => 'Anthropic']);
        $httpRequest = $request->request();

        $this->assertSame(['byProvider' => 'Anthropic'], $httpRequest->getQuery());
    }

    public function testRequestWithAllFilters()
    {
        $request = new ListFoundationModelsRequest([
            'byProvider' => 'Amazon',
            'byOutputModality' => 'TEXT',
            'byInferenceType' => 'ON_DEMAND',
        ]);
        $httpRequest = $request->request();

        $this->assertSame([
            'byProvider' => 'Amazon',
            'byOutputModality' => 'TEXT',
            'byInferenceType' => 'ON_DEMAND',
        ], $httpRequest->getQuery());
    }

    public function testRequestWithRegion()
    {
        $request = new ListFoundationModelsRequest(['@region' => 'eu-west-1']);

        $this->assertSame('eu-west-1', $request->getRegion());
    }
}
