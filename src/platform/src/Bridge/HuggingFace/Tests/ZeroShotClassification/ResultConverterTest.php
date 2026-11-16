<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Tests\ZeroShotClassification;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\ZeroShotClassificationResult;
use Symfony\AI\Platform\Bridge\HuggingFace\ZeroShotClassification\ResultConverter;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResultConverterTest extends TestCase
{
    #[TestDox('fromArray creates instance from new serverless format')]
    public function testFromArrayWithNewServerlessFormat()
    {
        $data = [
            ['label' => 'refund', 'score' => 0.878],
            ['label' => 'faq', 'score' => 0.105],
            ['label' => 'legal', 'score' => 0.017],
        ];

        $result = $this->convert($data);

        $this->assertSame(['refund', 'faq', 'legal'], $result->getLabels());
        $this->assertSame([0.878, 0.105, 0.017], $result->getScores());
        $this->assertNull($result->getSequence());
    }

    #[TestDox('fromArray creates instance with required fields')]
    public function testFromArrayWithRequiredFields()
    {
        $data = [
            'labels' => ['positive', 'negative'],
            'scores' => [0.85, 0.15],
        ];

        $result = $this->convert($data);

        $this->assertSame(['positive', 'negative'], $result->getLabels());
        $this->assertSame([0.85, 0.15], $result->getScores());
        $this->assertNull($result->getSequence());
    }

    #[TestDox('fromArray creates instance with all fields')]
    public function testFromArrayWithAllFields()
    {
        $data = [
            'labels' => ['sports', 'politics', 'entertainment'],
            'scores' => [0.6, 0.3, 0.1],
            'sequence' => 'The match was exciting to watch',
        ];

        $result = $this->convert($data);

        $this->assertSame(['sports', 'politics', 'entertainment'], $result->getLabels());
        $this->assertSame([0.6, 0.3, 0.1], $result->getScores());
        $this->assertSame('The match was exciting to watch', $result->getSequence());
    }

    /**
     * @param array{labels: array<string>, scores: array<float>} $data
     */
    #[TestDox('fromArray handles optional sequence field correctly')]
    #[TestWith([['labels' => ['a', 'b'], 'scores' => [0.7, 0.3]]])]
    #[TestWith([['labels' => ['test'], 'scores' => [1.0], 'sequence' => '']])]
    #[TestWith([['labels' => ['x', 'y'], 'scores' => [0.5, 0.5], 'sequence' => 'Test sequence']])]
    public function testFromArrayWithOptionalSequence(array $data)
    {
        $result = $this->convert($data);

        $this->assertSame($data['labels'], $result->getLabels());
        $this->assertSame($data['scores'], $result->getScores());
        $this->assertSame($data['sequence'] ?? null, $result->getSequence());
    }

    /**
     * @param array<string> $labels
     */
    #[TestDox('fromArray handles various label formats')]
    #[TestWith([['', 'empty', 'UPPERCASE', 'lowercase']])]
    #[TestWith([['with-dashes', 'with_underscores', 'with spaces', '123numeric']])]
    #[TestWith([['émoji 🎉', 'special-chars_123!@#']])]
    #[TestWith([['very_long_label_that_might_be_used_in_some_classification_tasks']])]
    public function testFromArrayWithVariousLabelFormats(array $labels)
    {
        $scores = array_fill(0, \count($labels), 1.0 / \count($labels));
        $data = ['labels' => $labels, 'scores' => $scores];

        $result = $this->convert($data);

        $this->assertSame($labels, $result->getLabels());
        $this->assertCount(\count($labels), $result->getLabels());
    }

    /**
     * @param array{labels: array<string>, scores: array<float>} $data
     */
    #[TestDox('fromArray handles various score formats')]
    #[TestWith([['labels' => ['a'], 'scores' => [0.0]]])]
    #[TestWith([['labels' => ['a'], 'scores' => [1.0]]])]
    #[TestWith([['labels' => ['a', 'b'], 'scores' => [0.123456789, 0.876543211]]])]
    #[TestWith([['labels' => ['a', 'b'], 'scores' => [-0.1, 1.1]]])] // Edge case
    #[TestWith([['labels' => ['a', 'b', 'c'], 'scores' => [0.5, 0.3, 0.2]]])]
    public function testFromArrayWithVariousScoreFormats(array $data)
    {
        $result = $this->convert($data);

        $this->assertSame($data['scores'], $result->getScores());
        $this->assertCount(\count($data['scores']), $result->getScores());

        foreach ($data['scores'] as $index => $expectedScore) {
            $this->assertSame($expectedScore, $result->getScores()[$index]);
        }
    }

    #[TestDox('Special sequence values are handled correctly')]
    #[TestWith([''])] // Empty string
    #[TestWith(['0'])] // String zero
    #[TestWith(['false'])] // String false
    #[TestWith(['null'])] // String null
    #[TestWith(['Multi\nline\nsequence'])] // Multiline string
    #[TestWith(['Sequence with émoji 🎉 and special chars!@#'])] // Unicode and special chars
    #[TestWith(['Very long sequence that might contain lots of text and details about the classification task and could potentially be quite lengthy'])]
    public function testSpecialSequenceValues(string $sequence)
    {
        $labels = ['test'];
        $scores = [1.0];

        $result = $this->convert([
            'labels' => $labels,
            'scores' => $scores,
            'sequence' => $sequence,
        ]);

        $this->assertSame($sequence, $result->getSequence());
    }

    /**
     * @param array<mixed> $data
     */
    private function convert(array $data): ZeroShotClassificationResult
    {
        $httpClient = new MockHttpClient(new JsonMockResponse($data));
        $result = (new ResultConverter())->convert(new RawHttpResult($httpClient->request('POST', 'https://router.huggingface.co/hf-inference/models/test/model')));

        $content = $result->getContent();
        $this->assertInstanceOf(ZeroShotClassificationResult::class, $content);

        return $content;
    }
}
