<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\HuggingFace\Output;

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

        $this->assertSame('Paris', $result->answer);
        $this->assertSame([], $result->coordinates);
        $this->assertSame([], $result->cells);
        $this->assertNull($result->aggregator);
    }

    #[TestDox('Construction with all parameters creates valid instance')]
    public function testConstructionWithAllParameters()
    {
        $coordinates = [[0, 1]];
        $cells = ['cell1', 'cell2', 42];
        $aggregator = ['SUM', 'AVERAGE'];

        $result = new TableQuestionAnsweringResult('Total is 100', $coordinates, $cells, $aggregator);

        $this->assertSame('Total is 100', $result->answer);
        $this->assertSame($coordinates, $result->coordinates);
        $this->assertSame($cells, $result->cells);
        $this->assertSame($aggregator, $result->aggregator);
    }

    #[TestDox('Constructor accepts various parameter combinations')]
    #[TestWith(['Yes', [], [], []])]
    #[TestWith(['No', [[0, 1]], ['A1'], ['COUNT']])]
    #[TestWith(['42.5', [[0, 1]], ['A1', 'B1', 42], ['SUM', 'AVERAGE']])]
    #[TestWith(['', [[0, 1]], [], []])]
    #[TestWith(['Complex answer with multiple words', [[0, 1]], [1, 2, 3], ['NONE']])]
    public function testConstructorWithDifferentValues(string $answer, array $coordinates, array $cells, array $aggregator)
    {
        $result = new TableQuestionAnsweringResult($answer, $coordinates, $cells, $aggregator);

        $this->assertSame($answer, $result->answer);
        $this->assertSame($coordinates, $result->coordinates);
        $this->assertSame($cells, $result->cells);
        $this->assertSame($aggregator, $result->aggregator);
    }

    #[TestDox('fromArray creates instance with required answer field')]
    public function testFromArrayWithRequiredField()
    {
        $data = ['answer' => 'Berlin'];

        $result = TableQuestionAnsweringResult::fromArray($data);

        $this->assertSame('Berlin', $result->answer);
        $this->assertSame([], $result->coordinates);
        $this->assertSame([], $result->cells);
        $this->assertNull($result->aggregator);
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

        $this->assertSame('The result is 150', $result->answer);
        $this->assertSame([[0, 0], [1, 1]], $result->coordinates);
        $this->assertSame(['A1', 'B2', 100, 50], $result->cells);
        $this->assertSame(['SUM'], $result->aggregator);
    }

    #[TestDox('fromArray handles optional fields with default values')]
    #[TestWith([['answer' => 'Test', 'coordinates' => [[0, 0], [1, 1]]]])]
    #[TestWith([['answer' => 'Test', 'cells' => ['A1', 'B1']]])]
    #[TestWith([['answer' => 'Test', 'aggregator' => ['COUNT']]])]
    #[TestWith([['answer' => 'Test', 'cells' => [1, 2], 'aggregator' => ['SUM', 'AVG']]])]
    public function testFromArrayWithOptionalFields(array $data)
    {
        $result = TableQuestionAnsweringResult::fromArray($data);

        $this->assertSame($data['answer'], $result->answer);
        $this->assertSame($data['coordinates'] ?? [], $result->coordinates);
        $this->assertSame($data['cells'] ?? [], $result->cells);
        $this->assertSame($data['aggregator'] ?? null, $result->aggregator);
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

        $this->assertSame('Mixed types', $result->answer);
        $this->assertCount(5, $result->cells);
        $this->assertSame('string', $result->cells[0]);
        $this->assertSame(42, $result->cells[1]);
        $this->assertSame('3.14', $result->cells[2]);
        $this->assertSame('another string', $result->cells[3]);
        $this->assertSame(0, $result->cells[4]);
        $this->assertSame(['NONE'], $result->aggregator);
    }

    #[TestDox('fromArray handles various aggregator formats')]
    #[TestWith([['answer' => 'Test', 'aggregator' => []]])]
    #[TestWith([['answer' => 'Test', 'aggregator' => ['NONE']]])]
    #[TestWith([['answer' => 'Test', 'aggregator' => ['SUM', 'COUNT', 'AVERAGE']]])]
    #[TestWith([['answer' => 'Test', 'aggregator' => ['custom_aggregator']]])]
    public function testFromArrayWithVariousAggregatorFormats(array $data)
    {
        $result = TableQuestionAnsweringResult::fromArray($data);

        $this->assertSame($data['answer'], $result->answer);
        $this->assertSame($data['aggregator'], $result->aggregator);
    }

    #[TestDox('Empty arrays are handled correctly')]
    public function testEmptyArrays()
    {
        $result1 = new TableQuestionAnsweringResult('answer', [], [], []);
        $result2 = TableQuestionAnsweringResult::fromArray(['answer' => 'test']);

        $this->assertSame([], $result1->coordinates);
        $this->assertSame([], $result1->cells);
        $this->assertSame([], $result1->aggregator);
        $this->assertSame([], $result2->coordinates);
        $this->assertSame([], $result2->cells);
        $this->assertNull($result2->aggregator);
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

        $this->assertCount(200, $result->cells);
        $this->assertSame('cell_0', $result->cells[0]);
        $this->assertSame(0, $result->cells[1]);
        $this->assertSame('cell_99', $result->cells[198]);
        $this->assertSame(99, $result->cells[199]);
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

        $this->assertSame($answer, $result1->answer);
        $this->assertSame($answer, $result2->answer);
    }
}
