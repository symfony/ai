<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Workflow;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Workflow\WorkflowError;

final class WorkflowErrorTest extends TestCase
{
    public function testConstruction(): void
    {
        $exception = new \RuntimeException('Original error');
        $error = new WorkflowError(
            message: 'Test error',
            step: 'processing',
            code: 500,
            previous: $exception,
            context: ['key' => 'value']
        );

        $this->assertSame('Test error', $error->getMessage());
        $this->assertSame('processing', $error->getStep());
        $this->assertSame(500, $error->getCode());
        $this->assertSame($exception, $error->getPrevious());
        $this->assertSame(['key' => 'value'], $error->getContext());
        $this->assertInstanceOf(\DateTimeInterface::class, $error->getOccurredAt());
    }

    public function testToArray(): void
    {
        $error = new WorkflowError(
            message: 'Test error',
            step: 'processing',
            code: 500,
            context: ['key' => 'value']
        );

        $array = $error->toArray();

        $this->assertSame('Test error', $array['message']);
        $this->assertSame('processing', $array['step']);
        $this->assertSame(500, $array['code']);
        $this->assertSame(['key' => 'value'], $array['context']);
        $this->assertArrayHasKey('occurredAt', $array);
    }

    public function testDefaultValues(): void
    {
        $error = new WorkflowError('Test error', 'step');

        $this->assertSame(0, $error->getCode());
        $this->assertNull($error->getPrevious());
        $this->assertEmpty($error->getContext());
    }
}
