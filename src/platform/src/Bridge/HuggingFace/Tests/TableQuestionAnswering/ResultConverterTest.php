<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Tests\TableQuestionAnswering;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\TableQuestionAnsweringResult;
use Symfony\AI\Platform\Bridge\HuggingFace\TableQuestionAnswering\ResultConverter;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResultConverterTest extends TestCase
{
    #[TestDox('fromArray creates instance with required answer field')]
    public function testFromArrayWithRequiredField()
    {
        $data = ['answer' => 'Berlin'];

        $result = $this->convert($data);

        $this->assertSame('Berlin', $result->getAnswer());
        $this->assertSame([], $result->getCoordinates());
        $this->assertSame([], $result->getCells());
        $this->assertNull($result->getAggregator());
    }

    #[TestDox('fromArray creates instance with all fields')]
    public function testFromArrayWithAllFields()
    {
        $data = [
            'answer' => 'The result is 150',
            'coordinates' => [[0, 0], [1, 1]],
            'cells' => ['A1', 'B2', 100, 50],
            'aggregator' => ['SUM'],
        ];

        $result = $this->convert($data);

        $this->assertSame('The result is 150', $result->getAnswer());
        $this->assertSame([[0, 0], [1, 1]], $result->getCoordinates());
        $this->assertSame(['A1', 'B2', 100, 50], $result->getCells());
        $this->assertSame(['SUM'], $result->getAggregator());
    }

    /**
     * @param array{answer: string, coordinates?: array{0: int, 1: int}[], cells?: array<string|int>, aggregator?: array<string>} $data
     */
    #[TestDox('fromArray handles optional fields with default values')]
    #[TestWith([['answer' => 'Test', 'coordinates' => [[0, 0], [1, 1]]]])]
    #[TestWith([['answer' => 'Test', 'cells' => ['A1', 'B1']]])]
    #[TestWith([['answer' => 'Test', 'aggregator' => ['COUNT']]])]
    #[TestWith([['answer' => 'Test', 'cells' => [1, 2], 'aggregator' => ['SUM', 'AVG']]])]
    public function testFromArrayWithOptionalFields(array $data)
    {
        $result = $this->convert($data);

        $this->assertSame($data['answer'], $result->getAnswer());
        $this->assertSame($data['coordinates'] ?? [], $result->getCoordinates());
        $this->assertSame($data['cells'] ?? [], $result->getCells());
        $this->assertSame($data['aggregator'] ?? null, $result->getAggregator());
    }

    #[TestDox('fromArray handles various cell data types')]
    public function testFromArrayWithVariousCellTypes()
    {
        $data = [
            'answer' => 'Mixed types',
            'cells' => ['string', 42, '3.14', 'another string', 0],
            'aggregator' => ['NONE'],
        ];

        $result = $this->convert($data);

        $this->assertSame('Mixed types', $result->getAnswer());
        $this->assertCount(5, $result->getCells());
        $this->assertSame('string', $result->getCells()[0]);
        $this->assertSame(42, $result->getCells()[1]);
        $this->assertSame('3.14', $result->getCells()[2]);
        $this->assertSame('another string', $result->getCells()[3]);
        $this->assertSame(0, $result->getCells()[4]);
        $this->assertSame(['NONE'], $result->getAggregator());
    }

    /**
     * @param array{answer: string, aggregator: array<string>} $data
     */
    #[TestDox('fromArray handles various aggregator formats')]
    #[TestWith([['answer' => 'Test', 'aggregator' => []]])]
    #[TestWith([['answer' => 'Test', 'aggregator' => ['NONE']]])]
    #[TestWith([['answer' => 'Test', 'aggregator' => ['SUM', 'COUNT', 'AVERAGE']]])]
    #[TestWith([['answer' => 'Test', 'aggregator' => ['custom_aggregator']]])]
    public function testFromArrayWithVariousAggregatorFormats(array $data)
    {
        $result = $this->convert($data);

        $this->assertSame($data['answer'], $result->getAnswer());
        $this->assertSame($data['aggregator'], $result->getAggregator());
    }

    #[TestDox('Empty arrays are handled correctly')]
    public function testEmptyArrays()
    {
        $result = $this->convert(['answer' => 'test']);

        $this->assertSame([], $result->getCoordinates());
        $this->assertSame([], $result->getCells());
        $this->assertNull($result->getAggregator());
    }

    #[TestDox('Special answer values are handled correctly')]
    #[TestWith([''])] // Empty string
    #[TestWith(['0'])] // String zero
    #[TestWith(['false'])] // String false
    #[TestWith(['null'])] // String null
    #[TestWith(['Multi\nline\nanswer'])] // Multiline string
    #[TestWith(['Answer with émoji 🎉'])] // Unicode characters
    #[TestWith(['Very long answer that might contain lots of details and explanations about the table data'])]
    public function testSpecialAnswerValues(string $answer)
    {
        $result = $this->convert(['answer' => $answer]);

        $this->assertSame($answer, $result->getAnswer());
    }

    /**
     * @param array<mixed> $data
     */
    private function convert(array $data): TableQuestionAnsweringResult
    {
        $httpClient = new MockHttpClient(new JsonMockResponse($data));
        $result = (new ResultConverter())->convert(new RawHttpResult($httpClient->request('POST', 'https://router.huggingface.co/hf-inference/models/test/model')));

        $content = $result->getContent();
        $this->assertInstanceOf(TableQuestionAnsweringResult::class, $content);

        return $content;
    }
}
