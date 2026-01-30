<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\TransformersPhp\Tests;

use Codewithkyrian\Transformers\Pipelines\Pipeline;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\TransformersPhp\PipelineExecution;

/**
 * @author Muhammad Elhwawshy <m.elhwawshy@gmail.com>
 */
final class PipelineExecutionTest extends TestCase
{
    public function testInvokesPipelineWithCorrectArguments()
    {
        $input = 'How many continents are there in the world?';
        $options = ['maxNewTokens' => 256, 'doSample' => true, 'repetitionPenalty' => 1.6];

        $result = ['generated_text' => 'There are generally considered to be seven continents.'];

        $pipeline = $this->createMock(Pipeline::class);
        $pipeline->expects($this->once())
            ->method('__invoke')
            ->with($this->callback(static fn (...$args) => $args === [$input, ...$options]))
            ->willReturn($result);

        $this->assertSame($result, (new PipelineExecution($pipeline, $input, $options))->getResult());
    }
}
