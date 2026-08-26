<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Execution;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\LogicException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Platform\Exception\UnexpectedResultTypeException;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\PartialObjectDelta;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\TextResult;

final class ExecutionTest extends TestCase
{
    public function testGetResultReturnsTheFinalResult()
    {
        $result = new TextResult('Done');

        $execution = new Execution(static function () use ($result): \Generator {
            yield new Progress('model_request', 'Invoking model.');
            yield new ResultUpdate($result);
        });

        $this->assertSame($result, $execution->getResult());
    }

    public function testItIsIterable()
    {
        $execution = new Execution(static function (): \Generator {
            yield new Progress('model_request', 'Invoking model.');
            yield new ResultUpdate(new TextResult('Done'));
        });

        $updates = iterator_to_array($execution, false);

        $this->assertCount(2, $updates);
        $this->assertInstanceOf(Progress::class, $updates[0]);
        $this->assertInstanceOf(ResultUpdate::class, $updates[1]);
    }

    public function testItInvokesTheRegisteredCallbacksWhileResolving()
    {
        $execution = new Execution(static function (): \Generator {
            yield new Progress('model_request', 'Invoking model.');
            yield new Progress('tool_call', 'Executing tool "clock".');
            yield new ResultUpdate(new TextResult('Done'));
        });

        $stages = [];
        $results = [];

        $execution
            ->onProgress(static function (Progress $progress) use (&$stages): void {
                $stages[] = $progress->getStage();
            })
            ->onResult(static function (ResultUpdate $update) use (&$results): void {
                $results[] = $update->getResult()->getContent();
            })
            ->getResult();

        $this->assertSame(['model_request', 'tool_call'], $stages);
        $this->assertSame(['Done'], $results);
    }

    public function testItInvokesTheRegisteredCallbacksHoweverItIsConsumed()
    {
        $factory = static function (): \Generator {
            yield new Progress('delta', 'Received a streamed delta.', new TextDelta('Hello'));
            yield new ResultUpdate(new TextResult('Hello'));
        };

        foreach (['iterate', 'stream'] as $mode) {
            $stages = [];
            $results = [];

            $execution = (new Execution($factory, streamed: true))
                ->onProgress(static function (Progress $progress) use (&$stages): void {
                    $stages[] = $progress->getStage();
                })
                ->onResult(static function (ResultUpdate $update) use (&$results): void {
                    $results[] = $update->getResult()->getContent();
                });

            iterator_to_array('iterate' === $mode ? $execution : $execution->asStream(), false);

            $this->assertSame(['delta'], $stages, $mode);
            $this->assertSame(['Hello'], $results, $mode);
        }
    }

    public function testItIsLazyAndOnlyRunsTheAgentWhenConsumed()
    {
        $state = new \ArrayObject(['runs' => 0]);

        $execution = new Execution(static function () use ($state): \Generator {
            ++$state['runs'];

            yield new ResultUpdate(new TextResult('Done'));
        });

        $this->assertSame(0, $state['runs']);

        $execution->getResult();

        $this->assertSame(1, $state['runs']);
    }

    public function testGetResultIsIdempotentAndDoesNotRerunTheAgent()
    {
        $result = new TextResult('Done');
        $state = new \ArrayObject(['runs' => 0]);

        $execution = new Execution(static function () use ($result, $state): \Generator {
            ++$state['runs'];

            yield new ResultUpdate($result);
        });

        $this->assertSame($result, $execution->getResult());
        $this->assertSame($result, $execution->getResult());
        $this->assertSame(1, $state['runs']);
    }

    public function testItThrowsWhenIteratedAfterBeingConsumed()
    {
        $execution = new Execution(static function (): \Generator {
            yield new ResultUpdate(new TextResult('Done'));
        });

        $execution->getResult();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The execution was already consumed. Call the agent again for a new execution.');

        iterator_to_array($execution);
    }

    public function testItActsAsTheResultItProduces()
    {
        $execution = new Execution(static function (): \Generator {
            yield new Progress('model_request', 'Invoking model.');
            yield new ResultUpdate(new TextResult('Hello world'));
        });

        $this->assertInstanceOf(ResultInterface::class, $execution);
        $this->assertSame('Hello world', $execution->getContent());
    }

    public function testStreamedGetContentYieldsTheDeltas()
    {
        $execution = new Execution(static function (): \Generator {
            yield new Progress('delta', 'Received a streamed delta.', new TextDelta('Hello '));
            yield new Progress('delta', 'Received a streamed delta.', new TextDelta('world'));
            yield new ResultUpdate(new TextResult('Hello world'));
        }, streamed: true);

        $text = '';
        foreach ($execution->getContent() as $delta) {
            $text .= $delta->getText();
        }

        $this->assertSame('Hello world', $text);
    }

    public function testItThrowsWhenNoResultIsProduced()
    {
        $execution = new Execution(static function (): \Generator {
            yield new Progress('model_request', 'Invoking model.');
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The agent execution finished without producing a result.');

        $execution->getResult();
    }

    public function testAsTextNarrowsToTheTextResult()
    {
        $execution = new Execution(static function (): \Generator {
            yield new ResultUpdate(new TextResult('Done'));
        });

        $this->assertSame('Done', $execution->asText());
    }

    public function testAsTextNarrowsAMultiPartResultToItsSingleTextPart()
    {
        $execution = new Execution(static function (): \Generator {
            yield new ResultUpdate(new MultiPartResult([
                new ObjectResult(new \stdClass()),
                new TextResult('Done'),
            ]));
        });

        $this->assertSame('Done', $execution->asText());
    }

    public function testAsObjectNarrowsToTheObjectResult()
    {
        $object = new \stdClass();

        $execution = new Execution(static function () use ($object): \Generator {
            yield new ResultUpdate(new ObjectResult($object));
        });

        $this->assertSame($object, $execution->asObject());
    }

    public function testAsBinaryAndAsDataUriNarrowToTheBinaryResult()
    {
        $execution = new Execution(static function (): \Generator {
            yield new ResultUpdate(new BinaryResult('audio-bytes', 'audio/mpeg'));
        });

        $this->assertSame('audio-bytes', $execution->asBinary());
        $this->assertSame('data:audio/mpeg;base64,'.base64_encode('audio-bytes'), $execution->asDataUri());
    }

    public function testAsFileWritesTheBinaryResult()
    {
        $path = tempnam(sys_get_temp_dir(), 'execution');

        $execution = new Execution(static function (): \Generator {
            yield new ResultUpdate(new BinaryResult('audio-bytes', 'audio/mpeg'));
        });

        try {
            $execution->asFile($path);

            $this->assertStringEqualsFile($path, 'audio-bytes');
        } finally {
            @unlink($path);
        }
    }

    public function testTypedAccessorsThrowOnAnUnexpectedResultType()
    {
        $execution = new Execution(static function (): \Generator {
            yield new ResultUpdate(new TextResult('Done'));
        });

        $this->expectException(UnexpectedResultTypeException::class);

        $execution->asObject();
    }

    public function testTypedAccessorsDriveTheExecutionOnce()
    {
        $runs = 0;

        $execution = new Execution(static function () use (&$runs): \Generator {
            ++$runs;

            yield new ResultUpdate(new TextResult('Done'));
        });

        $execution->asText();
        $execution->asText();

        $this->assertSame(1, $runs);
    }

    public function testAsStreamYieldsTheDeltasOfAStreamedExecution()
    {
        $execution = new Execution(static function (): \Generator {
            yield new Progress('model_request', 'Invoking model.');
            yield new Progress('delta', 'Received a streamed delta.', new TextDelta('Hello '));
            yield new Progress('delta', 'Received a streamed delta.', new TextDelta('world!'));
            yield new ResultUpdate(new TextResult('Hello world!'));
        }, streamed: true);

        $deltas = iterator_to_array($execution->asStream(), false);

        $this->assertCount(2, $deltas);
        $this->assertInstanceOf(TextDelta::class, $deltas[0]);
        $this->assertInstanceOf(TextDelta::class, $deltas[1]);
        $this->assertSame('Hello ', $deltas[0]->getText());
        $this->assertSame('world!', $deltas[1]->getText());
    }

    public function testAsTextStreamAndAsStreamedObjectFilterTheDeltas()
    {
        $object = new \stdClass();

        $execution = new Execution(static function () use ($object): \Generator {
            yield new Progress('delta', 'Received a streamed delta.', new TextDelta('{'));
            yield new Progress('delta', 'Received a streamed delta.', new PartialObjectDelta($object, '{'));
            yield new ResultUpdate(new TextResult('{'));
        }, streamed: true);

        $this->assertSame([$object], iterator_to_array($execution->asStreamedObject(), false));

        $execution = new Execution(static function () use ($object): \Generator {
            yield new Progress('delta', 'Received a streamed delta.', new TextDelta('{'));
            yield new Progress('delta', 'Received a streamed delta.', new PartialObjectDelta($object, '{'));
            yield new ResultUpdate(new TextResult('{'));
        }, streamed: true);

        $texts = iterator_to_array($execution->asTextStream(), false);

        $this->assertCount(1, $texts);
        $this->assertSame('{', $texts[0]->getText());
    }

    public function testAsObjectOnAStreamedExecutionReturnsTheFinalObject()
    {
        $object = new \stdClass();

        // mirrors a streamed structured output run: partial objects as deltas, the final object as result
        $execution = new Execution(static function () use ($object): \Generator {
            yield new Progress('delta', 'Received a streamed delta.', new TextDelta('{'));
            yield new Progress('delta', 'Received a streamed delta.', new PartialObjectDelta(new \stdClass(), '{'));
            yield new ResultUpdate(new ObjectResult($object));
        }, streamed: true);

        $this->assertSame($object, $execution->asObject());
    }

    public function testAsStreamThrowsWhenTheExecutionIsNotStreamed()
    {
        $execution = new Execution(static function (): \Generator {
            yield new ResultUpdate(new TextResult('Done'));
        });

        $this->expectException(LogicException::class);

        $execution->asStream()->current();
    }
}
