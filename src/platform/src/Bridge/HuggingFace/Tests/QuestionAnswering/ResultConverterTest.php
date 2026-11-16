<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Tests\QuestionAnswering;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\QuestionAnsweringResult;
use Symfony\AI\Platform\Bridge\HuggingFace\QuestionAnswering\ResultConverter;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResultConverterTest extends TestCase
{
    #[TestDox('fromArray creates instance from array data')]
    public function testFromArray()
    {
        $data = [
            'answer' => 'Machine learning',
            'start' => 25,
            'end' => 40,
            'score' => 0.87,
        ];

        $result = $this->convert($data);

        $this->assertSame('Machine learning', $result->getAnswer());
        $this->assertSame(25, $result->getStartIndex());
        $this->assertSame(40, $result->getEndIndex());
        $this->assertSame(0.87, $result->getScore());
    }

    /**
     * @param array{answer: string, start: int, end: int, score: float} $data
     */
    #[TestDox('fromArray handles various data formats')]
    #[TestWith([['answer' => 'Yes', 'start' => 0, 'end' => 3, 'score' => 1.0]])]
    #[TestWith([['answer' => 'No answer found', 'start' => -1, 'end' => -1, 'score' => 0.0]])]
    #[TestWith([['answer' => '2023', 'start' => 100, 'end' => 104, 'score' => 0.95]])]
    #[TestWith([['answer' => 'The quick brown fox', 'start' => 0, 'end' => 19, 'score' => 0.88]])]
    public function testFromArrayWithVariousData(array $data)
    {
        $result = $this->convert($data);

        $this->assertSame($data['answer'], $result->getAnswer());
        $this->assertSame($data['start'], $result->getStartIndex());
        $this->assertSame($data['end'], $result->getEndIndex());
        $this->assertSame($data['score'], $result->getScore());
    }

    /**
     * @param array<mixed> $data
     */
    private function convert(array $data): QuestionAnsweringResult
    {
        $httpClient = new MockHttpClient(new JsonMockResponse($data));
        $result = (new ResultConverter())->convert(new RawHttpResult($httpClient->request('POST', 'https://router.huggingface.co/hf-inference/models/test/model')));

        $content = $result->getContent();
        $this->assertInstanceOf(QuestionAnsweringResult::class, $content);

        return $content;
    }
}
