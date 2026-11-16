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

    #[TestDox('Empty arrays are handled correctly')]
    public function testEmptyArrays()
    {
        $result = new TableQuestionAnsweringResult('answer', [], [], []);

        $this->assertSame([], $result->getCoordinates());
        $this->assertSame([], $result->getCells());
        $this->assertSame([], $result->getAggregator());
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
        $result = new TableQuestionAnsweringResult($answer);

        $this->assertSame($answer, $result->getAnswer());
    }
}
