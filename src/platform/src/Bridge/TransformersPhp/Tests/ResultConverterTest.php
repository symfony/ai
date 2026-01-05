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

use Codewithkyrian\Transformers\Pipelines\Task;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\TransformersPhp\ResultConverter;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\VectorResult;

/**
 * @author Muhammad Elhwawshy <m.elhwawshy@gmail.com>
 */
final class ResultConverterTest extends TestCase
{
    public function testConvertText2TextGeneration()
    {
        $result = new InMemoryRawResult([
            ['generated_text' => 'Some response'],
        ]);

        $textResult = (new ResultConverter())->convert($result, ['task' => Task::Text2TextGeneration]);
        $this->assertInstanceOf(TextResult::class, $textResult);

        $this->assertSame('Some response', $textResult->getContent());
    }

    public function testConvertFeatureExtraction()
    {
        $result = new InMemoryRawResult([
            [0.1, 0.2, 0.3],
            [0.4, 0.5, 0.6],
        ]);

        $vectorResult = (new ResultConverter())->convert($result, ['task' => Task::FeatureExtraction]);
        $this->assertInstanceOf(VectorResult::class, $vectorResult);

        $convertedContent = $vectorResult->getContent();
        $this->assertCount(2, $convertedContent);
        $this->assertSame([0.1, 0.2, 0.3], $convertedContent[0]->getData());
        $this->assertSame([0.4, 0.5, 0.6], $convertedContent[1]->getData());
    }
}
