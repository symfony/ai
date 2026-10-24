<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Decision\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Decision\RequestBodyBuilder;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;

final class RequestBodyBuilderTest extends TestCase
{
    public function testItBuildsTheBody()
    {
        $body = RequestBodyBuilder::build(new Model('jev'), [
            'state' => 'text',
            'questions' => ['q' => ['type' => 'score', 'criteria' => ['a', 'b']]],
        ]);

        $this->assertSame('jev', $body['model']);
        $this->assertSame('text', $body['state']);
        $this->assertSame('{"q":{"type":"score","criteria":["a","b"]}}', json_encode($body['questions']));
    }

    public function testItKeepsNonScoreCriteriaAsObjects()
    {
        $body = RequestBodyBuilder::build(new Model('jev'), [
            'state' => 'text',
            'questions' => ['q' => ['type' => 'choice', 'criteria' => [1 => 'one', 2 => 'two']]],
        ]);

        $this->assertSame('{"q":{"type":"choice","criteria":{"1":"one","2":"two"}}}', json_encode($body['questions']));
    }

    public function testItRejectsAStringPayload()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Decision payload must be an array with a "state" key and a "questions" map.');

        RequestBodyBuilder::build(new Model('jev'), 'text');
    }

    public function testItRejectsAMissingState()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Decision payload must be an array with a "state" key and a "questions" map.');

        RequestBodyBuilder::build(new Model('jev'), ['questions' => []]);
    }

    public function testItRejectsANonArrayQuestion()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Question "q" must be an array, "string" given.');

        RequestBodyBuilder::build(new Model('jev'), ['state' => 'text', 'questions' => ['q' => 'nope']]);
    }
}
