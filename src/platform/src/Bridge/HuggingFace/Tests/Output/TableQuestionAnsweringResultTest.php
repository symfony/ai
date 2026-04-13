<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Tests\Output;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\TableQuestionAnsweringResult;

/**
 * @author Oskar Stark <oskar.stark@gmail.com>
 */
final class TableQuestionAnsweringResultTest extends TestCase
{
    #[TestDox('Construction with required answer parameter creates valid instance')]
    public function testConstruction()
    {
        $result = new TableQuestionAnsweringResult('Paris');

        $this->assertSame('Paris', $result->getAnswer());
        $this->assertSame([], $result->getCoordinates());
        $this->assertSame([], $result->getCells());
        $this->assertNull($result->getAggregator());
    }

    #[TestDox('Construction with all parameters creates valid instance')]
    public function testConstructionWithAllParameters()
    {
        $coordinates = [[0, 1]];
        $cells = ['cell1', 'cell2', 42];
        $aggregator = ['SUM', 'AVERAGE'];

        $result = new TableQuestionAnsweringResult('Total is 100', $coordinates, $cells, $aggregator);

        $this->assertSame('Total is 100', $result->getAnswer());
        $this->assertSame($coordinates, $result->getCoordinates());
        $this->assertSame($cells, $result->getCells());
        $this->assertSame($aggregator, $result->getAggregator());
    }

    /**
     * @param array{0: int, 1: int}[] $coordinates
     * @param array<string|int>       $cells
     * @param array<string>           $aggregator
     */
    #[TestDox('Constructor accepts various parameter combinations')]
    #[TestWith(['Yes', [], [], []])]
    #[TestWith(['No', [[0, 1]], ['A1'], ['COUNT']])]
    #[TestWith(['42.5', [[0, 1]], ['A1', 'B1', 42], ['SUM', 'AVERAGE']])]
    #[TestWith(['', [[0, 1]], [], []])]
    #[TestWith(['Complex answer with multiple words', [[0, 1]], [1, 2, 3], ['NONE']])]
    public function testConstructorWithDifferentValues(string $answer, array $coordinates, array $cells, array $aggregator)
    {
        $result = new TableQuestionAnsweringResult($answer, $coordinates, $cells, $aggregator);

        $this->assertSame($answer, $result->getAnswer());
        $this->assertSame($coordinates, $result->getCoordinates());
        $this->assertSame($cells, $result->getCells());
        $this->assertSame($aggregator, $result->getAggregator());
    }

    #[TestDox('fromArray creates instance with required answer field')]
    public function testFromArrayWithRequiredField()
    {
        $data = ['answer' => 'Berlin'];

        $result = TableQuestionAnsweringResult::fromArray($data);

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

        $result = TableQuestionAnsweringResult::fromArray($data);

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
        $result = TableQuestionAnsweringResult::fromArray($data);

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

        $result = TableQuestionAnsweringResult::fromArray($data);

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
        $result = TableQuestionAnsweringResult::fromArray($data);

        $this->assertSame($data['answer'], $result->getAnswer());
        $this->assertSame($data['aggregator'], $result->getAggregator());
    }

    #[TestDox('Empty arrays are handled correctly')]
    public function testEmptyArrays()
    {
        $result1 = new TableQuestionAnsweringResult('answer', [], [], []);
        $result2 = TableQuestionAnsweringResult::fromArray(['answer' => 'test']);

        $this->assertSame([], $result1->getCoordinates());
        $this->assertSame([], $result1->getCells());
        $this->assertSame([], $result1->getAggregator());
        $this->assertSame([], $result2->getCoordinates());
        $this->assertSame([], $result2->getCells());
        $this->assertNull($result2->getAggregator());
    }

    #[TestDox('Large cell arrays are handled correctly')]
    public function testLargeCellArrays()
    {
        $largeCells = [];
        for ($i = 0; $i < 100; ++$i) {
            $largeCells[] = "cell_$i";
            $largeCells[] = $i;
        }

        $result = new TableQuestionAnsweringResult('Large table result', [], $largeCells, ['COUNT']);

        $this->assertCount(200, $result->getCells());
        $this->assertSame('cell_0', $result->getCells()[0]);
        $this->assertSame(0, $result->getCells()[1]);
        $this->assertSame('cell_99', $result->getCells()[198]);
        $this->assertSame(99, $result->getCells()[199]);
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
        $result1 = new TableQuestionAnsweringResult($answer);
        $result2 = TableQuestionAnsweringResult::fromArray(['answer' => $answer]);

        $this->assertSame($answer, $result1->getAnswer());
        $this->assertSame($answer, $result2->getAnswer());
    }
}
