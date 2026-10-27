<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Test;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Test\ScriptedResponse;

final class ScriptedResponseTest extends TestCase
{
    public function testStringScriptWrapsInTextResult()
    {
        $response = new ScriptedResponse('Hello world');

        $result = $response->resolve(new Model('any-model'), 'q', []);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world', $result->getContent());
    }

    public function testMapScriptSelectsByModelName()
    {
        $object = new ObjectResult(['foo' => 'bar']);
        $response = new ScriptedResponse([
            'model-a' => 'answer a',
            'model-b' => $object,
        ]);

        $resultA = $response->resolve(new Model('model-a'), 'q', []);
        $resultB = $response->resolve(new Model('model-b'), 'q', []);

        $this->assertInstanceOf(TextResult::class, $resultA);
        $this->assertSame('answer a', $resultA->getContent());
        $this->assertSame($object, $resultB);
    }

    public function testMapScriptThrowsForUnknownModel()
    {
        $response = new ScriptedResponse(['known' => 'answer']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No scripted response configured for model "unknown".');

        $response->resolve(new Model('unknown'), 'q', []);
    }

    public function testClosureScriptReceivesModelInputAndOptions()
    {
        $response = new ScriptedResponse(static function (Model $model, array|string|object $input, array $options): string {
            return \sprintf('%s|%s|%s', $model->getName(), $input, $options['temperature']);
        });

        $result = $response->resolve(new Model('gpt'), 'hi', ['temperature' => 0.5]);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('gpt|hi|0.5', $result->getContent());
    }

    public function testClosureScriptReturningResultInterfaceIsPassedThrough()
    {
        $object = new ObjectResult(['answer' => 42]);
        $response = new ScriptedResponse(static fn (): ObjectResult => $object);

        $this->assertSame($object, $response->resolve(new Model('any-model'), 'q', []));
    }
}
