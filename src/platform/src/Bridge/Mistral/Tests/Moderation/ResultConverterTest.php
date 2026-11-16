<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Tests\Moderation;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\AI\Platform\Bridge\Mistral\Moderation;
use Symfony\AI\Platform\Bridge\Mistral\Moderation\Result\ModerationResult;
use Symfony\AI\Platform\Bridge\Mistral\Moderation\ResultConverter;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ResultConverterTest extends TestCase
{
    public function testItSupportsModerationModel()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new Moderation('mistral-moderation-latest')));
        $this->assertFalse($converter->supports(new Mistral('mistral-large-latest')));
    }

    public function testItConvertsEachResultToAVerdict()
    {
        $moderation = (new ResultConverter())->convert($this->rawResult(new JsonMockResponse([
            'id' => 'abc',
            'model' => 'mistral-moderation-2603',
            'results' => [
                [
                    'categories' => ['sexual' => false, 'jailbreaking' => false],
                    'category_scores' => ['sexual' => 0.001, 'jailbreaking' => 0.002],
                ],
                [
                    'categories' => ['sexual' => false, 'jailbreaking' => true],
                    'category_scores' => ['sexual' => 0.003, 'jailbreaking' => 0.998],
                ],
            ],
        ])))->getContent();

        $this->assertInstanceOf(ModerationResult::class, $moderation);
        $this->assertSame('abc', $moderation->getId());
        $this->assertSame('mistral-moderation-2603', $moderation->getModel());
        $this->assertTrue($moderation->isFlagged());
        $this->assertSame(['jailbreaking'], $moderation->getFlaggedCategories());

        [$clean, $flagged] = $moderation->getVerdicts();

        $this->assertFalse($clean->isFlagged());
        $this->assertSame([], $clean->getFlaggedCategories());
        $this->assertTrue($flagged->isFlagged());
        $this->assertSame(['jailbreaking'], $flagged->getFlaggedCategories());
        $this->assertSame(['sexual' => false, 'jailbreaking' => true], $flagged->getCategories());
        $this->assertSame(['sexual' => 0.003, 'jailbreaking' => 0.998], $flagged->getScores());
        $this->assertSame(0.998, $flagged->getScore('jailbreaking'));
        $this->assertNull($flagged->getScore('unknown'));
    }

    public function testItIsNotFlaggedWhenNoCategoryIs()
    {
        $moderation = (new ResultConverter())->convert($this->rawResult(new JsonMockResponse([
            'results' => [
                ['categories' => ['sexual' => false], 'category_scores' => ['sexual' => 0.001]],
            ],
        ])))->getContent();

        $this->assertInstanceOf(ModerationResult::class, $moderation);
        $this->assertFalse($moderation->isFlagged());
        $this->assertSame([], $moderation->getFlaggedCategories());
    }

    public function testItListsACategoryFlaggedInSeveralVerdictsOnce()
    {
        $moderation = (new ResultConverter())->convert($this->rawResult(new JsonMockResponse([
            'results' => [
                ['categories' => ['pii' => true, 'jailbreaking' => true], 'category_scores' => ['pii' => 0.9, 'jailbreaking' => 0.9]],
                ['categories' => ['pii' => false, 'jailbreaking' => true], 'category_scores' => ['pii' => 0.1, 'jailbreaking' => 0.9]],
            ],
        ])))->getContent();

        $this->assertInstanceOf(ModerationResult::class, $moderation);
        $this->assertSame(['pii', 'jailbreaking'], $moderation->getFlaggedCategories());
    }

    public function testItIsFlaggedWheneverACategoryIsListedAsFlagged()
    {
        $moderation = (new ResultConverter())->convert($this->rawResult(new JsonMockResponse([
            'results' => [
                ['categories' => ['sexual' => 0, 'jailbreaking' => 1], 'category_scores' => ['sexual' => 0.001, 'jailbreaking' => 0.9]],
            ],
        ])))->getContent();

        $this->assertInstanceOf(ModerationResult::class, $moderation);
        $this->assertSame(['jailbreaking'], $moderation->getFlaggedCategories());
        $this->assertTrue($moderation->isFlagged());
        $this->assertTrue($moderation->getVerdicts()[0]->isFlagged());
    }

    public function testItThrowsWhenResultsAreMissing()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain moderation results.');

        (new ResultConverter())->convert($this->rawResult(new JsonMockResponse(['foo' => 'bar'])));
    }

    public function testItThrowsWhenResultsAreEmpty()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain moderation results.');

        (new ResultConverter())->convert($this->rawResult(new JsonMockResponse(['results' => []])));
    }

    public function testItThrowsOnAnUnexpectedStatusCode()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected response code 422');

        (new ResultConverter())->convert($this->rawResult(new JsonMockResponse(
            ['detail' => [['type' => 'extra_forbidden', 'loc' => ['body', 'temperature'], 'msg' => 'Extra inputs are not permitted']]],
            ['http_code' => 422],
        )));
    }

    public function testItThrowsOnBadRequest()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Please provide at least one input element.');

        (new ResultConverter())->convert($this->rawResult(new JsonMockResponse(
            ['message' => 'Please provide at least one input element.'],
            ['http_code' => 400],
        )));
    }

    private function rawResult(JsonMockResponse $response): RawHttpResult
    {
        return new RawHttpResult((new MockHttpClient($response))->request('POST', 'https://api.mistral.ai/v1/moderations'));
    }
}
