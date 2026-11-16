<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Tests\Classification;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\Classification\ResultConverter;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\Classification;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\ClassificationResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResultConverterTest extends TestCase
{
    #[TestDox('fromArray creates instance with Classification objects')]
    public function testFromArray()
    {
        $data = [
            ['label' => 'positive', 'score' => 0.95],
            ['label' => 'negative', 'score' => 0.03],
            ['label' => 'neutral', 'score' => 0.02],
        ];

        $result = $this->convert($data);

        $this->assertCount(3, $result->getClassifications());

        $this->assertSame('positive', $result->getClassifications()[0]->getLabel());
        $this->assertSame(0.95, $result->getClassifications()[0]->getScore());

        $this->assertSame('negative', $result->getClassifications()[1]->getLabel());
        $this->assertSame(0.03, $result->getClassifications()[1]->getScore());

        $this->assertSame('neutral', $result->getClassifications()[2]->getLabel());
        $this->assertSame(0.02, $result->getClassifications()[2]->getScore());
    }

    #[TestDox('fromArray with empty data creates empty result')]
    public function testFromArrayWithEmptyData()
    {
        $result = $this->convert([]);

        $this->assertCount(0, $result->getClassifications());
        $this->assertSame([], $result->getClassifications());
    }

    #[TestDox('fromArray with single classification')]
    public function testFromArrayWithSingleClassification()
    {
        $data = [
            ['label' => 'confident', 'score' => 0.99],
        ];

        $result = $this->convert($data);

        $this->assertCount(1, $result->getClassifications());
        $this->assertInstanceOf(Classification::class, $result->getClassifications()[0]);
        $this->assertSame('confident', $result->getClassifications()[0]->getLabel());
        $this->assertSame(0.99, $result->getClassifications()[0]->getScore());
    }

    #[TestDox('fromArray preserves order of classifications')]
    public function testFromArrayPreservesOrder()
    {
        $data = [
            ['label' => 'first', 'score' => 0.5],
            ['label' => 'second', 'score' => 0.3],
            ['label' => 'third', 'score' => 0.2],
        ];

        $result = $this->convert($data);

        $this->assertSame('first', $result->getClassifications()[0]->getLabel());
        $this->assertSame('second', $result->getClassifications()[1]->getLabel());
        $this->assertSame('third', $result->getClassifications()[2]->getLabel());
    }

    /**
     * @param array{label: string, score: float} $classification
     */
    #[TestDox('fromArray handles various label formats')]
    #[TestWith([['label' => '', 'score' => 0.5]])]
    #[TestWith([['label' => 'UPPERCASE', 'score' => 0.5]])]
    #[TestWith([['label' => 'with-dashes', 'score' => 0.5]])]
    #[TestWith([['label' => 'with_underscores', 'score' => 0.5]])]
    #[TestWith([['label' => 'with spaces', 'score' => 0.5]])]
    #[TestWith([['label' => '123numeric', 'score' => 0.5]])]
    public function testFromArrayWithVariousLabelFormats(array $classification)
    {
        $result = $this->convert([$classification]);

        $this->assertCount(1, $result->getClassifications());
        $this->assertSame($classification['label'], $result->getClassifications()[0]->getLabel());
        $this->assertSame($classification['score'], $result->getClassifications()[0]->getScore());
    }

    /**
     * @param array<mixed> $data
     */
    private function convert(array $data): ClassificationResult
    {
        $httpClient = new MockHttpClient(new JsonMockResponse($data));
        $result = (new ResultConverter())->convert(new RawHttpResult($httpClient->request('POST', 'https://router.huggingface.co/hf-inference/models/test/model')));

        $content = $result->getContent();
        $this->assertInstanceOf(ClassificationResult::class, $content);

        return $content;
    }
}
