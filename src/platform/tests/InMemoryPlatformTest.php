<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\AI\Platform\Vector\Vector;

class InMemoryPlatformTest extends TestCase
{
    public function testPlatformInvokeWithFixedResult()
    {
        $platform = new InMemoryPlatform('Mocked result');
        $result = $platform->invoke('test', 'input');

        $this->assertSame('Mocked result', $result->asText());
        $this->assertSame('Mocked result', $result->getResult()->getContent());
        $this->assertSame(['text' => 'Mocked result'], $result->getRawResult()->getData());
    }

    public function testPlatformInvokeWithCallableResult()
    {
        $platform = new InMemoryPlatform(function (Model $model, $input) {
            return strtoupper((string) $input);
        });

        $result = $platform->invoke('test', 'dynamic text');

        $this->assertSame('DYNAMIC TEXT', $result->asText());
    }

    public function testPlatformInvokeWithVectorResultResponse()
    {
        $platform = new InMemoryPlatform(
            fn () => new VectorResult(new Vector([0.1, 0.1, 0.5]))
        );

        $result = $platform->invoke('test', 'dynamic text');

        $this->assertEquals([0.1, 0.1, 0.5], $result->asVectors()[0]->getData());
    }
}
